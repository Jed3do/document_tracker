<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$pdo = getPDO();
$doc_id = $_GET['doc_id'] ?? null;

if (!$doc_id) {
    echo json_encode([]);
    exit();
}

try {
    /**
     * UPDATED QUERY:
     * We join the office table to u1 (the sender) and o2 (the receiver)
     * to ensure both office names are retrieved.
     */
    $stmt = $pdo->prepare("
        SELECT 
            h.id,
            h.status,
            h.remarks,
            TO_CHAR(h.moved_at, 'Mon DD, YYYY HH:MI AM') as moved_at,
            u1.name as sender_name, 
            u2.name as receiver_name,
            o.office_name,
            o2.office_name as receiver_office
        FROM document_history h
        JOIN \"user\" u1 ON h.sender_id = u1.id
        LEFT JOIN \"user\" u2 ON h.receiver_id = u2.id
        LEFT JOIN office o ON u1.office_id = o.id
        LEFT JOIN office o2 ON u2.office_id = o2.id
        WHERE h.document_id = ?
        ORDER BY h.moved_at DESC
    ");
    
    $stmt->execute([$doc_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($history);

} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error', 
        'message' => $e->getMessage()
    ]);
}
?>