<?php
declare(strict_types=1);

/**
 * Rendu des bulles de conversation (utilisé par message_chat.php et message_chat_partial.php).
 *
 * @var array<int, array<string, mixed>> $messages
 */

if (!isset($messages) || !is_array($messages)) {
    return;
}
?>
<div class="max-w-2xl mx-auto space-y-3 pb-1">
    <?php if (empty($messages)): ?>
    <div class="text-center py-12">
        <div class="w-16 h-16 bg-white/80 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-gray-100">
            <svg class="h-8 w-8 text-primary/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </div>
        <p class="text-gray-600 text-sm font-medium"><?= htmlspecialchars(__('message_chat_partial.start_conversation'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php else: ?>
        <?php
        $last_date = null;
        foreach ($messages as $msg):
            $msg_date = date('Y-m-d', strtotime((string) $msg['created_at']));
            $show_date = $msg_date !== $last_date;
            $last_date = $msg_date;
            ?>
            <?php if ($show_date): ?>
            <div class="flex justify-center">
                <span class="text-[10px] text-gray-600 font-semibold bg-white/90 shadow-sm border border-gray-100 px-3 py-1 rounded-full">
                    <?= htmlspecialchars(date('d/m/Y', strtotime((string) $msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="flex <?= $msg['sender_type'] === 'me' ? 'justify-end' : 'justify-start' ?> chat-bubble-row">
                <div class="max-w-[88%] sm:max-w-[78%]">
                    <?php if (!empty($msg['has_attachment']) && !empty($msg['attachment_path'])): ?>
                    <div class="mb-1.5 rounded-2xl overflow-hidden shadow-sm <?= $msg['sender_type'] === 'me' ? 'ring-1 ring-white/20' : 'ring-1 ring-black/5' ?>">
                        <img src="../<?= htmlspecialchars((string) $msg['attachment_path']) ?>" alt="" class="max-w-full max-h-52 sm:max-h-60 object-cover w-full">
                    </div>
                    <?php endif; ?>

                    <div class="message-bubble-<?= $msg['sender_type'] === 'me' ? 'me' : 'other' ?> px-3.5 py-2.5 sm:px-4 sm:py-3 text-[15px] leading-snug break-words whitespace-pre-wrap shadow-sm">
                        <?= htmlspecialchars((string) $msg['content']) ?>
                    </div>

                    <div class="flex items-center gap-1 mt-1 px-0.5 <?= $msg['sender_type'] === 'me' ? 'justify-end' : 'justify-start' ?>">
                        <span class="text-[10px] text-gray-500 tabular-nums">
                            <?= htmlspecialchars(date('H:i', strtotime((string) $msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
