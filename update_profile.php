<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $pdo = getPDO();
    $uid = $_SESSION['user_id'];
    $name = $_POST['name'];
    $position = $_POST['position'];
    $password = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // 1. Handle Profile Picture Upload
    $pix_name = null;
    if (!empty($_FILES['profile_pix']['name'])) {
        $ext = pathinfo($_FILES['profile_pix']['name'], PATHINFO_EXTENSION);
        $pix_name = "user_" . $uid . "_" . time() . "." . $ext;
        $target = "uploads/profiles/" . $pix_name;
        
        if (!is_dir('uploads/profiles/')) mkdir('uploads/profiles/', 0777, true);
        move_uploaded_file($_FILES['profile_pix']['tmp_name'], $target);
    }

    // 2. Handle Signature Upload
    $sig_name = null;
    if (!empty($_FILES['signature']['name'])) {
        $ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $sig_name = "sig_" . $uid . "_" . time() . "." . $ext;
        $target = "uploads/signatures/" . $sig_name;
        
        if (!is_dir('uploads/signatures/')) mkdir('uploads/signatures/', 0777, true);
        move_uploaded_file($_FILES['signature']['tmp_name'], $target);
    }

    // 3. Prepare Update Query
    $sql = "UPDATE \"user\" SET name = ?, position = ?";
    $params = [$name, $position];

    if ($pix_name) {
        $sql .= ", profile_pix = ?";
        $params[] = $pix_name;
    }

    // Add signature_path to the update
    if ($sig_name) {
        $sql .= ", signature_path = ?";
        $params[] = $sig_name;
    }

    if (!empty($password) && $password === $confirm) {
        $sql .= ", password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id = ?";
    $params[] = $uid;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: dashboard.php?updated=success");
    exit();
}
?>