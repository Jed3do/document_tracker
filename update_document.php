<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$pdo = getPDO();
$doc_id = $_POST['doc_id'] ?? null;
$title = $_POST['title'] ?? '';
$due_date = $_POST['due_date'] ?: null;
$user_id = $_SESSION['user_id'];

if (!$doc_id) {
    echo json_encode(['success' => false, 'message' => 'Missing document ID']);
    exit();
}

try {
    // 1. Verify ownership and status (Only uploader can edit, only if status is pending)
    $check_stmt = $pdo->prepare("SELECT filename FROM \"document\" WHERE id = ? AND uploader_id = ? AND status = 'pending'");
    $check_stmt->execute([$doc_id, $user_id]);
    $existing_doc = $check_stmt->fetch();

    if (!$existing_doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found or cannot be edited']);
        exit();
    }

    $new_filename = $existing_doc['filename']; // Default to old filename

    // 2. Handle File Upload if a new file is provided
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $new_filename = bin2hex(random_bytes(10)) . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
            // Delete the old physical file
            $old_file_path = $upload_dir . $existing_doc['filename'];
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        } else {
            throw new Exception("Failed to upload new file.");
        }
    }

    // 3. Update Database
    $update_stmt = $pdo->prepare("
        UPDATE \"document\" 
        SET document_title = ?, 
            due_date = ?, 
            filename = ? 
        WHERE id = ?
    ");
    
    $success = $update_stmt->execute([$title, $due_date, $new_filename, $doc_id]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}