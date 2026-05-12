<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// 1. Fetch User & Office Data
$stmt = $pdo->prepare("
    SELECT u.*, o.office_name 
    FROM \"user\" u 
    LEFT JOIN office o ON u.office_id = o.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) { logout(); exit(); }

$user_name = $user['name'];
$office_name = $user['office_name'] ?? 'General Office';

// 2. Notification Fetch (Incoming + Status Updates)
try {
    $incoming_stmt = $pdo->prepare("SELECT COUNT(*) FROM \"document\" WHERE receiver_user_id = ? AND status = 'pending' AND is_archived = FALSE");
    $incoming_stmt->execute([$user_id]);
    $incoming_count = $incoming_stmt->fetchColumn();

    $sender_notif_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM \"document\" 
        WHERE uploader_id = ? AND status IN ('approved', 'rejected') AND seen_by_sender = false AND is_archived = FALSE
    ");
    $sender_notif_stmt->execute([$user_id]);
    $sender_action_count = $sender_notif_stmt->fetchColumn();

    $notification_count = $incoming_count + $sender_action_count;
} catch (PDOException $e) { $notification_count = 0; }

// 3. Stats & Recent Activity
try {
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = 'pending') as pending,
            COUNT(*) FILTER (WHERE status = 'approved') as approved,
            COUNT(*) FILTER (WHERE status = 'rejected') as rejected
        FROM \"document\" 
        WHERE uploader_id = ? AND is_archived = FALSE
    ");
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();

    // UPDATED: Joined with user table to get receiver name
    $activity_stmt = $pdo->prepare("
        SELECT d.id, d.document_title, d.filename, d.status, d.created_at, u.name as receiver_name 
        FROM \"document\" d
        LEFT JOIN \"user\" u ON d.receiver_user_id = u.id
        WHERE d.uploader_id = ? AND d.is_archived = FALSE 
        ORDER BY d.created_at DESC LIMIT 5
    ");
    $activity_stmt->execute([$user_id]);
    $recent_docs = $activity_stmt->fetchAll();
} catch (PDOException $e) {
    $stats = ['total'=>0, 'pending'=>0, 'approved'=>0, 'rejected'=>0];
    $recent_docs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-gradient { background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%); }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .row-fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex overflow-x-hidden">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 md:ml-64 p-8 transition-all duration-300 overflow-y-auto">
        <div class="max-w-6xl mx-auto">
            
            <div id="global-notif-bar" class="mb-6 space-y-3 transition-all duration-500 <?php echo ($notification_count > 0) ? '' : 'hidden opacity-0'; ?>">
                <?php if ($incoming_count > 0): ?>
                <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-xl shadow-sm flex justify-between items-center">
                    <div class="flex items-center">
                        <i class="fas fa-file-import text-amber-500 text-lg"></i>
                        <p class="ml-3 text-sm text-amber-700">You have <span class="font-bold"><?php echo $incoming_count; ?></span> docs to process. <a href="inbox.php" class="underline font-bold ml-1">View Inbox</a></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($sender_action_count > 0): ?>
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-xl shadow-sm flex justify-between items-center">
                    <div class="flex items-center">
                        <i class="fas fa-bell text-blue-500 text-lg"></i>
                        <p class="ml-3 text-sm text-blue-700"><span class="font-bold"><?php echo $sender_action_count; ?></span> documents were updated. <a href="track.php" class="underline font-bold ml-1">Check Status</a></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <header class="flex justify-between items-start mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Hello, <?php echo htmlspecialchars($user_name); ?>!</h1>
                    <p class="text-gray-500 flex items-center gap-2 mt-1">
                        <i class="fas fa-building text-blue-500"></i> <?php echo htmlspecialchars($office_name); ?>
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-6 rounded-2xl card-shadow border-b-4 border-blue-500">
                    <p class="text-xs text-gray-400 uppercase font-black mb-1">Active Sent</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['total']; ?></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl card-shadow border-b-4 border-yellow-500">
                    <p class="text-xs text-gray-400 uppercase font-black mb-1">Pending</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['pending']; ?></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl card-shadow border-b-4 border-green-500">
                    <p class="text-xs text-gray-400 uppercase font-black mb-1">Approved</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['approved']; ?></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl card-shadow border-b-4 border-red-500">
                    <p class="text-xs text-gray-400 uppercase font-black mb-1">Rejected</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['rejected']; ?></h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white rounded-2xl card-shadow overflow-hidden">
                    <div class="p-6 border-b flex justify-between items-center">
                        <h2 class="font-bold text-gray-800">Recent Activity</h2>
                        <a href="track.php" class="text-blue-600 text-sm font-semibold hover:underline">View All</a>
                    </div>
                    <div class="p-6 space-y-4">
                        <?php if (empty($recent_docs)): ?>
                            <p class="text-center text-gray-400 py-4 italic">No recent active activity.</p>
                        <?php else: foreach ($recent_docs as $doc): ?>
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl hover:bg-slate-100 transition row-fade-in">
                                <div class="flex items-center gap-4 min-w-0">
                                    <div class="bg-blue-100 text-blue-600 w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-lg">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="truncate">
                                        <p class="font-bold text-gray-700 text-sm truncate">
                                            <?php echo htmlspecialchars($doc['document_title'] ?: $doc['filename']); ?>
                                        </p>
                                        <p class="text-[10px] text-gray-500 font-medium">
                                            <i class="fas fa-paper-plane mr-1 text-[9px]"></i> Sent to: <span class="text-blue-600"><?php echo htmlspecialchars($doc['receiver_name'] ?? 'Unknown'); ?></span>
                                            <span class="mx-1 text-gray-300">•</span>
                                            <span class="text-gray-400 uppercase"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></span>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <a href="uploads/<?php echo $doc['filename']; ?>" target="_blank" 
                                       class="p-2 text-gray-400 hover:text-blue-600 hover:bg-white rounded-lg transition"
                                       title="Quick View">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>

                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase 
                                        <?php echo ($doc['status'] == 'approved') ? 'bg-green-100 text-green-700' : 
                                                    (($doc['status'] == 'pending') ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                        <?php echo $doc['status']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl card-shadow p-8 flex flex-col items-center text-center h-fit">
                    <button onclick="toggleProfile()" class="group relative focus:outline-none mb-4">
                        <div class="w-24 h-24 bg-blue-600 rounded-full flex items-center justify-center text-white text-4xl font-bold overflow-hidden border-4 border-white shadow-lg transition transform group-hover:scale-105">
                            <?php if (!empty($user['profile_pix'])): ?>
                                <img src="uploads/profiles/<?php echo $user['profile_pix']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo substr($user_name, 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <div class="absolute bottom-0 right-0 bg-blue-500 text-white p-2 rounded-full border-2 border-white shadow-md group-hover:bg-blue-600 transition">
                            <i class="fas fa-cog text-[10px]"></i>
                        </div>
                    </button>
                    <h3 class="font-bold text-xl text-gray-800"><?php echo htmlspecialchars($user_name); ?></h3>
                    <p class="text-blue-600 text-sm mb-6 font-medium"><?php echo htmlspecialchars($user['position'] ?? 'Staff'); ?></p>
                    <button onclick="location.href='upload.php'" class="w-full bg-slate-800 text-white py-3.5 rounded-xl font-bold hover:bg-black transition shadow-lg shadow-slate-200">
                        Upload New Document
                    </button>
                </div>
            </div>
            
        </div> 
    </main>

    <div id="profile-drawer" class="fixed inset-y-0 right-0 w-96 bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-50 overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center bg-slate-50 sticky top-0 z-10">
            <h2 class="font-bold text-gray-800 text-lg">Account Settings</h2>
            <button onclick="toggleProfile()" class="text-gray-400 hover:text-black transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
            <div class="flex flex-col items-center">
                <div class="relative group">
                    <img src="<?php echo !empty($user['profile_pix']) ? 'uploads/profiles/'.$user['profile_pix'] : 'https://ui-avatars.com/api/?name='.urlencode($user_name).'&background=1e40af&color=fff'; ?>" 
                         id="preview-pix" class="w-28 h-28 rounded-full object-cover border-4 border-blue-50 shadow-md">
                    <label for="pix-input" class="absolute bottom-0 right-0 bg-blue-600 text-white p-2.5 rounded-full cursor-pointer shadow-lg hover:bg-blue-700 transition">
                        <i class="fas fa-camera text-xs"></i>
                        <input type="file" id="pix-input" name="profile_pix" class="hidden" accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <p class="text-[10px] text-gray-400 uppercase font-bold mt-3 tracking-widest">Change Photo</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Position</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">E-Signature (PNG/JPG)</label>
                    <div class="relative w-full h-32 bg-slate-50 border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center overflow-hidden hover:border-blue-400 transition">
                        <?php if (!empty($user['signature_path'])): ?>
                            <img src="uploads/signatures/<?php echo $user['signature_path']; ?>" id="preview-sig" class="max-h-full object-contain">
                        <?php else: ?>
                            <img id="preview-sig" class="max-h-full object-contain hidden">
                            <i id="sig-icon" class="fas fa-pen-nib text-slate-300 text-2xl mb-2"></i>
                            <p id="sig-text" class="text-[10px] text-slate-400 font-bold uppercase">Upload Signature</p>
                        <?php endif; ?>
                        <input type="file" name="signature" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" onchange="previewSignature(this)">
                    </div>
                </div>
                
                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-gray-700 mb-3">Security</h4>
                    <input type="password" name="new_password" placeholder="New Password" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition mb-3">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold shadow-xl shadow-blue-100 hover:bg-blue-700 transition transform active:scale-[0.98]">
                Update Account
            </button>
        </form>
    </div>
    
    <div id="drawer-overlay" onclick="toggleProfile()" class="fixed inset-0 bg-black/40 backdrop-blur-[2px] z-40 hidden opacity-0 transition-opacity duration-300"></div>

    <script>
        function toggleProfile() {
            const drawer = document.getElementById('profile-drawer');
            const overlay = document.getElementById('drawer-overlay');
            if (drawer.classList.contains('translate-x-full')) {
                drawer.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.add('opacity-100'), 10);
            } else {
                drawer.classList.add('translate-x-full');
                overlay.classList.remove('opacity-100');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => document.getElementById('preview-pix').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }

        // NEW: Signature Preview Function
        function previewSignature(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.getElementById('preview-sig');
                    const icon = document.getElementById('sig-icon');
                    const txt = document.getElementById('sig-text');
                    
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    if(icon) icon.classList.add('hidden');
                    if(txt) txt.classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        let lastCount = <?php echo $notification_count; ?>;
        async function checkNotifications() {
            try {
                const res = await fetch('get_notifications.php');
                const data = await res.json();
                if (data.count > lastCount) {
                    Swal.fire({
                        toast: true,
                        position: 'bottom-end',
                        icon: 'info',
                        title: 'New Update!',
                        text: 'A document status was changed.',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true
                    });
                }
                lastCount = data.count;
                const badge = document.getElementById('sidebar-badge');
                if (badge) {
                    badge.innerText = data.count;
                    data.count > 0 ? badge.classList.remove('hidden') : badge.classList.add('hidden');
                }
            } catch (e) {}
        }
        setInterval(checkNotifications, 10000);

        if (window.location.search.includes('updated=success')) {
            Swal.fire({ icon: 'success', title: 'Profile Updated!', text: 'Your changes have been saved successfully.', confirmButtonColor: '#1e40af' });
        }
    </script>
</body>
</html>