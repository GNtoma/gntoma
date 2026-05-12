<?php
declare(strict_types=1);

/**
 * Fragment HTML pour le fil de conversation (rafraîchissement HTMX).
 * Toujours renvoyer du HTML (pas de sortie vide) pour éviter une zone de chat blanche.
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/message_chat_queries.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    echo '<div class="max-w-2xl mx-auto py-10 px-4 text-center rounded-2xl bg-white/90 border border-gray-100 shadow-sm">';
    echo '<p class="text-sm text-gray-600 mb-3">' . htmlspecialchars(__('message_chat.partial_session'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="../index.php" class="inline-flex text-sm font-bold text-primary">' . htmlspecialchars(__('message_chat.partial_relogin'), ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</div>';
    exit;
}

$user_code = strtoupper(trim((string) $_SESSION['user_id']));
$thread_id = (int) ($_GET['thread'] ?? 0);

if ($thread_id <= 0) {
    echo '<div class="max-w-2xl mx-auto py-10 px-4 text-center text-sm text-gray-600">' . htmlspecialchars(__('message_chat.partial_bad_thread'), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

try {
    $thread_stmt = $pdo->prepare('
        SELECT id FROM message_threads
        WHERE id = ? AND (participant_1 = ? OR participant_2 = ?)
        LIMIT 1
    ');
    $thread_stmt->execute([$thread_id, $user_code, $user_code]);
    if (!$thread_stmt->fetch()) {
        echo '<div class="max-w-2xl mx-auto py-10 px-4 text-center">';
        echo '<p class="text-sm text-gray-600 mb-3">' . htmlspecialchars(__('message_chat.partial_thread_denied'), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<a href="messages_list.php" class="inline-flex text-sm font-bold text-primary">' . htmlspecialchars(__('message_chat.partial_back_list'), ENT_QUOTES, 'UTF-8') . '</a>';
        echo '</div>';
        exit;
    }

    $messages = gntoma_fetch_chat_messages($pdo, $user_code, $thread_id);

    $pdo->prepare('
        UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE thread_id = ? AND recipient_user_code = ? AND is_read = 0
    ')->execute([$thread_id, $user_code]);
} catch (Throwable $e) {
    error_log('Erreur rendu partial chat GNTOMA : ' . $e->getMessage());
    echo '<div class="max-w-2xl mx-auto py-10 px-4 text-center rounded-2xl bg-red-50 border border-red-100 text-sm font-semibold text-red-700">';
    echo htmlspecialchars(__('message_chat.err_system'), ENT_QUOTES, 'UTF-8');
    echo '</div>';
    exit;
}

require __DIR__ . '/message_chat_bubbles.php';
