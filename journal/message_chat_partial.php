<?php
declare(strict_types=1);

session_start();
require_once 'config.php';
require_once __DIR__ . '/message_chat_queries.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    exit;
}

$user_code = (string) $_SESSION['user_id'];
$thread_id = (int)($_GET['thread'] ?? 0);

if ($thread_id <= 0) {
    exit;
}

try {
    $thread_stmt = $pdo->prepare("
        SELECT id FROM message_threads
        WHERE id = ? AND (participant_1 = ? OR participant_2 = ?)
        LIMIT 1
    ");
    $thread_stmt->execute([$thread_id, $user_code, $user_code]);
    if (!$thread_stmt->fetch()) {
        exit;
    }

    $messages = gntoma_fetch_chat_messages($pdo, $user_code, $thread_id);

    $pdo->prepare("
        UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE thread_id = ? AND recipient_user_code = ? AND is_read = 0
    ")->execute([$thread_id, $user_code]);
} catch (Throwable $e) {
    error_log('Erreur rendu partial chat GNTOMA : ' . $e->getMessage());
    exit;
}

require __DIR__ . '/message_chat_bubbles.php';
