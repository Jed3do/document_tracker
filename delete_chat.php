<?php
// delete_chat.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');
if (!isLoggedIn()) exit(json_encode(['success' => false]));

$pdo = getPDO();
$message_id = $_POST['message_id'] ?? null;
$user_id = $_SESSION['user_id'];

if ($message_id) {
    // SECURITY: Ensure the sender_id matches the logged-in user
    $stmt = $pdo->prepare("DELETE FROM document_chats WHERE id = ? AND sender_id = ?");
    $stmt->execute([$message_id, $user_id]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
} else {
    echo json_encode(['success' => false]);
}