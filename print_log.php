<?php
require_once 'includes/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Error: You must be logged in to view this report.");
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'incoming';

// Dynamic header selection based on report type
$headerImage = ($type === 'outgoing') ? 'outgoing header2.png' : 'incoming header2.png';

try {
    if ($type === 'outgoing') {
        // Logic for Outgoing/Release - Added u_sender.signature_path
        $sql = "SELECT 
                    d.id, 
                    d.created_at, 
                    d.document_title, 
                    d.uploader_id,
                    o.office_name as destination_office, 
                    o_orig.office_name as origin_office,
                    o_sender.office_name as sender_office, 
                    u_recv.name as receiver_name, 
                    uploader.name as uploader_name,
                    u_sender.name as current_sender_name,
                    u_sender.signature_path as sender_signature,
                    dh.moved_at as interaction_date,
                    TO_CHAR(dh.moved_at, 'HH:MI AM') as time_released
                FROM document_history dh
                JOIN document d ON dh.document_id = d.id
                LEFT JOIN \"user\" u_sender ON dh.sender_id = u_sender.id 
                LEFT JOIN office o_sender ON u_sender.office_id = o_sender.id 
                LEFT JOIN office o ON dh.office_id = o.id
                LEFT JOIN \"user\" u_recv ON dh.receiver_id = u_recv.id
                LEFT JOIN \"user\" uploader ON d.uploader_id = uploader.id
                LEFT JOIN office o_orig ON uploader.office_id = o_orig.id
               WHERE dh.sender_id = :user_id AND dh.status IN ('forwarded', 'uploaded')
                ORDER BY dh.moved_at ASC";

    } elseif ($type === 'filing') {
        $sql = "SELECT d.id, d.document_title,
                       o_origin.office_name as origin_office, 
                       u.name as uploader_name, 
                       f.folder_name, f.location as folder_location, 
                       d.updated_at as interaction_date
                FROM document d
                LEFT JOIN \"user\" u ON d.uploader_id = u.id
                LEFT JOIN office o_origin ON u.office_id = o_origin.id
                LEFT JOIN folders f ON d.folder_id = f.id
                WHERE (d.receiver_user_id = :user_id OR d.uploader_id = :user_id) 
                AND d.status = 'approved'
                ORDER BY d.updated_at ASC";

    } elseif ($type === 'logs') {
        // Logic for Logs
        $sql = "SELECT d.id, d.document_title, o_orig.office_name as origin_office, 
                       u_sender.name as sender_name,
                       dh.moved_at as interaction_date, d.status
                FROM document_history dh
                JOIN document d ON dh.document_id = d.id
                LEFT JOIN \"user\" u_sender ON dh.sender_id = u_sender.id
                LEFT JOIN office o_orig ON u_sender.office_id = o_orig.id
                WHERE dh.receiver_id = :user_id
                ORDER BY dh.moved_at ASC";
                
    } else {
        // Logic for Incoming
        $sql = "SELECT d.id, d.document_title, d.created_at, o_orig.office_name as origin_office, 
                       u_sender.name as sender_name, dh.moved_at as interaction_date, d.status
                FROM document_history dh
                JOIN document d ON dh.document_id = d.id
                LEFT JOIN \"user\" u_sender ON dh.sender_id = u_sender.id
                LEFT JOIN office o_orig ON u_sender.office_id = o_orig.id
                WHERE dh.receiver_id = :user_id 
                AND dh.moved_at = (SELECT MAX(moved_at) FROM document_history WHERE document_id = d.id)
                AND d.status != 'approved'
                ORDER BY dh.moved_at ASC";
    }
                 
    $stmt = $pdo->prepare($sql);
    $params = ($type === 'filing') ? ['user_id' => $user_id, 'user_id' => $user_id] : ['user_id' => $user_id];
    $stmt->execute(['user_id' => $user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registry Log - <?= ucfirst($type) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f0f0f0; }
        .no-print-bar { background: #333; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        .btn { padding: 8px 20px; cursor: pointer; border: none; border-radius: 4px; font-weight: bold; text-decoration: none; }
        .btn-back { background: #666; color: white; margin-right: 10px; }
        .btn-print { background: #28a745; color: white; }
        .paper { background: white; width: 13in; margin: 20px auto; padding: 0.35in; box-shadow: 0 0 10px rgba(0,0,0,0.1); min-height: 8.5in; display: flex; flex-direction: column; box-sizing: border-box; }
        .main-content { flex: 1; }
        .header-img img { width: 100%; height: auto; display: block; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid black; padding: 5px 3px; text-align: center; text-transform: uppercase; }
        th { background-color: white; font-weight: bold; }
        .footer { margin-top: 15px; font-size: 9px; text-align: left; width: 100%; }
        @media print {
            .no-print-bar { display: none !important; }
            body { background-color: white !important; margin: 0; padding: 0; }
            .paper { margin: 0 !important; box-shadow: none !important; width: 100% !important; height: 7.8in; }
            @page { size: 13in 8.5in; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div><strong>REGISTRY LOG PREVIEW (LONG SIZE)</strong></div>
        <div>
            <button onclick="window.history.back()" class="btn btn-back">Go Back</button>
            <button onclick="window.print()" class="btn btn-print">Print Sheet</button>
        </div>
    </div>

    <div class="paper">
        <div class="main-content">
            <div class="header-img">
                <img src="<?= htmlspecialchars($headerImage) ?>" alt="Registry Header">
            </div>

            <table>
                <thead>
                    <?php if ($type === 'outgoing'): ?>
                        <tr>
                            <th>Date Received</th><th>Doc No.</th><th>Office of Origin</th><th>Sender's Office</th>
                            <th>Destination Office</th><th>Addressee</th><th>Document Title</th><th>Responsible Person</th>
                            <th>Date Released</th><th>Time Released</th><th>Name/Signature</th>
                        </tr>
                    <?php elseif ($type === 'filing'): ?>
                        <tr>
                            <th>Date</th><th>Doc No.</th><th>Office of Origin</th><th>Document Title</th>
                            <th>Responsible Person</th><th>Folder Name</th><th>Location</th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>Date Received</th><th>Doc No.</th><th>Office of Origin</th><th>Document Title</th>
                            <th>Responsible Person</th><th>Status</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="100%">No records found.</td></tr>
                    <?php else: foreach ($records as $row): ?>
                        <tr>
                            <?php if ($type === 'outgoing'): ?>
                                <td><?= date('m/d/Y', strtotime($row['created_at'])) ?></td>
                                <td>#<?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['origin_office'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['sender_office'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['destination_office'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['receiver_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['document_title']) ?></td>
                                <td><?= htmlspecialchars($row['current_sender_name']) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['interaction_date'])) ?></td>
                                <td><?= $row['time_released'] ?></td>
                                <td style="padding: 2px;">
                                    <?php if (!empty($row['sender_signature'])): ?>
                                        <img src="uploads/signatures/<?= htmlspecialchars($row['sender_signature']) ?>" style="max-height: 30px; width: auto; display: block; margin: 0 auto;">
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($type === 'filing'): ?>
                                <td><?= date('m/d/Y', strtotime($row['interaction_date'])) ?></td>
                                <td>#<?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['origin_office'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['document_title']) ?></td>
                                <td><?= htmlspecialchars($row['uploader_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['folder_name'] ?? 'Unassigned') ?></td>
                                <td><?= htmlspecialchars($row['folder_location'] ?? 'N/A') ?></td>
                            <?php else: ?>
                                <td><?= date('m/d/Y', strtotime($row['interaction_date'])) ?></td>
                                <td>#<?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['origin_office'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['document_title']) ?></td>
                                <td><?= htmlspecialchars($row['sender_name'] ?? 'Staff') ?></td>
                                <td><?= strtoupper($row['status']) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        
    </div>
</body>
</html>