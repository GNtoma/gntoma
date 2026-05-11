<?php
declare(strict_types=1);
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

$target_user = trim(strtoupper((string)($_POST['target_user'] ?? '')));

if (strlen($target_user) < 2) {
    echo '';
    exit;
}

if ($target_user === strtoupper($_SESSION['user_id'])) {
    echo '<p class="text-[10px] font-bold text-red-500 ml-1">Vous ne pouvez pas vous offrir de jours à vous-même.</p>';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name, first_name, last_name FROM users WHERE UPPER(user_code) = ?");
    $stmt->execute([$target_user]);
    $user = $stmt->fetch();
    
    if ($user) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if (empty($name)) {
            $name = $user['name'];
        }
        $safeName = htmlspecialchars($name);
        echo '<div class="bg-green-50/80 border border-green-100 rounded-xl px-3 py-2 flex items-center space-x-2 animate__animated animate__fadeIn">';
        echo '<div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">';
        echo '<svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>';
        echo '</div>';
        echo '<div class="min-w-0 flex-1">';
        echo '<p class="text-[10px] text-green-700 font-bold uppercase tracking-wide">Bénéficiaire trouvé</p>';
        echo '<p class="text-xs font-black text-green-900 truncate">' . $safeName . '</p>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<p class="text-[10px] font-bold text-red-500 ml-1">Aucun utilisateur trouvé pour ce code.</p>';
    }
} catch (Throwable $e) {
    echo '';
}
