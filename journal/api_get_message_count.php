<?php
declare(strict_types=1);
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

try {
    $msg_u = $pdo->prepare("SELECT COUNT(*) as t FROM messages WHERE recipient_user_code = ? AND is_read = 0");
    $msg_u->execute([$_SESSION['user_id']]);
    $msg_unread = $msg_u->fetch()['t'] ?? 0;
    
    if ($msg_unread > 0) {
        $displayCount = $msg_unread > 99 ? '99+' : (string) $msg_unread;
        echo '<span data-message-count="' . (int) $msg_unread . '" class="bg-red-500 text-white text-[9px] font-black min-w-[20px] h-5 px-1 rounded-full flex items-center justify-center shadow-md">'
            . $displayCount
            . '</span>';
    } else {
        echo '';
    }
} catch (Throwable $e) {
    echo '';
}
