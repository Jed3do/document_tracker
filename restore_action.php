<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'])) {
    $pdo = getPDO();
    $doc_id = $_POST['doc_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Update the document to be no longer archived
        // We also check uploader_id to ensure the user owns the document they are restoring
        $stmt = $pdo->prepare("
            UPDATE \"document\" 
            SET is_archived = FALSE 
            WHERE id = ? AND uploader_id = ?
        ");
        
        if ($stmt->execute([$doc_id, $user_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}