<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Query: Counts unread messages for each document thread
$stmt = $pdo->prepare("
    SELECT d.id, d.document_title, d.filename, 
            u_send.name as uploader_name, u_recv.name as receiver_name,
            (SELECT COUNT(*) FROM document_chats 
             WHERE document_id = d.id 
             AND sender_id != ? 
             AND is_read = FALSE) as unread_count
    FROM \"document\" d
    JOIN \"user\" u_send ON d.uploader_id = u_send.id
    JOIN \"user\" u_recv ON d.receiver_user_id = u_recv.id
    WHERE d.uploader_id = ? OR d.receiver_user_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$threads = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat - DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-badge { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        body { height: 100vh; overflow: hidden; }

        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        #chat_messages { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-slate-50 flex text-slate-900">

    <aside class="w-64 bg-[#1e3a8a] text-white h-screen flex-shrink-0">
        <?php include 'includes/sidebar.php'; ?>
    </aside>

    <main class="flex-1 flex h-screen overflow-hidden">
        <div class="w-80 bg-white border-r border-gray-200 flex flex-col h-full">
            <div class="p-6 border-b flex-shrink-0">
                <h1 class="text-xl font-black text-gray-900 tracking-tight">Messages</h1>
                <p class="text-[10px] font-bold text-gray-500 mb-4 uppercase tracking-widest">Document Discussions</p>
                
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="threadSearch" onkeyup="filterThreads()" placeholder="Search documents..." 
                           class="w-full pl-9 pr-4 py-2.5 bg-slate-100 border-2 border-transparent focus:border-blue-500 rounded-xl text-sm font-bold outline-none transition-all">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar" id="thread_list">
                <?php foreach ($threads as $thread): 
                    $display_title = $thread['document_title'] ?: $thread['filename'];
                    $other_party = ($thread['uploader_name'] === $user_name) ? $thread['receiver_name'] : $thread['uploader_name'];
                    $unread = $thread['unread_count'];
                ?>
                    <div onclick="loadChat(<?= $thread['id'] ?>, '<?= htmlspecialchars($display_title, ENT_QUOTES) ?>', '<?= htmlspecialchars($thread['filename'], ENT_QUOTES) ?>')" 
                         id="thread-<?= $thread['id'] ?>"
                         data-search-key="<?= strtolower(htmlspecialchars($display_title . ' ' . $other_party)) ?>"
                         class="thread-item p-4 border-b hover:bg-blue-50 cursor-pointer transition group flex justify-between items-center">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 flex-shrink-0 flex items-center justify-center text-blue-700 font-bold group-hover:bg-blue-600 group-hover:text-white transition-all">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="overflow-hidden">
                                <div class="text-sm font-black text-gray-900 truncate"><?= htmlspecialchars($display_title) ?></div>
                                <div class="text-[11px] font-bold text-gray-500 truncate uppercase">With: <?= htmlspecialchars($other_party) ?></div>
                            </div>
                        </div>
                        
                        <div id="badge-<?= $thread['id'] ?>" class="unread-badge <?= $unread > 0 ? '' : 'hidden' ?>">
                            <span class="bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded-full notification-badge shadow-lg shadow-red-200">
                                <?= $unread ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex-1 flex flex-col h-full bg-white relative">
            <div id="welcome_screen" class="absolute inset-0 flex items-center justify-center bg-slate-50 text-gray-400 flex-col z-10">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center mb-6">
                    <i class="fas fa-comments text-4xl text-blue-200"></i>
                </div>
                <p class="font-black uppercase tracking-widest text-xs">Select a conversation</p>
            </div>

            <div id="chat_area" class="hidden flex flex-col h-full">
                <div class="p-5 border-b flex justify-between items-center shadow-sm flex-shrink-0 bg-white">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-blue-700 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-folder-open text-xs"></i>
                        </div>
                        <div>
                            <h2 id="active_doc_title" class="font-black text-gray-900 uppercase text-sm tracking-tight leading-none mb-1"></h2>
                            <span class="text-[10px] font-black text-emerald-600 flex items-center gap-1 uppercase">
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> Server Connected 
                            </span>
                        </div>
                    </div>
                    <a id="view_doc_btn" href="#" target="_blank" class="flex items-center gap-2 bg-gray-900 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-xs font-black transition-all shadow-md">
                        <i class="fas fa-external-link-alt"></i>
                        VIEW DOCUMENT
                    </a>
                </div>

                <div id="chat_messages" class="flex-1 p-6 overflow-y-auto bg-slate-50 flex flex-col custom-scrollbar">
                </div>

                <div class="p-5 border-t flex-shrink-0 bg-white">
                    <form onsubmit="sendMessage(event)" class="flex gap-3">
                        <input type="hidden" id="active_doc_id">
                        <input type="text" id="chat_input" placeholder="Write your message here..." 
                               class="flex-1 px-6 py-4 bg-slate-100 rounded-2xl outline-none border-2 border-transparent focus:border-blue-600 focus:bg-white text-sm font-bold transition-all" autocomplete="off">
                        <button type="submit" class="bg-blue-700 text-white px-8 py-4 rounded-2xl hover:bg-gray-900 transition-all shadow-xl shadow-blue-100 flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-widest">Send</span>
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        let chatInterval;

        function filterThreads() {
            const input = document.getElementById('threadSearch').value.toLowerCase();
            const items = document.getElementsByClassName('thread-item');
            Array.from(items).forEach(item => {
                const searchKey = item.getAttribute('data-search-key');
                item.style.display = searchKey.includes(input) ? "flex" : "none";
            });
        }

        async function checkNotifications() {
            try {
                const res = await fetch('check_notifications.php');
                const data = await res.json();
                const activeId = document.getElementById('active_doc_id').value;

                data.forEach(item => {
                    const badge = document.getElementById(`badge-${item.document_id}`);
                    if (badge) {
                        if (item.unread_count > 0 && item.document_id != activeId) {
                            badge.classList.remove('hidden');
                            badge.querySelector('span').innerText = item.unread_count;
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                });
            } catch (e) { console.error(e); }
        }

        async function loadChat(docId, title, filename) {
            document.getElementById('welcome_screen').classList.add('hidden');
            document.getElementById('chat_area').classList.remove('hidden');
            document.getElementById('active_doc_title').innerText = title;
            document.getElementById('active_doc_id').value = docId;
            
            const viewBtn = document.getElementById('view_doc_btn');
            viewBtn.href = 'uploads/' + filename; 

            const badge = document.getElementById(`badge-${docId}`);
            if (badge) badge.classList.add('hidden');

            if(chatInterval) clearInterval(chatInterval);
            
            await fetch(`mark_read.php?doc_id=${docId}`);
            await fetchMessages(docId, true);
            
            chatInterval = setInterval(() => fetchMessages(docId, false), 3000);
        }

        async function fetchMessages(docId, forceScroll = false) {
            const container = document.getElementById('chat_messages');
            const activeId = document.getElementById('active_doc_id').value;
            
            if (docId != activeId) return;

            try {
                const res = await fetch(`fetch_chat.php?doc_id=${docId}`);
                const html = await res.text();
                
                const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                container.innerHTML = html;
                
                if (forceScroll || isAtBottom) {
                    container.scrollTop = container.scrollHeight;
                }

                if (!document.hidden) {
                    fetch(`mark_read.php?doc_id=${docId}`);
                }
            } catch (e) { console.error(e); }
        }

        async function sendMessage(e) {
            e.preventDefault();
            const docId = document.getElementById('active_doc_id').value;
            const input = document.getElementById('chat_input');
            const message = input.value.trim();

            if (!message) return;

            const formData = new FormData();
            formData.append('doc_id', docId);
            formData.append('message', message);

            input.value = '';
            await fetch('send_chat.php', { method: 'POST', body: formData });
            fetchMessages(docId, true);
        }

        /**
         * Delete Message Functionality
         */
        async function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message? This action cannot be undone.')) return;

            const formData = new FormData();
            formData.append('message_id', messageId);

            try {
                const res = await fetch('delete_chat.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    const docId = document.getElementById('active_doc_id').value;
                    // Refresh chat to show changes immediately
                    fetchMessages(docId);
                } else {
                    alert('Could not delete message. You may only delete your own messages.');
                }
            } catch (e) {
                console.error('Delete error:', e);
            }
        }

        setInterval(checkNotifications, 5000);
    </script>
</body>
</html>