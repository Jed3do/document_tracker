<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d'); 

// ADDED: Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

try {
    // ADDED: Get total records for pagination
    $count_all_stmt = $pdo->prepare("SELECT COUNT(*) FROM \"document\" WHERE uploader_id = ? AND is_archived = FALSE");
    $count_all_stmt->execute([$user_id]);
    $total_records = $count_all_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // MODIFIED: Added LIMIT and OFFSET to query
    $stmt = $pdo->prepare("
        SELECT d.*, u.name as receiver_name, o.office_name as receiver_office
        FROM \"document\" d
        JOIN \"user\" u ON d.receiver_user_id = u.id
        LEFT JOIN office o ON u.office_id = o.id
        WHERE d.uploader_id = ? AND d.is_archived = FALSE
        ORDER BY (d.due_date < ? AND d.status = 'pending') DESC, d.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$user_id, $today]);
    $sent_docs = $stmt->fetchAll();

    $notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM \"document\" WHERE receiver_user_id = ? AND status = 'pending' AND is_archived = FALSE");
    $notif_stmt->execute([$user_id]);
    $notification_count = $notif_stmt->fetchColumn();
} catch (PDOException $e) {
    $sent_docs = [];
    $notification_count = 0;
    $total_records = 0;
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Status - DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-gradient { background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%); }
        .status-pill { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
        @keyframes fast-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .animate-urgent { animation: fast-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        .active-filter { background-color: #1e40af !important; color: white !important; }

        @media print {
            @page { margin: 0; }
            body { margin: 0; }
            body > *:not(#print-iframe-target) { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex text-gray-900">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 md:ml-64 p-8 transition-all duration-300 overflow-y-auto">
        <div class="max-w-6xl mx-auto"> 
            <header class="mb-8 flex flex-col gap-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Track Documents</h1>
                        <p class="text-gray-700 font-medium">Monitor active files currently in transit or pending review.</p>
                    </div>
                    <button onclick="location.href='upload.php'" class="bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-blue-800 transition flex items-center gap-2 shadow-lg shadow-blue-100">
                        <i class="fas fa-plus"></i> New Upload
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-4 bg-white p-3 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="relative flex-1 min-w-[240px]">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-600"></i>
                        <input type="text" id="trackSearch" placeholder="Search title, holder, or status..." 
                               class="pl-11 pr-4 py-2 w-full bg-slate-50 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500 transition text-sm text-gray-900 placeholder:text-gray-500">
                    </div>
                    <div class="flex items-center gap-2 border-l pl-4 border-slate-200">
                        <button onclick="filterByStatus('all')" class="filter-btn active-filter px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider transition">All</button>
                        <button onclick="filterByStatus('overdue')" class="filter-btn px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider text-red-700 hover:bg-red-50 transition">Overdue</button>
                        <button onclick="filterByStatus('pending')" class="filter-btn px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider text-amber-700 hover:bg-amber-50 transition">Pending</button>
                    </div>
                </div>
            </header>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-x-auto flex flex-col">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700 text-xs uppercase font-black">
                        <tr>
                            <th class="px-6 py-4 text-nowrap border-b border-gray-200">#</th>
                            <th class="px-6 py-4 text-nowrap border-b border-gray-200">Document</th>
                            <th class="px-6 py-4 text-nowrap border-b border-gray-200">Current Holder</th>
                            <th class="px-6 py-4 text-nowrap border-b border-gray-200">Status / Deadline</th>
                            <th class="px-6 py-4 text-center text-nowrap border-b border-gray-200">Actions</th>
                            <th class="px-6 py-4 text-right text-nowrap border-b border-gray-200">Date Sent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200" id="documentTableBody">
                        <?php if (empty($sent_docs)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-600">
                                    <i class="fas fa-paper-plane text-4xl mb-3 block opacity-40"></i>
                                    No active documents being tracked.
                                </td>
                            </tr>
                        <?php else: 
                            $index = 0;
                            foreach ($sent_docs as $doc): 
                            $is_overdue = ($doc['status'] == 'pending' && $doc['due_date'] && $doc['due_date'] < $today);
                            $row_number = $offset + $index + 1;
                            $index++;
                        ?>
                            <tr class="track-row transition <?php echo $is_overdue ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-slate-50'; ?>">
                                <td class="px-6 py-4 font-black text-gray-500 text-sm"><?php echo $row_number; ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900 search-target"><?php echo htmlspecialchars($doc['document_title'] ?: $doc['filename']); ?></div>
                                    <div class="text-xs text-gray-600 font-medium italic search-target"><?php echo $doc['filename']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-800 search-target"><?php echo htmlspecialchars($doc['receiver_name']); ?></div>
                                    <div class="text-[10px] text-blue-700 font-black uppercase"><?php echo htmlspecialchars($doc['receiver_office']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($is_overdue): ?>
                                        <div class="flex flex-col gap-1">
                                            <div class="inline-flex items-center gap-1.5 bg-red-200 text-red-900 px-3 py-1 rounded-full status-pill animate-urgent w-fit search-target border border-red-300">
                                                <i class="fas fa-exclamation-circle text-[10px]"></i> Overdue
                                            </div>
                                            <button onclick="sendReminder(<?= $doc['id'] ?>, '<?= addslashes($doc['receiver_name']) ?>')" class="text-[10px] font-black text-blue-800 hover:text-blue-900 underline text-left ml-1">
                                                <i class="fas fa-bell mr-1"></i> Remind Holder
                                            </button>
                                        </div>
                                    <?php else: 
                                        $statusClass = 'bg-yellow-200 text-yellow-900 border-yellow-300';
                                        $icon = 'fa-clock';
                                        if ($doc['status'] == 'approved') { $statusClass = 'bg-green-200 text-green-900 border-green-300'; $icon = 'fa-check-circle'; }
                                        elseif ($doc['status'] == 'rejected') { $statusClass = 'bg-red-200 text-red-900 border-red-300'; $icon = 'fa-times-circle'; }
                                    ?>
                                        <span class="px-3 py-1 rounded-full status-pill flex items-center gap-1.5 w-fit border <?php echo $statusClass; ?> search-target">
                                            <i class="fas <?php echo $icon; ?> text-[10px]"></i> <?php echo $doc['status']; ?>
                                        </span>
                                        <?php if($doc['status'] == 'pending'): ?>
                                            <div class="text-[10px] text-gray-700 mt-1 font-bold italic">
                                                Due: <?php echo $doc['due_date'] ? date('M d', strtotime($doc['due_date'])) : 'No deadline'; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="quickView('uploads/<?= $doc['filename'] ?>', '<?= addslashes($doc['document_title'] ?: $doc['filename']) ?>')" class="text-emerald-800 hover:text-emerald-950 bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-lg text-xs font-bold transition" title="Quick View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if($doc['status'] == 'pending'): ?>
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($doc)) ?>)" class="text-amber-800 hover:text-amber-950 bg-amber-100 border border-amber-200 px-3 py-1.5 rounded-lg text-xs font-bold transition" title="Edit Pending Document">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="viewHistory(<?php echo $doc['id']; ?>)" class="text-blue-800 hover:text-blue-950 bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg text-xs font-bold transition" title="View Trail">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <form action="archive_action.php" method="POST" class="inline archive-form">
                                            <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                            <button type="button" onclick="confirmArchive(this)" class="text-gray-700 hover:text-amber-900 bg-gray-100 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold transition" title="Archive">
                                                <i class="fas fa-archive"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm text-gray-900 font-bold"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></div>
                                    <div class="text-[10px] text-gray-600 font-black uppercase"><?php echo date('h:i A', strtotime($doc['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-slate-50/50">
                    <div class="text-sm text-gray-700">
                        Showing <span class="font-bold"><?php echo min($offset + 1, $total_records); ?></span> to <span class="font-bold"><?php echo min($offset + $limit, $total_records); ?></span> of <span class="font-bold"><?php echo $total_records; ?></span> entries
                    </div>
                    <div class="flex gap-2">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" 
                               class="px-4 py-2 border rounded-lg text-sm font-bold transition shadow-sm 
                               <?php echo $i == $page ? 'border-blue-700 bg-blue-700 text-white shadow-blue-200' : 'border-slate-300 bg-white text-gray-700 hover:bg-slate-100'; ?>">
                                 <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="viewModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-[60] p-4">
        <div class="bg-white rounded-2xl w-full max-w-5xl h-[90vh] overflow-hidden flex flex-col shadow-2xl">
            <div class="p-4 border-b flex justify-between items-center bg-slate-100">
                <h2 id="viewModalTitle" class="text-lg font-bold text-gray-900 truncate pr-4">Document Preview</h2>
                <button onclick="closeViewModal()" class="text-gray-600 hover:text-black text-2xl font-bold">&times;</button>
            </div>
            <div id="viewModalContent" class="flex-1 bg-slate-200 overflow-auto flex items-center justify-center">
            </div>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden flex flex-col border border-gray-300">
            <div class="p-5 border-b flex justify-between items-center bg-slate-100">
                <h2 class="text-lg font-bold text-gray-900"><i class="fas fa-pen-to-square mr-2 text-amber-700"></i>Edit Document</h2>
                <button onclick="closeEditModal()" class="text-gray-600 hover:text-black text-2xl font-bold">&times;</button>
            </div>
            <form id="editForm" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="doc_id" id="edit_doc_id">
                
                <div>
                    <label class="block text-xs font-black uppercase text-gray-700 mb-1">Document Title</label>
                    <input type="text" name="title" id="edit_title" required class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-bold text-gray-900">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-gray-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" id="edit_due_date" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm font-bold text-gray-900">
                </div>

                <div>
                    <label class="block text-xs font-black uppercase text-gray-700 mb-1">Replace File (Optional)</label>
                    <div class="relative group">
                        <input type="file" name="document" id="edit_file" class="hidden" onchange="updateFileNameDisplay(this)">
                        <label for="edit_file" class="flex items-center justify-center gap-2 w-full p-4 border-2 border-dashed border-slate-300 rounded-xl hover:border-blue-500 hover:bg-blue-50 cursor-pointer transition group">
                            <i class="fas fa-cloud-upload-alt text-gray-600 group-hover:text-blue-600"></i>
                            <span id="file-name-label" class="text-sm text-gray-700 font-bold">Choose new file...</span>
                        </label>
                    </div>
                    <p class="text-[10px] text-gray-600 mt-1 italic font-bold">Leave empty to keep the current file.</p>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2.5 rounded-xl bg-slate-200 text-gray-800 font-black hover:bg-slate-300 transition">Cancel</button>
                    <button type="submit" id="saveBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-blue-700 text-white font-black hover:bg-blue-800 transition shadow-lg">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="historyModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg max-h-[85vh] overflow-hidden flex flex-col shadow-2xl border border-gray-300">
            <div class="p-5 border-b flex justify-between items-center bg-slate-100">
                <h2 class="text-lg font-bold text-gray-900"><i class="fas fa-route mr-2 text-blue-700"></i>Document Trail </h2>
                <button onclick="closeHistoryModal()" class="text-gray-600 hover:text-black text-2xl font-bold">&times;</button>
            </div>
            <div id="historyContent" class="p-6 overflow-y-auto bg-white"></div>
        </div>
    </div>

    <script>
        // QUICK VIEW LOGIC
        function quickView(filePath, title) {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewModalContent');
            const modalTitle = document.getElementById('viewModalTitle');
            
            modalTitle.innerText = title;
            const ext = filePath.split('.').pop().toLowerCase();
            
            if (ext === 'pdf') {
                content.innerHTML = `<iframe src="${filePath}#toolbar=0" class="w-full h-full border-none"></iframe>`;
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                content.innerHTML = `<img src="${filePath}" class="max-w-full max-h-full object-contain">`;
            } else {
                content.innerHTML = `
                    <div class="text-center p-10">
                        <i class="fas fa-file-alt text-6xl text-slate-400 mb-4"></i>
                        <p class="font-bold text-gray-600">Preview not available for .${ext} files.</p>
                        <a href="${filePath}" download class="mt-4 inline-block bg-blue-700 text-white px-6 py-2 rounded-lg font-black uppercase text-xs">Download to View</a>
                    </div>`;
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
            document.getElementById('viewModal').classList.remove('flex');
            document.getElementById('viewModalContent').innerHTML = "";
        }

        function openEditModal(doc) {
            document.getElementById('edit_doc_id').value = doc.id;
            document.getElementById('edit_title').value = doc.document_title || doc.filename;
            document.getElementById('edit_due_date').value = doc.due_date || '';
            document.getElementById('file-name-label').innerText = "Choose new file...";
            document.getElementById('edit_file').value = "";
            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        function updateFileNameDisplay(input) {
            const label = document.getElementById('file-name-label');
            label.innerText = input.files.length > 0 ? input.files[0].name : "Choose new file...";
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';
            const formData = new FormData(e.target);
            try {
                const response = await fetch('update_document.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Updated!', showConfirmButton: false, timer: 1500 });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.message || 'Update failed', 'error');
                    btn.disabled = false; btn.innerHTML = originalText;
                }
            } catch (err) {
                Swal.fire('Error', 'Connection error', 'error');
                btn.disabled = false; btn.innerHTML = originalText;
            }
        });

        function applyFilters() {
            const searchTerm = document.getElementById('trackSearch').value.toLowerCase();
            const activeBtn = document.querySelector('.filter-btn.active-filter').innerText.toLowerCase();
            const rows = document.querySelectorAll('.track-row');
            rows.forEach(row => {
                const targets = row.querySelectorAll('.search-target');
                let textMatch = false;
                let statusMatch = (activeBtn === 'all');
                targets.forEach(t => {
                    const content = t.innerText.toLowerCase();
                    if (content.includes(searchTerm)) textMatch = true;
                    if (activeBtn !== 'all' && content.includes(activeBtn)) statusMatch = true;
                });
                row.style.display = (textMatch && statusMatch) ? "" : "none";
            });
        }

        document.getElementById('trackSearch').addEventListener('input', applyFilters);

        function filterByStatus(status) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active-filter');
                if(btn.innerText.toLowerCase() === status) btn.classList.add('active-filter');
            });
            applyFilters();
        }

        async function sendReminder(docId, holderName) {
            const result = await Swal.fire({
                title: 'Send Reminder?',
                text: `Notify ${holderName} that this document is overdue?`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#1d4ed8',
                confirmButtonText: 'Yes, Nudge Them'
            });
            if (result.isConfirmed) {
                try {
                    const response = await fetch('send_notification.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `doc_id=${docId}&type=reminder`
                    });
                    const data = await response.json();
                    if (data.success) { Swal.fire('Sent!', 'Reminder sent.', 'success'); }
                    else { Swal.fire('Error', 'Failed to send.', 'error'); }
                } catch (e) { Swal.fire('Error', 'Connection failed.', 'error'); }
            }
        }

        function confirmArchive(button) {
            Swal.fire({
                title: 'Archive Document?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#1e40af',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, archive it!'
            }).then((result) => { if (result.isConfirmed) button.closest('form').submit(); })
        }

        async function viewHistory(docId) {
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('historyContent');
            modal.classList.remove('hidden'); modal.classList.add('flex');
            content.innerHTML = '<div class="text-center py-10"><i class="fas fa-circle-notch fa-spin text-3xl text-blue-500"></i></div>';
            try {
                const response = await fetch(`get_history.php?doc_id=${docId}`);
                const data = await response.json();
                if (data.length === 0) {
                    content.innerHTML = '<p class="text-center text-gray-800 font-bold py-10">No movement history found.</p>';
                    return;
                }
                let html = '<div class="relative space-y-6 before:absolute before:left-4 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">';
                data.forEach((step, index) => {
                    const isForwarded = step.status === 'forwarded';
                    const downloadBtn = step.filename ? `<a href="uploads/${step.filename}" download class="mt-2 inline-flex items-center gap-1 text-[10px] bg-white border border-slate-300 px-2 py-1 rounded hover:bg-slate-100 text-gray-900 transition font-black shadow-sm"><i class="fas fa-download"></i> Download</a>` : '';
                    html += `<div class="relative pl-10">
                        <div class="absolute left-0 top-1 w-8 h-8 ${isForwarded ? 'bg-blue-700' : 'bg-emerald-700'} rounded-full border-4 border-white flex items-center justify-center text-white text-xs z-10 shadow-sm"><i class="fas ${isForwarded ? 'fa-share' : 'fa-check'}"></i></div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-[10px] font-black uppercase ${isForwarded ? 'text-blue-800' : 'text-emerald-800'}">${step.status}</span>
                                <span class="text-[10px] text-gray-700 font-black">${step.moved_at}</span>
                            </div>
                            <p class="text-sm text-gray-900 font-bold">${step.sender_name} ${step.office_name ? `<span class="text-[10px] text-blue-600 font-black uppercase">(${step.office_name})</span>` : ''}</p>
                            <p class="text-xs text-gray-700">To: <span class="font-black text-gray-900">${step.receiver_name || 'N/A'}</span> ${step.receiver_office ? `<span class="text-[10px] text-blue-600 font-black uppercase">(${step.receiver_office})</span>` : ''}</p>
                            ${step.remarks ? `<div class="mt-3 text-xs italic text-gray-900 font-medium bg-white p-2 rounded border border-slate-200 shadow-sm">"${step.remarks}"</div>` : ''}
                            ${downloadBtn}
                        </div>
                    </div>`;
                });
                html += '</div>';
                content.innerHTML = html;
            } catch (e) { content.innerHTML = '<p class="text-red-700 font-bold text-center py-10">Failed to load history.</p>'; }
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').classList.add('hidden');
            document.getElementById('historyModal').classList.remove('flex');
        }

        async function syncBadge() {
            try {
                const response = await fetch('get_notifications.php');
                const data = await response.json();
                const badge = document.getElementById('sidebar-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.innerText = data.count; badge.classList.remove('hidden');
                    } else { badge.classList.add('hidden'); }
                }
            } catch (e) { console.error(e); }
        }
        setInterval(syncBadge, 10000);
    </script>
</body>
</html>