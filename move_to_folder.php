<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getPDO();
$data = json_decode(file_get_contents('php://input'), true);

$doc_id = $data['doc_id'] ?? null;
$folder_id = $data['folder_id'] ?? null;
$office_id = $_SESSION['office_id'];

if (!$doc_id || !$folder_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

try {
    // 1. SECURITY CHECK: Does this folder actually belong to the user's office?
    $checkStmt = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND office_id = ?");
    $checkStmt->execute([$folder_id, $office_id]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Access denied: Folder does not belong to your office.']);
        exit;
    }

    // 2. UPDATE: Attach the document to the folder
    $updateStmt = $pdo->prepare("UPDATE document SET folder_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $result = $updateStmt->execute([$folder_id, $doc_id]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}