<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Get the current view type from the URL, default to 'incoming'
$type = $_GET['type'] ?? 'incoming';

/**
 * LOGIC BASED ON BISU STANDARDS:
 * Incoming: General view of documents sent to the user.
 * Outgoing: Matches Form F-ADF-REC-002 (For Release/Outgoing).
 * Filing: Matches Form F-ADF-REC-001 (Incoming for Filing) for 'Approved' docs.
 */

try {
    switch ($type) {
        case 'outgoing':
            // BISU Form: F-ADF-REC-002 [cite: 34]
            $query = "SELECT d.*, o.office_name as destination_office, u.name as receiver_name, 
                             uploader.name as uploader_name,
                             d.updated_at as interaction_date,
                             TO_CHAR(d.updated_at, 'HH:MI AM') as time_released
                      FROM document d
                      LEFT JOIN office o ON d.receiver_office_id = o.id
                      LEFT JOIN \"user\" u ON d.receiver_user_id = u.id
                      LEFT JOIN \"user\" uploader ON d.uploader_id = uploader.id
                      WHERE d.uploader_id = ?
                      ORDER BY d.updated_at DESC";
            $headers = ['Date Received', 'Doc No.', 'Office of Origin', 'Addressee', 'Document Title (Particulars)', 'Responsible Person', 'Date Released', 'Time Released', 'Trail']; [cite: 33]
            break;

        case 'filing':
            // BISU Form: F-ADF-REC-001 [cite: 14]
            $query = "SELECT d.*, o.office_name as origin_office, u.name as uploader_name, d.updated_at as interaction_date
                      FROM document d
                      LEFT JOIN office o ON d.receiver_office_id = o.id
                      LEFT JOIN \"user\" u ON d.uploader_id = u.id
                      WHERE (d.receiver_user_id = ? OR d.uploader_id = ?) AND d.status = 'approved'
                      ORDER BY d.updated_at DESC";
            $headers = ['Date', 'Doc No.', 'Office of Origin', 'File Folder Label/Document Title (Particulars)', 'Responsible Person', 'Folder Code', 'Storage Location', 'Trail']; [cite: 13]
            break;

        default: // incoming (General Registry View)
            $query = "SELECT d.*, o.office_name as origin_office, u.name as sender_name, d.updated_at as interaction_date
                      FROM document d
                      LEFT JOIN office o ON d.receiver_office_id = o.id
                      LEFT JOIN \"user\" u ON d.uploader_id = u.id
                      WHERE d.receiver_user_id = ? AND d.status != 'approved'
                      ORDER BY d.updated_at DESC";
            $headers = ['Date Received', 'Doc No.', 'Office of Origin', 'Document Title', 'Responsible Person', 'Status', 'Trail'];
            break;
    }

    $stmt = $pdo->prepare($query);
    if ($type === 'filing') {
        $stmt->execute([$user_id, $user_id]);
    } else {
        $stmt->execute([$user_id]);
    }
    $records = $stmt->fetchAll();

} catch (PDOException $e) {
    $records = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records Log - DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hidden-row { display: none !important; }
        /* Professional Table Styling matching BISU Registry standards */
        th { border-right: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1 !important; }
        td { border-right: 1px solid #f1f5f9; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-900">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8">
        <header class="mb-8">
            <h1 class="text-3xl font-black text-gray-800 tracking-tight mb-1 uppercase">Registry Log Sheets</h1>
            <p class="text-xs text-slate-500 font-bold mb-6 tracking-widest uppercase">Bohol Island State University • ISO 9001:2015 Certified</p>

            <div class="flex space-x-2 bg-slate-200 p-1.5 rounded-2xl w-fit mb-8 shadow-inner">
                <a href="?type=incoming" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= $type == 'incoming' ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">INCOMING</a>
                <a href="?type=outgoing" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= $type == 'outgoing' ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">OUTGOING (F-ADF-REC-002)</a>
                <a href="?type=filing" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= $type == 'filing' ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">FOR FILING (F-ADF-REC-001)</a>
            </div>

            <div class="flex gap-4">
                <div class="relative flex-1 group">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="recordSearch" onkeyup="applySearch()" placeholder="Search registry records..." 
                        class="w-full pl-11 pr-4 py-3 bg-white border-2 border-slate-200 rounded-2xl focus:border-blue-500 outline-none font-bold text-sm transition-all">
                </div>
                <div class="bg-blue-600 text-white px-6 py-3 rounded-2xl flex items-center gap-3 shadow-lg shadow-blue-200">
                    <span class="text-[10px] font-black uppercase tracking-widest opacity-80">Total</span>
                    <span id="resultCounter" class="text-xl font-black"><?= count($records) ?></span>
                </div>
            </div>
        </header>

        <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse" id="recordsTable">
                <thead class="bg-slate-100 text-slate-900 text-[10px] uppercase font-black tracking-tight">
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <th class="px-6 py-5"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-bold text-slate-800 text-sm">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="<?= count($headers) ?>" class="px-6 py-20 text-center text-slate-400 italic font-medium">No records found for this category.</td>
                        </tr>
                    <?php else: foreach ($records as $row): ?>
                        <tr class="record-row hover:bg-blue-50/50 transition-colors">
                            
                            <?php if ($type == 'outgoing'): ?>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office'] ?? 'BISU Office') ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['receiver_name'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['uploader_name']) ?></td>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4"><?= $row['time_released'] ?></td>

                            <?php elseif ($type == 'filing'): ?>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office'] ?? 'Origin Office') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['uploader_name']) ?></td>
                                <td class="px-6 py-4 text-blue-500">FC-<?= $row['id'] ?></td>
                                <td class="px-6 py-4 text-emerald-600 italic">Vault Section-A</td>

                            <?php else: // General Incoming ?>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office'] ?? 'Origin Office') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['sender_name'] ?? 'Staff') ?></td>
                                <td class="px-6 py-4"><span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black"><?= strtoupper($row['status']) ?></span></td>
                            <?php endif; ?>

                            <td class="px-6 py-4 text-center">
                                <button onclick="viewHistory(<?= $row['id'] ?>)" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black hover:bg-blue-700 transition shadow-sm">
                                    VIEW TRAIL
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="historyModal" class="fixed inset-0 bg-slate-900/80 hidden items-center justify-center z-[100] backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl flex flex-col max-h-[85vh]">
            <div class="p-6 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Document Trail</h2>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Registry Movement History</p>
                </div>
                <button onclick="closeHistoryModal()" class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:text-red-500 transition-all text-xl font-black">&times;</button>
            </div>
            <div id="historyContent" class="p-8 overflow-y-auto flex-1 bg-slate-50"></div>
        </div>
    </div>

    <script>
        function applySearch() {
            const searchText = document.getElementById("recordSearch").value.toLowerCase();
            const rows = document.querySelectorAll(".record-row");
            let visibleCount = 0;

            rows.forEach(row => {
                const match = row.innerText.toLowerCase().includes(searchText);
                row.classList.toggle("hidden-row", !match);
                if (match) visibleCount++;
            });
            document.getElementById("resultCounter").innerText = visibleCount;
        }

        async function viewHistory(docId) {
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('historyContent');
            modal.classList.replace('hidden', 'flex');
            content.innerHTML = '<div class="flex justify-center py-10"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i></div>';
            
            try {
                const response = await fetch(`get_history.php?doc_id=${docId}`);
                const data = await response.json();
                let html = '<div class="relative space-y-6 before:absolute before:left-5 before:top-2 before:bottom-2 before:w-1 before:bg-blue-100">';
                
                data.forEach(step => {
                    const statusColors = {
                        'approved': 'bg-emerald-600',
                        'forwarded': 'bg-blue-600',
                        'rejected': 'bg-red-600'
                    };
                    const color = statusColors[step.status] || 'bg-slate-600';
                    
                    html += `
                        <div class="relative pl-12">
                            <div class="absolute left-0 top-0 w-10 h-10 ${color} rounded-full border-4 border-white flex items-center justify-center text-white shadow-md z-10">
                                <i class="fas ${step.status === 'forwarded' ? 'fa-share' : (step.status === 'approved' ? 'fa-check' : 'fa-times')} text-[10px]"></i>
                            </div>
                            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">${step.status} • ${step.moved_at}</p>
                                <p class="text-sm font-black text-slate-800">${step.sender_name}</p>
                                <p class="text-[11px] font-bold text-blue-600">${step.office_name || 'Registry'}</p>
                            </div>
                        </div>`;
                });
                content.innerHTML = html + '</div>';
            } catch (e) { 
                content.innerHTML = '<p class="text-center py-10 text-red-500 font-bold">Error loading trail.</p>'; 
            }
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').classList.replace('flex', 'hidden');
        }
    </script>
</body>
</html>