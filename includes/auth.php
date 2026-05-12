<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

function registerUser($data) {
    $pdo = getPDO();
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM \"user\" WHERE email = ? OR employee_number = ?");
    $stmt->execute([$data['email'], $data['employee_number']]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email or Employee Number already exists'];
    }
    
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Insert using office_id (the foreign key)
    $stmt = $pdo->prepare("INSERT INTO \"user\" (employee_number, name, email, password, position, office_id) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([
        $data['employee_number'],
        $data['name'],
        $data['email'],
        $hashedPassword,
        $data['position'],
        $data['office_id']
    ])) {
        return loginUser($data['email'], $data['password']);
    }
    return ['success' => false, 'message' => 'Registration failed'];
}

function loginUser($email, $password) {
    $pdo = getPDO();
    // FIXED: Removed 'deleted_at IS NULL' because the column doesn't exist in your DB
    $stmt = $pdo->prepare("SELECT * FROM \"user\" WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Store essential info in Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['office_id'] = $user['office_id'];
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Invalid email or password'];
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Stats and Document functions for the Dashboard
function getIncomingDocuments($user_id) {
    $pdo = getPDO();
    $sql = "SELECT d.*, dr.status as current_step_status, dr.remarks 
            FROM document_routing dr
            JOIN document d ON dr.document_id = d.id
            WHERE dr.receiver_id = ? AND dr.is_current = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getSentDocuments($user_id) {
    $pdo = getPDO();
    $sql = "SELECT * FROM document WHERE uploader_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}