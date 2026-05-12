<?php
// Get the current page filename (e.g., 'dashboard.php')
$current_page = basename($_SERVER['PHP_SELF']);

// Function to check if a link is active
function isActive($page_name, $current_page) {
    return ($page_name === $current_page) 
        ? 'bg-white/10 border border-white/20' 
        : 'hover:bg-white/5 opacity-80 hover:opacity-100';
}
?>

<aside class="sidebar-gradient w-64 hidden md:flex flex-col text-white p-6 shadow-xl h-screen fixed top-0 left-0 overflow-y-auto">
    <div class="mb-10 flex items-center gap-3">
        <div class="bg-white rounded-full p-1 shadow-md flex items-center justify-center w-12 h-12 shrink-0">
            <img src="logo.jpg" alt="DocuTrack Logo" class="w-full h-full rounded-full object-cover">
        </div>
        <div class="flex flex-col">
            <span class="font-bold text-lg leading-tight tracking-tight">
                Document <span class="text-yellow-400">Tracker</span>
            </span>
            <span class="text-[10px] text-blue-200 uppercase tracking-widest font-bold">Secure Portal</span>
        </div>
    </div>

    <nav class="space-y-2 flex-1">
        <a href="dashboard.php" class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 <?php echo isActive('dashboard.php', $current_page); ?>">
            <i class="fas fa-th-large w-5"></i> 
            <span class="font-medium">Dashboard</span>
        </a>
        
        <a href="inbox.php" class="flex items-center justify-between p-3 rounded-xl transition-all duration-200 <?php echo isActive('inbox.php', $current_page); ?>">
            <div class="flex items-center gap-3">
                <i class="fas fa-bell w-5"></i> 
                <span class="font-medium">Inbox</span>
            </div>
            <span id="sidebar-badge" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo (isset($notification_count) && $notification_count > 0) ? '' : 'hidden'; ?>">
                <?php echo $notification_count ?? 0; ?>
            </span>
        </a>

        <a href="chats.php" class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 <?php echo isActive('chats.php', $current_page); ?>">
            <i class="fas fa-comments w-5"></i> 
            <span class="font-medium">Chats</span>
        </a>

        <a href="upload.php" class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 <?php echo isActive('upload.php', $current_page); ?>">
            <i class="fas fa-file-upload w-5"></i> 
            <span class="font-medium">Upload File</span>
        </a>

        <a href="track.php" class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 <?php echo isActive('track.php', $current_page); ?>">
            <i class="fas fa-route w-5"></i> 
            <span class="font-medium">Track Status</span>
        </a>

        <a href="records.php" class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 <?php echo isActive('records.php', $current_page); ?>">
            <i class="fas fa-history w-5"></i> 
            <span class="font-medium">Records</span>
        </a>
   
        <a href="archive.php" class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 <?php echo isActive('archive.php', $current_page); ?>">
            <i class="fas fa-archive w-5"></i> 
            <span class="font-medium">Archive</span>
        </a>
    </nav>

    <div class="pt-6 border-t border-white/10">
        <button onclick="confirmLogout()" class="w-full flex items-center gap-3 p-3 text-red-300 hover:text-red-100 transition-colors bg-transparent border-none cursor-pointer">
            <i class="fas fa-sign-out-alt w-5"></i> 
            <span class="font-medium">Logout</span>
        </button>
    </div>
</aside>

<script>
function confirmLogout() {
    Swal.fire({
        title: 'Ready to leave?',
        text: "Confirm Logout",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1e40af', 
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-3xl'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    })
}
</script>