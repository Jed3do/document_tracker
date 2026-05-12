<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Ensure the user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'])) {
    $pdo = getPDO();
    $doc_id = (int)$_POST['doc_id'];
    $sender_id = $_SESSION['user_id'];
    $type = $_POST['type'] ?? 'reminder';

    try {
        // 1. Verify the document belongs to the sender and get the receiver (current holder)
        $stmt = $pdo->prepare("SELECT uploader_id, receiver_user_id, document_title, filename FROM \"document\" WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();

        if (!$doc || $doc['uploader_id'] != $sender_id) {
            echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
            exit();
        }

        $receiver_id = $doc['receiver_user_id'];
        $doc_name = $doc['document_title'] ?: $doc['filename'];

        // 2. Insert into your notifications table
        // Adjust column names (user_id, message, etc.) to match your database schema
        $notif_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, sender_id, document_id, message, type, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $message = "REMINDER: The document \"$doc_name\" is overdue. Please review it as soon as possible.";
        $notif_stmt->execute([$receiver_id, $sender_id, $doc_id, $message, $type]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}