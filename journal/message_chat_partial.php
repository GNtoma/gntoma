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

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_code'])) {
    echo '<div id="chat-messages-track" data-chat-revision="err:session" class="chat-messages-track"><div class="max-w-2xl mx-auto py-10 px-4 text-center rounded-2xl bg-white/90 border border-gray-100 shadow-sm">';
    echo '<p class="text-sm text-gray-600 mb-3">' . htmlspecialchars(__('message_chat.partial_session'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="../index.php" class="inline-flex text-sm font-bold text-primary">' . htmlspecialchars(__('message_chat.partial_relogin'), ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</div></div>';
    exit;
}

$user_code = gntoma_resolve_logged_in_user_code($pdo);
if ($user_code === null || $user_code === '') {
    echo '<div id="chat-messages-track" data-chat-revision="err:session" class="chat-messages-track"><div class="max-w-2xl mx-auto py-10 px-4 text-center rounded-2xl bg-white/90 border border-gray-100 shadow-sm">';
    echo '<p class="text-sm text-gray-600 mb-3">' . htmlspecialchars(__('message_chat.partial_session'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="../index.php" class="inline-flex text-sm font-bold text-primary">' . htmlspecialchars(__('message_chat.partial_relogin'), ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</div></div>';
    exit;
}

$thread_id = (int) ($_GET['thread'] ?? 0);

if ($thread_id <= 0) {
    echo '<div id="chat-messages-track" data-chat-revision="err:bad_thread" class="chat-messages-track"><div class="max-w-2xl mx-auto py-10 px-4 text-center text-sm text-gray-600">' . htmlspecialchars(__('message_chat.partial_bad_thread'), ENT_QUOTES, 'UTF-8') . '</div></div>';
    exit;
}

try {
    $thread_stmt = $pdo->prepare('
        SELECT id FROM message_threads
        WHERE id = ? AND (UPPER(TRIM(participant_1)) = ? OR UPPER(TRIM(participant_2)) = ?)
        LIMIT 1
    ');
    $thread_stmt->execute([$thread_id, $user_code, $user_code]);
    if (!$thread_stmt->fetch()) {
        echo '<div id="chat-messages-track" data-chat-revision="err:denied" class="chat-messages-track"><div class="max-w-2xl mx-auto py-10 px-4 text-center">';
        echo '<p class="text-sm text-gray-600 mb-3">' . htmlspecialchars(__('message_chat.partial_thread_denied'), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<a href="messages_list.php" class="inline-flex text-sm font-bold text-primary">' . htmlspecialchars(__('message_chat.partial_back_list'), ENT_QUOTES, 'UTF-8') . '</a>';
        echo '</div></div>';
        exit;
    }

    $messages = gntoma_fetch_chat_messages($pdo, $user_code, $thread_id);

    $credits_stmt = $pdo->prepare('SELECT remaining_credits FROM message_credits WHERE UPPER(TRIM(user_code)) = ?');
    $credits_stmt->execute([$user_code]);
    $credits_row = $credits_stmt->fetch();
    $remaining_credits = (int) ($credits_row['remaining_credits'] ?? 0);
    $no_message_credits = $remaining_credits < 1;

    if (!$no_message_credits) {
        $pdo->prepare('
            UPDATE messages
            SET is_read = 1, read_at = NOW()
            WHERE thread_id = ? AND UPPER(TRIM(recipient_user_code)) = ? AND is_read = 0
        ')->execute([$thread_id, $user_code]);
    }
} catch (Throwable $e) {
    error_log('Erreur rendu partial chat GNTOMA : ' . $e->getMessage());
    echo '<div id="chat-messages-track" data-chat-revision="err:system" class="chat-messages-track"><div class="max-w-2xl mx-auto py-10 px-4 text-center rounded-2xl bg-red-50 border border-red-100 text-sm font-semibold text-red-700">';
    echo htmlspecialchars(__('message_chat.err_system'), ENT_QUOTES, 'UTF-8');
    echo '</div></div>';
    exit;
}

$chat_bubbles_quiet_rows = true;
require __DIR__ . '/message_chat_bubbles.php';
