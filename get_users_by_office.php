<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Only allow logged-in users to fetch the list
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$office_id = isset($_GET['office_id']) ? (int)$_GET['office_id'] : 0;
$pdo = getPDO();

try {
    // We select the ID, Name, and Position so the uploader knows exactly who they are picking
    $stmt = $pdo->prepare("SELECT id, name, position FROM \"user\" WHERE office_id = ? ORDER BY name ASC");
    $stmt->execute([$office_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($users);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}