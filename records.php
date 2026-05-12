<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];
$type = $_GET['type'] ?? 'incoming';
$folder_id = $_GET['folder_id'] ?? null;

// ADDED: Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$current_folder_name = "";
if ($folder_id) {
    $fStmt = $pdo->prepare("SELECT folder_name FROM folders WHERE id = ? AND office_id = ?");
    $fStmt->execute([$folder_id, $office_id]);
    $folder_data = $fStmt->fetch();
    
    if (!$folder_data) {
        header('Location: records.php?type=filing');
        exit();
    }
    $current_folder_name = $folder_data['folder_name'];
}

try {
    $folderListStmt = $pdo->prepare("SELECT id, folder_name FROM folders WHERE office_id = ? ORDER BY folder_name ASC");
    $folderListStmt->execute([$office_id]);
    $availableFolders = $folderListStmt->fetchAll();

    switch ($type) {
        case 'outgoing':
            // ADDED: LIMIT and OFFSET to query
            $query = "
                SELECT 
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
                WHERE dh.sender_id = ? AND dh.status IN ('forwarded', 'uploaded')
                ORDER BY dh.moved_at ASC LIMIT $limit OFFSET $offset";

            $headers = ['Date Received', 'Doc No.', 'Office of Origin', 'Sender\'s Office', 'Destination Office', 'Addressee', 'Document Title',
                        'Responsible Person', 'Date Released', 'Time Released', 'Trail'];
            
            $params = [$user_id]; 
            break;

        case 'filing':
            if ($folder_id) {
                $folderFilter = " AND d.folder_id = ?";
                $params = [$user_id, $user_id, $folder_id];
            } else {
                $folderFilter = " AND d.folder_id IS NULL";
                $params = [$user_id, $user_id];
            }

            // ADDED: LIMIT and OFFSET to query
            $query = "SELECT d.*, o_origin.office_name as origin_office, u.name as uploader_name, 
                           f.folder_name, f.location as folder_location, d.updated_at as interaction_date
                     FROM document d
                     LEFT JOIN \"user\" u ON d.uploader_id = u.id
                     LEFT JOIN office o_origin ON u.office_id = o_origin.id
                     LEFT JOIN folders f ON d.folder_id = f.id
                     WHERE (d.receiver_user_id = ? OR d.uploader_id = ?) 
                     AND d.status = 'approved' $folderFilter
                     ORDER BY d.updated_at ASC LIMIT $limit OFFSET $offset";
            
            $headers = ['Date', 'Doc No.', 'Office of Origin', 'Document Title', 'Responsible Person', 'Folder Name', 'Location', 'Trail'];
            break;

      case 'logs':
        // ADDED: LIMIT and OFFSET to query
        $query = "SELECT d.id, d.document_title, o_orig.office_name as origin_office, 
                       u_sender.name as sender_name,
                       dh.moved_at as interaction_date, d.status
                FROM document_history dh
                JOIN document d ON dh.document_id = d.id
                LEFT JOIN \"user\" u_sender ON dh.sender_id = u_sender.id
                LEFT JOIN office o_orig ON u_sender.office_id = o_orig.id
                WHERE dh.receiver_id = ?
                ORDER BY dh.moved_at ASC LIMIT $limit OFFSET $offset";

        $headers = ['Date Interacted', 'Doc No.', 'Office of Origin', 'Document Title', 'Responsible Person', 'Status', 'Trail'];
        $params = [$user_id]; 
        break;

        default: // incoming
            // ADDED: LIMIT and OFFSET to query
            $query = "SELECT d.id, d.document_title, d.created_at, o_orig.office_name as origin_office, 
                      u_sender.name as sender_name, dh.moved_at as interaction_date, d.status
                      FROM document_history dh
                      JOIN document d ON dh.document_id = d.id
                      LEFT JOIN \"user\" u_sender ON dh.sender_id = u_sender.id
                      LEFT JOIN office o_orig ON u_sender.office_id = o_orig.id
                      WHERE dh.receiver_id = ? 
                      AND dh.moved_at = (SELECT MAX(moved_at) FROM document_history WHERE document_id = d.id)
                      AND d.status != 'approved'
                      ORDER BY dh.moved_at ASC LIMIT $limit OFFSET $offset";
            $headers = ['Date Received', 'Doc No.', 'Office of Origin', 'Document Title', 'Responsible Person', 'Status', 'Trail'];
            $params = [$user_id];
            break;
    }

    // Get Total Count for Numeric Pagination
    $countQuery = "SELECT COUNT(*) FROM (" . preg_replace('/LIMIT \d+ OFFSET \d+/i', '', $query) . ") AS total_count";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

} catch (PDOException $e) {
    $records = [];
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registry Logs | DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sidebar-gradient { background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%) !important; }
        @media (min-width: 768px) { .main-content { margin-left: 16rem; } }
        @media print {
            .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
        }
        .hidden-row { display: none !important; }
        th { border-right: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1 !important; }
        td { border-right: 1px solid #f1f5f9; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-900">

    <div class="no-print">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <main class="flex-1 p-8 main-content transition-all duration-300">
        
        <header class="mb-8 no-print">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-black text-gray-800 tracking-tight mb-1 uppercase italic">
                        <?= $folder_id ? htmlspecialchars($current_folder_name) : "Registry Log Sheets" ?>
                    </h1>
                    <p class="text-xs text-slate-500 font-bold mb-6 tracking-widest uppercase">
                        <?= $folder_id ? "Viewing files inside folder" : "Official Records Registry System" ?>
                    </p>
                </div>
                <div class="flex gap-2">
                    <?php if($type == 'filing'): ?>
                        <a href="folders.php" class="bg-blue-600 text-white px-5 py-2.5 rounded-2xl font-black text-xs hover:bg-blue-700 transition flex items-center gap-2 shadow-lg shadow-blue-100 uppercase">
                            <i class="fas fa-folders"></i> Folders
                        </a>
                        <?php if($folder_id): ?>
                            <a href="records.php?type=filing" class="bg-slate-800 text-white px-5 py-2.5 rounded-2xl font-black text-xs hover:bg-slate-900 transition flex items-center gap-2 shadow-lg shadow-slate-100 uppercase">
                                <i class="fas fa-list"></i> View All Unfiled
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button onclick="window.open('print_log.php?type=<?= $type ?>', '_blank')" class="bg-white border-2 border-slate-200 text-slate-600 px-5 py-2.5 rounded-2xl font-black text-xs hover:bg-slate-50 transition flex items-center gap-2 uppercase">
                        <i class="fas fa-print"></i> Print Log
                    </button>
                </div>
            </div>

            <div class="flex space-x-2 bg-slate-200 p-1.5 rounded-2xl w-fit mb-8 shadow-inner">
                <a href="?type=incoming" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= $type == 'incoming' ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">INCOMING</a>
                <a href="?type=outgoing" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= $type == 'outgoing' ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">OUTGOING</a>
                <a href="?type=filing" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= ($type == 'filing') ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">FILING</a>
                <a href="?type=logs" class="px-8 py-2.5 rounded-xl font-black text-sm transition-all <?= ($type == 'logs') ? 'bg-white text-blue-700 shadow-md' : 'text-slate-600 hover:text-blue-600' ?>">LOGS</a>
            </div>

            <div class="flex gap-4">
                <div class="relative flex-1 group">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="recordSearch" onkeyup="applySearch()" placeholder="Search registry records..." 
                           class="w-full pl-11 pr-4 py-3 bg-white border-2 border-slate-200 rounded-2xl focus:border-blue-500 outline-none font-bold text-sm">
                </div>
                <div class="bg-blue-600 text-white px-6 py-3 rounded-2xl flex items-center gap-3 shadow-lg shadow-blue-200">
                    <span id="resultCounter" class="text-xl font-black"><?= count($records) ?></span>
                    <span class="text-[10px] font-black uppercase tracking-widest opacity-80 border-l border-white/20 pl-3">Records Found</span>
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
                        <tr><td colspan="100%" class="px-6 py-20 text-center text-slate-400 italic">No records found in this section.</td></tr>
                    <?php else: foreach ($records as $row): ?>
                        <tr class="record-row hover:bg-blue-50/50 transition-colors">
                            <?php if ($type == 'outgoing'): ?>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['sender_office']) ?></td> 
                                <td class="px-6 py-4"><?= htmlspecialchars($row['destination_office'] ?? 'Registry') ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['receiver_name'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['uploader_name']) ?></td>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4 uppercase"><?= $row['time_released'] ?></td>
                            <?php elseif ($type == 'filing'): ?>
                                <td class="px-6 py-4"><?= date('m/d/Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office'] ?? 'Main Office') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['uploader_name']) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-blue-600 italic bg-blue-50 px-2 py-1 rounded-lg">
                                            <?= htmlspecialchars($row['folder_name'] ?? 'Unassigned') ?>
                                        </span>
                                        <button onclick="openMoveModal(<?= $row['id'] ?>)" class="text-slate-300 hover:text-blue-600 transition no-print">
                                            <i class="fas fa-pen-to-square text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-emerald-600 italic"><?= htmlspecialchars($row['folder_location'] ?? 'Vault-A') ?></td>
                            <?php elseif ($type == 'logs'): ?>
                                <td class="px-6 py-4"><?= date('m/d/Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office'] ?? 'Main Office') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['sender_name'] ?? 'Staff') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-[10px] <?= $row['status'] == 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>">
                                        <?= strtoupper($row['status']) ?>
                                    </span>
                                </td>
                            <?php else: ?>
                                <td class="px-6 py-4"><?= date('m/d/Y', strtotime($row['interaction_date'])) ?></td>
                                <td class="px-6 py-4 text-xs font-black text-blue-600">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($row['origin_office'] ?? 'Main Office') ?></td>
                                <td class="px-6 py-4 font-black uppercase text-slate-900"><?= htmlspecialchars($row['document_title']) ?></td>
                                <td class="px-6 py-4 text-xs"><?= htmlspecialchars($row['sender_name'] ?? 'Staff') ?></td>
                                <td class="px-6 py-4"><span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px]"><?= strtoupper($row['status']) ?></span></td>
                            <?php endif; ?>
                            <td class="px-6 py-4 text-center no-print">
                                <button onclick="location.href='track.php?id=<?= $row['id'] ?>'" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black hover:bg-blue-700 transition shadow-sm">VIEW TRAIL</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-between items-center no-print">
                <span class="text-[10px] font-black text-slate-400 uppercase">Page <?= $page ?> of <?= $totalPages ?: 1 ?></span>
                <div class="flex gap-1">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?type=<?= $type ?><?= $folder_id ? '&folder_id='.$folder_id : '' ?>&page=<?= $i ?>" 
                           class="px-3 py-1.5 rounded-lg font-black text-[10px] transition-all <?= $i == $page ? 'bg-blue-600 text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-blue-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="moveModal" class="fixed inset-0 bg-slate-900/80 hidden items-center justify-center z-[110] backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl p-8">
            <h2 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tighter italic">Move to Folder</h2>
            <p class="text-[10px] text-slate-400 font-bold uppercase mb-6 tracking-widest">Relocate document within registry</p>
            <input type="hidden" id="moveDocId">
            <div class="space-y-4">
                <select id="targetFolderId" class="w-full px-5 py-4 bg-slate-100 border-2 border-transparent focus:border-blue-500 rounded-2xl outline-none font-bold">
                    <option value="">-- SELECT FOLDER --</option>
                    <?php foreach ($availableFolders as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['folder_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3 mt-8">
                <button onclick="closeMoveModal()" class="flex-1 py-3 font-black text-xs text-slate-400 uppercase tracking-widest">Cancel</button>
                <button onclick="submitMove()" class="flex-1 bg-blue-600 text-white py-3 rounded-2xl font-black text-xs shadow-lg shadow-blue-200 uppercase tracking-widest">Move Now</button>
            </div>
        </div>
    </div>

    <div id="historyModal" class="fixed inset-0 bg-slate-900/80 hidden items-center justify-center z-[100] backdrop-blur-sm p-4 no-print">
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl flex flex-col max-h-[85vh]">
            <div class="p-6 border-b flex justify-between items-center">
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter italic">Document Trail</h2>
                <button onclick="closeHistoryModal()" class="text-slate-400 hover:text-red-500 text-2xl font-black">&times;</button>
            </div>
            <div id="historyContent" class="p-8 overflow-y-auto flex-1 bg-slate-50"></div>
        </div>
    </div>

    <script>
        function openMoveModal(docId) {
            document.getElementById('moveDocId').value = docId;
            document.getElementById('moveModal').classList.replace('hidden', 'flex');
        }
        function closeMoveModal() {
            document.getElementById('moveModal').classList.replace('flex', 'hidden');
        }
        async function submitMove() {
            const docId = document.getElementById('moveDocId').value;
            const folderId = document.getElementById('targetFolderId').value;
            if(!folderId) return Swal.fire('Wait', 'Please select a folder', 'warning');

            try {
                const response = await fetch('move_to_folder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ doc_id: docId, folder_id: folderId })
                });
                const result = await response.json();
                if(result.success) { 
                    Swal.fire({
                        icon: 'success',
                        title: 'Moved!',
                        text: 'Document has been filed.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); 
                    });
                }
                else { Swal.fire('Error', result.message, 'error'); }
            } catch(e) { Swal.fire('Error', 'Failed to move', 'error'); }
        }

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
                    const colors = { 'approved': 'bg-emerald-600', 'forwarded': 'bg-blue-600', 'rejected': 'bg-red-600' };
                    html += `
                        <div class="relative pl-12">
                            <div class="absolute left-0 top-0 w-10 h-10 ${colors[step.status] || 'bg-slate-600'} rounded-full border-4 border-white flex items-center justify-center text-white shadow-md z-10">
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
            } catch (e) { content.innerHTML = '<p class="text-red-500 font-bold">Error loading trail.</p>'; }
        }
        function closeHistoryModal() {
            document.getElementById('historyModal').classList.replace('flex', 'hidden');
        }
    </script>
</body>
</html>