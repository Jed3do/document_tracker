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
$filter = $_GET['filter'] ?? 'all'; 

// Pagination setup
$results_per_page = 10; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $results_per_page;

// Fetch offices for the forward modal
$offices_stmt = $pdo->query("SELECT * FROM office ORDER BY office_name ASC");
$offices = $offices_stmt->fetchAll();

try {
    // 1. Get total count for pagination links
    $count_query = "SELECT COUNT(*) FROM document d 
                    JOIN \"user\" u ON d.uploader_id = u.id
                    WHERE d.receiver_user_id = ? AND d.status = 'pending' AND d.is_archived = FALSE";
    
    if ($filter === 'overdue') {
        $count_query .= " AND d.due_date < '$today'";
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$user_id]);
    $total_docs = $count_stmt->fetchColumn();
    $total_pages = ceil($total_docs / $results_per_page);

    // 2. Fetch only the 10 rows for the current page
    $query = "
        SELECT 
            d.id, 
            d.document_title, 
            d.filename, 
            d.remarks, 
            d.description, 
            d.due_date, 
            d.created_at, 
            u.name as uploader_name, 
            u.profile_pix, 
            o.office_name as sender_office
        FROM document d
        JOIN \"user\" u ON d.uploader_id = u.id
        LEFT JOIN office o ON u.office_id = o.id
        WHERE d.receiver_user_id = ? AND d.status = 'pending' AND d.is_archived = FALSE
    ";

    if ($filter === 'overdue') {
        $query .= " AND d.due_date < '$today'";
    }

    $query .= " ORDER BY d.updated_at DESC LIMIT $results_per_page OFFSET $offset";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $inbox_docs = $stmt->fetchAll();

} catch (PDOException $e) {
    $inbox_docs = [];
    $total_docs = 0;
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fast-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .animate-urgent { animation: fast-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-950"> 
    <aside class="w-64 bg-[#1e3a8a] text-white min-h-screen flex-shrink-0 shadow-xl">
        <?php include 'includes/sidebar.php'; ?>
    </aside>

    <main class="flex-1 p-8 overflow-y-auto">
        <header class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Incoming Documents</h1> 
                <p class="text-slate-600 font-medium">Review, forward, or update documents before routing.</p> 
            </div>
            
            <div class="flex bg-white p-1 rounded-xl shadow-sm border border-gray-300">
                <a href="inbox.php?filter=all" class="px-4 py-2 rounded-lg text-xs font-black uppercase transition <?php echo $filter !== 'overdue' ? 'bg-blue-700 text-white shadow-md' : 'text-slate-600 hover:text-slate-900'; ?>">All</a>
                <a href="inbox.php?filter=overdue" class="px-4 py-2 rounded-lg text-xs font-black uppercase transition <?php echo $filter === 'overdue' ? 'bg-red-700 text-white shadow-md' : 'text-red-600 hover:text-red-800'; ?>">
                    <i class="fas fa-clock mr-1"></i> Overdue
                </a>
            </div>
        </header>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-100 text-slate-700 text-xs uppercase font-black"> 
                    <tr>
                        <th class="px-6 py-4 border-b border-gray-200 text-nowrap">#</th> <th class="px-6 py-4 border-b border-gray-200">UPLOADER</th>
                        <th class="px-6 py-4 border-b border-gray-200">Document Details</th>
                        <th class="px-6 py-4 border-b border-gray-200">Description</th> 
                        <th class="px-6 py-4 border-b border-gray-200">Remarks</th> 
                        <th class="px-6 py-4 border-b border-gray-200">Deadline Status</th>
                        <th class="px-6 py-4 border-b border-gray-200 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($inbox_docs)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-600 font-bold italic">Inbox is empty.</td></tr>
                    <?php else: 
                        $index = 0; // Initialize index counter
                        foreach ($inbox_docs as $doc): 
                        
                        $is_late = ($doc['due_date'] && $doc['due_date'] < $today);
                        $row_number = $offset + $index + 1; // Calculate row number
                        $index++; // Increment index counter
                        
                        $doc_json = htmlspecialchars(json_encode([
                            'title' => $doc['document_title'] ?: $doc['filename'],
                            'description' => $doc['description'] ?: 'No description provided.',
                            'remarks' => $doc['remarks'] ?: 'No remarks provided.',
                            'uploader' => $doc['uploader_name'],
                            'office' => $doc['sender_office'],
                            'date' => date('M d, Y', strtotime($doc['created_at'])),
                            'filename' => $doc['filename']
                        ]));
                    ?>
                        <tr class="transition <?php echo $is_late ? 'bg-red-50/70 hover:bg-red-100' : 'hover:bg-slate-50'; ?>">
                            <td class="px-6 py-4 font-black text-slate-500 text-sm">
                                <?php echo $row_number; ?>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex-shrink-0 bg-blue-100 text-blue-800 flex items-center justify-center font-bold text-xs uppercase overflow-hidden border border-gray-300">
                                        <?php if (!empty($doc['profile_pix']) && file_exists('uploads/profiles/' . $doc['profile_pix'])): ?>
                                            <img src="uploads/profiles/<?php echo $doc['profile_pix']; ?>" class="w-full h-full object-cover">
                                        <?php else: 
                                            $names = explode(" ", $doc['uploader_name']);
                                            echo substr($names[0], 0, 1) . (count($names) > 1 ? substr(end($names), 0, 1) : "");
                                        endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($doc['uploader_name']); ?></div>
                                        <div class="text-xs text-blue-700 font-bold"><?php echo htmlspecialchars($doc['sender_office']); ?></div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-900 truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($doc['document_title'] ?: $doc['filename']); ?>
                                </div>
                                <div class="text-[10px] text-slate-600 mt-1 font-bold"> 
                                    <i class="far fa-calendar-alt mr-1"></i><?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-xs text-slate-700 line-clamp-2 max-w-xs font-medium">
                                    <?php echo !empty($doc['description']) ? htmlspecialchars($doc['description']) : '<span class="italic text-slate-400 font-normal">No description</span>'; ?>
                                </p>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-xs text-slate-700 line-clamp-2 max-w-xs font-medium">
                                    <?php echo !empty($doc['remarks']) ? htmlspecialchars($doc['remarks']) : '<span class="italic text-slate-400 font-normal">No remarks</span>'; ?>
                                </p>
                            </td>

                            <td class="px-6 py-4">
                                <?php if ($is_late): ?>
                                    <div class="flex items-center gap-2 text-red-700 animate-urgent">
                                        <i class="fas fa-exclamation-circle text-lg"></i>
                                        <div>
                                            <div class="text-xs font-black uppercase tracking-tighter">Overdue</div>
                                            <div class="text-[10px] font-black"><?php echo date('M d', strtotime($doc['due_date'])); ?></div>
                                        </div>
                                    </div>
                                <?php elseif ($doc['due_date']): ?>
                                    <div class="text-slate-700">
                                        <div class="text-[10px] font-black uppercase text-slate-500">Due Date</div>
                                        <div class="text-xs font-bold"><?php echo date('M d, Y', strtotime($doc['due_date'])); ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase italic">No Deadline</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick='showDetails(<?php echo $doc_json; ?>)' class="p-2 text-slate-600 hover:bg-slate-200 rounded-lg transition" title="View Details"><i class="fas fa-info-circle"></i></button>
                                    <button onclick="viewDocument('<?php echo $doc['filename']; ?>')" class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition" title="Open File"><i class="fas fa-external-link-alt"></i></button>
                                    <button onclick="openForwardModal(<?php echo $doc['id']; ?>)" class="p-2 text-amber-600 hover:bg-amber-100 rounded-lg transition" title="Forward"><i class="fas fa-share"></i></button>
                                    <button onclick="processAction(<?php echo $doc['id']; ?>, 'approved')" class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition" title="Approve"><i class="fas fa-check-circle"></i></button>
                                    <button onclick="processAction(<?php echo $doc['id']; ?>, 'rejected')" class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="Reject"><i class="fas fa-times-circle"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-slate-50/50">
                <div class="text-sm text-slate-700">
                    Showing <span class="font-bold"><?php echo min($offset + 1, $total_docs); ?></span> to <span class="font-bold"><?php echo min($offset + $results_per_page, $total_docs); ?></span> of <span class="font-bold"><?php echo $total_docs; ?></span> entries
                </div>
                <div class="flex gap-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" 
                           class="px-4 py-2 border rounded-lg text-sm font-bold transition shadow-sm 
                           <?php echo $i == $current_page ? 'border-blue-700 bg-blue-700 text-white shadow-blue-200' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="detailsModal" class="fixed inset-0 bg-slate-900/70 hidden items-center justify-center z-[60] backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="p-6 border-b flex justify-between items-center">
                <h2 id="modal_title" class="text-xl font-bold text-slate-900 truncate pr-4">Document Details</h2>
                <button onclick="closeDetailsModal()" class="text-slate-600 hover:text-slate-900 transition text-2xl">&times;</button>
            </div>
            <div class="p-8 overflow-y-auto flex-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-600 mb-1 tracking-widest">Description</label>
                        <p id="modal_description" class="text-slate-800 text-sm font-medium mb-4 italic"></p>

                        <label class="block text-[10px] font-black uppercase text-slate-600 mb-1 tracking-widest">Remarks</label>
                        <p id="modal_remarks" class="text-slate-800 leading-relaxed text-sm font-medium whitespace-pre-line bg-slate-50 p-4 rounded-xl border border-slate-200 min-h-[80px]"></p>
                        
                        <div class="mt-6 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center"><i class="fas fa-user text-xs"></i></div>
                                <div>
                                    <div class="text-[10px] font-black text-slate-600 uppercase">Sender</div>
                                    <div id="modal_sender" class="text-sm font-bold text-slate-900"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-800 flex items-center justify-center"><i class="fas fa-building text-xs"></i></div>
                                <div>
                                    <div class="text-[10px] font-black text-slate-600 uppercase">Office</div>
                                    <div id="modal_office" class="text-sm font-bold text-slate-900"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-4">
                        <label class="block text-[10px] font-black uppercase text-slate-600 tracking-widest">Document Preview</label>
                        <div id="preview_container" class="aspect-[3/4] bg-slate-100 rounded-xl border-2 border-dashed border-slate-300 flex items-center justify-center text-slate-600 overflow-hidden"></div>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
                <button onclick="closeDetailsModal()" class="px-6 py-2.5 rounded-xl font-black text-slate-700 hover:bg-slate-200 transition">Close</button>
                <a id="modal_open" href="#" target="_blank" class="px-4 py-2 bg-blue-700 text-white rounded-xl font-bold hover:bg-blue-800 transition flex items-center gap-2">
                    <i class="fas fa-external-link-alt"></i> Open
                </a>
                <button id="modal_print_btn" onclick="printDocument()" class="px-4 py-2 bg-slate-800 text-white rounded-xl font-bold hover:bg-slate-900 transition flex items-center gap-2">
                    <i class="fas fa-print"></i> Print
                </button>
                <a id="modal_download" href="#" download class="px-4 py-2 bg-emerald-700 text-white rounded-xl font-bold hover:bg-emerald-800 transition flex items-center gap-2">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>

    <div id="forwardModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
        <div class="bg-white p-8 rounded-2xl w-full max-w-md shadow-2xl">
            <h2 class="text-xl font-bold mb-4 text-slate-900">Forward & Update</h2>
            <input type="hidden" id="forward_doc_id">
            <div class="space-y-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-300">
                    <label class="block text-xs font-black uppercase text-slate-700 mb-2">Update Document? (Optional)</label>
                    <input type="file" id="forwardNewFile" class="block w-full text-xs text-slate-700 font-bold file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-100 file:text-blue-800 hover:file:bg-blue-200 cursor-pointer"/>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-600 mb-1">Target Office</label>
                    <select id="forwardOfficeSelect" onchange="fetchUsersForForward(this.value)" class="w-full p-3 border-gray-300 border rounded-xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-bold text-slate-800">
                        <option value="">Select Office...</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?= $office['id'] ?>"><?= htmlspecialchars($office['office_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-600 mb-1">Recipient</label>
                    <select id="forwardUserSelect" disabled class="w-full p-3 border-gray-300 border rounded-xl outline-none bg-gray-100 text-sm font-bold text-slate-800">
                        <option value="">Select Person...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase text-slate-600 mb-1">Remarks</label>
                    <textarea id="forwardRemarks" rows="2" class="w-full p-3 border-gray-300 border rounded-xl outline-none focus:ring-2 focus:ring-blue-500 text-sm font-medium text-slate-800" placeholder="Add instructions..."></textarea>
                </div>
                <div class="flex gap-3 pt-4">
                    <button onclick="closeForwardModal()" class="flex-1 py-3 border border-gray-300 rounded-xl font-black text-slate-700 hover:bg-gray-100 transition">Cancel</button>
                    <button onclick="submitForward()" class="flex-1 py-3 bg-blue-700 text-white rounded-xl font-black hover:bg-blue-800 transition shadow-lg">Forward Now</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFilePath = '';

        function showDetails(data) {
            document.getElementById('modal_title').innerText = data.title;
            document.getElementById('modal_description').innerText = data.description;
            document.getElementById('modal_remarks').innerText = data.remarks;
            document.getElementById('modal_sender').innerText = data.uploader;
            document.getElementById('modal_office').innerText = data.office;
            
            currentFilePath = 'uploads/' + encodeURIComponent(data.filename);
            document.getElementById('modal_open').href = currentFilePath;
            
            const downloadBtn = document.getElementById('modal_download');
            downloadBtn.href = currentFilePath;
            downloadBtn.setAttribute('download', data.title);

            const container = document.getElementById('preview_container');
            const ext = data.filename.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                container.innerHTML = `<img id="printable_image" src="uploads/${data.filename}" class="w-full h-full object-contain">`;
            } else if (ext === 'pdf') {
                container.innerHTML = `<embed src="uploads/${data.filename}#toolbar=0" type="application/pdf" width="100%" height="100%" class="rounded-xl">`;
            } else {
                container.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-file-alt text-4xl mb-2 opacity-40 text-slate-900"></i>
                        <p class="text-[10px] uppercase font-black tracking-widest text-slate-800">No Preview Available</p>
                    </div>`;
            }

            document.getElementById('detailsModal').classList.remove('hidden');
            document.getElementById('detailsModal').classList.add('flex');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
            document.getElementById('detailsModal').classList.remove('flex');
        }

        function printDocument() {
            if (!currentFilePath) return;
            const printFrame = document.createElement('iframe');
            printFrame.style.position = 'fixed'; printFrame.style.right = '0'; printFrame.style.bottom = '0';
            printFrame.style.width = '0'; printFrame.style.height = '0'; printFrame.style.border = '0';
            printFrame.src = currentFilePath;
            document.body.appendChild(printFrame);
            printFrame.onload = function() {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
                setTimeout(() => { document.body.removeChild(printFrame); }, 1000);
            };
        }

        async function triggerNotification(docId, type) {
            try {
                await fetch('send_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `doc_id=${docId}&type=${type}`
                });
            } catch (e) { console.error("Notification failed", e); }
        }

        function openForwardModal(docId) {
            document.getElementById('forward_doc_id').value = docId;
            document.getElementById('forwardNewFile').value = ""; 
            document.getElementById('forwardModal').classList.remove('hidden');
            document.getElementById('forwardModal').classList.add('flex');
        }

        function closeForwardModal() {
            document.getElementById('forwardModal').classList.add('hidden');
            document.getElementById('forwardModal').classList.remove('flex');
        }

        async function fetchUsersForForward(officeId) {
            const userSelect = document.getElementById('forwardUserSelect');
            if (!officeId) {
                userSelect.disabled = true; userSelect.classList.add('bg-gray-100');
                return;
            }
            userSelect.innerHTML = '<option>Loading...</option>';
            try {
                const res = await fetch(`get_users_by_office.php?office_id=${officeId}`);
                const users = await res.json();
                userSelect.innerHTML = '<option value="">Select Person...</option>';
                users.forEach(u => userSelect.innerHTML += `<option value="${u.id}">${u.name}</option>`);
                userSelect.disabled = false; userSelect.classList.remove('bg-gray-100');
            } catch (e) { console.error(e); }
        }

        async function submitForward() {
            const docId = document.getElementById('forward_doc_id').value;
            const officeId = document.getElementById('forwardOfficeSelect').value;
            const userId = document.getElementById('forwardUserSelect').value;
            const remarks = document.getElementById('forwardRemarks').value;
            const fileInput = document.getElementById('forwardNewFile');

            if (!userId) {
                Swal.fire('Recipient Required', 'Please select a recipient.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('doc_id', docId);
            formData.append('new_receiver_id', userId);
            formData.append('new_office_id', officeId);
            formData.append('remarks', remarks);
            formData.append('action', 'forward');
            
            if (fileInput.files.length > 0) {
                formData.append('new_document', fileInput.files[0]);
            }

            try {
                Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                const res = await fetch('process_document.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    await triggerNotification(docId, 'forwarded');
                    Swal.fire('Success', 'Document forwarded successfully.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (e) { Swal.fire('Error', 'Connection failed.', 'error'); }
        }

        async function processAction(docId, status) {
            const result = await Swal.fire({
                title: 'Confirm Action',
                text: `Are you sure you want to mark this document as ${status}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: status === 'approved' ? '#059669' : '#dc2626'
            });

            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('doc_id', docId);
                formData.append('action', status);
                
                try {
                    const res = await fetch('process_document.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        await triggerNotification(docId, status);
                        location.reload();
                    } else {
                        Swal.fire('Error', data.message || 'Operation failed', 'error');
                    }
                } catch (e) { Swal.fire('Error', 'Connection failed.', 'error'); }
            }
        }

        function viewDocument(filename) {
            window.open('uploads/' + encodeURIComponent(filename), '_blank');
        }
    </script>
</body>
</html>