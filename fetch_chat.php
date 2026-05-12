<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) exit('Unauthorized');

$pdo = getPDO();
$doc_id = $_GET['doc_id'] ?? null;
$current_user = $_SESSION['user_id'];

if (!$doc_id) exit('No document selected');

$stmt = $pdo->prepare("
    SELECT c.*, u.name as sender_name 
    FROM document_chats c 
    JOIN \"user\" u ON c.sender_id = u.id 
    WHERE c.document_id = ? 
    ORDER BY c.created_at ASC
");
$stmt->execute([$doc_id]);
$messages = $stmt->fetchAll();

if (empty($messages)) {
    echo '<div class="text-center text-gray-600 mt-10 italic text-sm font-black uppercase tracking-widest">No messages yet.</div>';
}

foreach ($messages as $msg) {
    $isMine = ($msg['sender_id'] == $current_user);
    $alignClass = $isMine ? 'items-end' : 'items-start';
    
    // High contrast bubble colors
    $bubbleClass = $isMine 
        ? 'bg-blue-700 text-white rounded-br-none shadow-blue-100' 
        : 'bg-white text-gray-900 border-2 border-slate-200 rounded-bl-none shadow-sm';
    
    $nameColor = $isMine ? 'text-blue-900' : 'text-gray-800';
    $timeColor = $isMine ? 'text-blue-100' : 'text-gray-500';

    echo "
    <div class='flex flex-col mb-4 $alignClass group'>
        <span class='text-[10px] font-black $nameColor mb-1 px-1 uppercase tracking-tighter'>{$msg['sender_name']}</span>
        
        <div class='relative max-w-[85%] p-3 px-4 rounded-2xl shadow-md text-sm $bubbleClass'>
            " . nl2br(htmlspecialchars($msg['message'])) . "
            
            <div class='flex justify-between items-center mt-2 gap-4'>
                ";
                
                // Show delete button only on your messages via hover
                if ($isMine) {
                    echo "
                    <button onclick='deleteMessage({$msg['id']})' 
                            class='opacity-0 group-hover:opacity-100 text-red-200 hover:text-red-400 transition-all text-[10px] font-black uppercase flex items-center gap-1'>
                        <i class='fas fa-trash-alt'></i> Delete
                    </button>";
                } else {
                    echo "<span></span>"; // Spacer for non-owned messages
                }

                echo "
                <div class='text-[9px] font-black text-right $timeColor uppercase'>" . date('h:i A', strtotime($msg['created_at'])) . "</div>
            </div>
        </div>";

    // Seen/Sent Status Logic
    if ($isMine) {
        $statusLabel = $msg['is_read'] ? 'Seen' : 'Sent';
        $statusIcon = $msg['is_read'] ? 'fa-check-double' : 'fa-check';
        $statusColor = $msg['is_read'] ? 'text-blue-600' : 'text-gray-400';

        echo "
        <div class='flex items-center gap-1 mt-1 px-1 $statusColor'>
            <i class='fas $statusIcon text-[10px]'></i>
            <span class='text-[9px] font-black uppercase tracking-tighter'>$statusLabel</span>
        </div>";
    }

    echo "</div>";
}