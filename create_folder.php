<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

// 1. Check Authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$pdo = getPDO();
$data = json_decode(file_get_contents('php://input'), true);

// 2. Collect Data
$folder_name = trim($data['folder_name'] ?? '');
$location = trim($data['location'] ?? 'Main Registry');
$office_id = $_SESSION['office_id'];
$user_id = $_SESSION['user_id'];

// 3. Validation
if (empty($folder_name)) {
    echo json_encode(['success' => false, 'message' => 'Folder name is required.']);
    exit;
}

try {
    // 4. Insert into Database
    // We include office_id to satisfy the UNIQUE(folder_name, office_id) constraint
    $stmt = $pdo->prepare("
        INSERT INTO folders (folder_name, location, office_id, creator_id) 
        VALUES (?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $folder_name, 
        $location, 
        $office_id, 
        $user_id
    ]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create folder.']);
    }

} catch (PDOException $e) {
    // Handle the unique constraint error (Postgres code 23505)
    if ($e->getCode() == '23505') {
        echo json_encode([
            'success' => false, 
            'message' => 'A folder with this name already exists in your office.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
}