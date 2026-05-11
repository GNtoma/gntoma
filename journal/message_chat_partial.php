<?php
declare(strict_types=1);

session_start();
require_once 'config.php';

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

    $messages_stmt = $pdo->prepare("
        SELECT m.*,
            CASE WHEN m.sender_user_code = ? THEN 'me' ELSE 'other' END as sender_type
        FROM messages m
        WHERE m.thread_id = ?
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    $messages_stmt->execute([$user_code, $thread_id]);
    $messages = $messages_stmt->fetchAll();

    $pdo->prepare("
        UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE thread_id = ? AND recipient_user_code = ? AND is_read = 0
    ")->execute([$thread_id, $user_code]);
} catch (Throwable $e) {
    error_log('Erreur rendu partial chat GNTOMA : ' . $e->getMessage());
    exit;
}
?>
<div class="max-w-2xl mx-auto space-y-3 pb-1">
    <?php if (empty($messages)): ?>
    <div class="text-center py-12">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </div>
        <p class="text-gray-500 text-sm">Commencez la conversation</p>
    </div>
    <?php else: ?>
        <?php
        $last_date = null;
        foreach ($messages as $msg):
            $msg_date = date('Y-m-d', strtotime((string)$msg['created_at']));
            $show_date = $msg_date !== $last_date;
            $last_date = $msg_date;
        ?>
            <?php if ($show_date): ?>
            <div class="flex justify-center">
                <span class="text-[10px] text-gray-400 font-bold bg-gray-100 px-3 py-1 rounded-full">
                    <?= date('d/m/Y', strtotime((string)$msg['created_at'])) ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="flex <?= $msg['sender_type'] === 'me' ? 'justify-end' : 'justify-start' ?>">
                <div class="max-w-[86%] sm:max-w-[75%]">
                    <?php if (!empty($msg['has_attachment']) && !empty($msg['attachment_path'])): ?>
                    <div class="mb-2 rounded-xl overflow-hidden">
                        <img src="../<?= htmlspecialchars((string)$msg['attachment_path']) ?>" alt="Image" class="max-w-full max-h-48 object-cover">
                    </div>
                    <?php endif; ?>

                    <div class="message-bubble-<?= $msg['sender_type'] ?> px-4 py-2.5 sm:py-3 text-sm leading-relaxed break-words whitespace-pre-wrap">
                        <?= htmlspecialchars((string)$msg['content']) ?>
                    </div>

                    <div class="flex items-center mt-1 <?= $msg['sender_type'] === 'me' ? 'justify-end' : 'justify-start' ?>">
                        <span class="text-[10px] text-gray-400">
                            <?= date('H:i', strtotime((string)$msg['created_at'])) ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
