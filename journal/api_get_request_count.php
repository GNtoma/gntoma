<?php
declare(strict_types=1);
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

try {
    $req_u = $pdo->prepare("SELECT COUNT(*) as t FROM access_requests WHERE author_user_code = ? AND status = 'pending'");
    $req_u->execute([$_SESSION['user_id']]);
    $req_pending = $req_u->fetch()['t'] ?? 0;
    
    if ($req_pending > 0) {
        $displayCount = $req_pending > 99 ? '99+' : (string) $req_pending;
        echo '<span data-request-count="' . (int) $req_pending . '" class="relative flex h-6 min-w-[24px] items-center justify-center">'
            . '<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-70"></span>'
            . '<span class="relative inline-flex items-center justify-center rounded-full h-6 min-w-[24px] px-1.5 bg-red-600 text-white text-[10px] leading-none font-black shadow-lg shadow-red-500/50 border-2 border-white">'
            . $displayCount
            . '</span>'
            . '</span>';
    } else {
        echo '';
    }
} catch (Throwable $e) {
    echo '';
}
