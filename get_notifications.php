<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Incoming Documents (You are the Receiver)
    $stmtIncoming = $pdo->prepare("
        SELECT d.document_title, d.filename, u.name as sender_name, 'incoming' as type
        FROM document d
        JOIN \"user\" u ON d.uploader_id = u.id
        WHERE d.receiver_user_id = ? AND d.status = 'pending'
        ORDER BY d.created_at DESC
    ");
    $stmtIncoming->execute([$user_id]);
    $incoming = $stmtIncoming->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Processed Updates (You are the Sender)
    $stmtUpdates = $pdo->prepare("
        SELECT d.document_title, d.filename, u.name as processor_name, 'update' as type, d.status
        FROM document d
        JOIN \"user\" u ON d.receiver_user_id = u.id
        WHERE d.uploader_id = ? 
        AND d.status IN ('approved', 'rejected') 
        AND d.seen_by_sender = false
        ORDER BY d.processed_at DESC
    ");
    $stmtUpdates->execute([$user_id]);
    $updates = $stmtUpdates->fetchAll(PDO::FETCH_ASSOC);

    // Combine them for the total count
    $all_notifications = array_merge($incoming, $updates);

    echo json_encode([
        'count' => count($all_notifications),
        'items' => $all_notifications,
        'incoming_count' => count($incoming),
        'update_count' => count($updates)
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}