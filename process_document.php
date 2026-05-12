<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$doc_id = $_POST['doc_id'] ?? null;
$action = $_POST['action'] ?? null; 

if (!$doc_id || !in_array($action, ['approved', 'rejected', 'forward'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verify existence and get current data
    $check_stmt = $pdo->prepare("SELECT filename FROM document WHERE id = ?");
    $check_stmt->execute([$doc_id]);
    $current_data = $check_stmt->fetch();
    
    if (!$current_data) throw new Exception('Document not found.');

    $active_filename = $current_data['filename'];

    // 2. Handle File Upload (if any)
    if (isset($_FILES['new_document']) && $_FILES['new_document']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['new_document']['name'], PATHINFO_EXTENSION);
        $new_filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (move_uploaded_file($_FILES['new_document']['tmp_name'], 'uploads/' . $new_filename)) {
            $active_filename = $new_filename;
        } else {
            throw new Exception('Failed to save file.');
        }
    }

    // 3. Logic based on Action
    if ($action === 'forward') {
        $new_receiver = $_POST['new_receiver_id'] ?? null;
        $new_office = $_POST['new_office_id'] ?? null;
        $remarks = $_POST['remarks'] ?? '';

        if (!$new_receiver || !$new_office) throw new Exception('Forwarding requires a recipient and office.');

        // UPDATE main document table (REMOVED office_id as it does not exist here)
        $update_stmt = $pdo->prepare("
            UPDATE document 
            SET receiver_user_id = ?, remarks = ?, filename = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_stmt->execute([$new_receiver, $remarks, $active_filename, $doc_id]);

        // Log to history (KEEP office_id here, as it exists in this table)
        $history_stmt = $pdo->prepare("
            INSERT INTO document_history (document_id, sender_id, receiver_id, office_id, status, remarks, filename, moved_at)
            VALUES (?, ?, ?, ?, 'forwarded', ?, ?, CURRENT_TIMESTAMP)
        ");
        $history_stmt->execute([$doc_id, $user_id, $new_receiver, $new_office, $remarks, $active_filename]);
        
        $msg = "Document forwarded successfully.";

    } else {
        // UPDATE main document table status for approval/rejection
        $update_stmt = $pdo->prepare("UPDATE document SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update_stmt->execute([$action, $doc_id]);

        // Log to history
        $history_stmt = $pdo->prepare("
            INSERT INTO document_history (document_id, sender_id, status, remarks, filename, moved_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $history_stmt->execute([$doc_id, $user_id, $action, "Status updated to: $action", $active_filename]);
        
        $msg = "Document has been $action.";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}