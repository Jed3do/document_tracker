<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id']; // Retrieve office_id from session

// Fetch folders for this specific OFFICE and count documents inside each
try {
    $query = "SELECT f.*, COUNT(d.id) as doc_count 
              FROM folders f 
              LEFT JOIN document d ON f.id = d.folder_id 
              WHERE f.office_id = ?
              GROUP BY f.id 
              ORDER BY f.folder_name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$office_id]);
    $folders = $stmt->fetchAll();
} catch (PDOException $e) {
    $folders = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registry Vault | DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sidebar-container {
            width: 16rem;
            position: fixed;
            height: 100vh;
            background-color: #1e3a8a;
            z-index: 100;
        }

        @media (min-width: 768px) { 
            .main-content { margin-left: 16rem; } 
        }

        .folder-card:hover { transform: translateY(-5px); }
        
        .new-folder-dashed {
            border: 2px dashed #10b981;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-900">

    <div class="sidebar-container no-print">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <main class="flex-1 p-8 main-content transition-all duration-300">
        <header class="mb-10 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight uppercase italic">Registry Vault</h1>
                <p class="text-xs text-slate-500 font-bold tracking-widest uppercase">Manage & Browse Folders</p>
            </div>
            
            <button onclick="openFolderModal()" class="bg-[#059669] text-white px-6 py-3 rounded-full font-black text-xs hover:bg-emerald-700 transition shadow-lg shadow-emerald-100 uppercase flex items-center gap-2">
                <i class="fas fa-plus"></i> Create Folder
            </button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <div onclick="openFolderModal()" class="cursor-pointer new-folder-dashed p-10 rounded-3xl flex flex-col items-center justify-center text-emerald-600 hover:bg-emerald-50/50 transition-all duration-300 bg-white/50">
                <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mb-3">
                    <i class="fas fa-plus text-xl"></i>
                </div>
                <span class="font-black text-[11px] uppercase tracking-widest">New Folder</span>
            </div>

            <?php foreach ($folders as $folder): ?>
                <a href="records.php?type=filing&folder_id=<?= $folder['id'] ?>" 
                   class="folder-card group bg-white p-6 rounded-3xl border-2 border-slate-100 hover:border-blue-500 hover:shadow-xl transition-all duration-300 shadow-sm">
                    <div class="flex justify-between items-start mb-6">
                        <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <i class="fas fa-folder text-xl"></i>
                        </div>
                        <span class="bg-slate-100 text-slate-500 text-[10px] font-black px-3 py-1 rounded-full uppercase">
                            <?= $folder['doc_count'] ?> Files
                        </span>
                    </div>
                    <h3 class="font-black text-slate-800 uppercase tracking-tight truncate text-lg">
                        <?= htmlspecialchars($folder['folder_name']) ?>
                    </h3>
                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-widest group-hover:text-blue-500 transition-colors">
                        <?= htmlspecialchars($folder['location'] ?? 'NO LOCATION SET') ?>
                    </p>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="folderModal" class="fixed inset-0 bg-slate-900/80 hidden items-center justify-center z-[110] backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-8 transform transition-all">
            <h2 class="text-2xl font-black text-slate-900 mb-2 uppercase italic tracking-tighter">New Vault Folder</h2>
            <p class="text-xs text-slate-500 mb-6 font-bold uppercase tracking-widest border-l-4 border-emerald-500 pl-3">Vault Security Entry</p>
            
            <form id="folderForm" onsubmit="handleFolderCreate(event)">
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-1">Folder Name</label>
                        <input type="text" id="newFolderName" required placeholder="e.g., Accounting 2024" 
                            class="w-full px-5 py-4 bg-slate-100 border-2 border-transparent focus:border-blue-500 rounded-2xl outline-none font-bold text-sm">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-1">Physical Location</label>
                        <input type="text" id="newFolderLocation" placeholder="e.g., Cabinet 3, Row A" 
                            class="w-full px-5 py-4 bg-slate-100 border-2 border-transparent focus:border-blue-500 rounded-2xl outline-none font-bold text-sm">
                    </div>
                </div>
                <div class="flex gap-3 mt-8">
                    <button type="button" onclick="closeFolderModal()" class="flex-1 px-6 py-4 rounded-2xl font-black text-xs text-slate-400 hover:bg-slate-100 transition uppercase">Cancel</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-4 rounded-2xl font-black text-xs hover:bg-blue-700 shadow-lg shadow-blue-200 transition uppercase">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openFolderModal() { document.getElementById('folderModal').classList.replace('hidden', 'flex'); }
        function closeFolderModal() { document.getElementById('folderModal').classList.replace('flex', 'hidden'); }

        async function handleFolderCreate(e) {
            e.preventDefault();
            const folderName = document.getElementById('newFolderName').value;
            const folderLocation = document.getElementById('newFolderLocation').value;
            
            try {
                const response = await fetch('create_folder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        folder_name: folderName,
                        location: folderLocation 
                    })
                });
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({ title: 'CREATED', icon: 'success', confirmButtonColor: '#2563eb', customClass: { popup: 'rounded-3xl' }})
                        .then(() => location.reload());
                } else {
                    Swal.fire({ title: 'Error', text: result.message, icon: 'error' });
                }
            } catch (error) {
                Swal.fire('Error', 'Vault connection failed', 'error');
            }
            closeFolderModal();
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('folderModal')) closeFolderModal();
        }
    </script>
</body>
</html>