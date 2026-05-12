<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $pdo = getPDO();
    $doc_id = $_POST['doc_id'] ?? null;
    $message = trim($_POST['message'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($doc_id && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO document_chats (document_id, sender_id, message) VALUES (?, ?, ?)");
        $success = $stmt->execute([$doc_id, $user_id, $message]);
        echo json_encode(['success' => $success]);
        exit;
    }
}
echo json_encode(['success' => false, 'message' => 'Invalid request']);