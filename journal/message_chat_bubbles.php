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

$no_message_credits = !empty($no_message_credits ?? false);

$chat_messages_revision = gntoma_chat_messages_revision($messages);
$chat_bubble_row_class = !empty($chat_bubbles_quiet_rows ?? false) ? '' : ' chat-bubble-row';
?>
<div id="chat-messages-track"
     data-chat-revision="<?= htmlspecialchars($chat_messages_revision, ENT_QUOTES, 'UTF-8') ?>"
     class="chat-messages-track">
<div class="max-w-2xl mx-auto space-y-2.5 sm:space-y-3 pb-2 px-0.5">
    <?php if (empty($messages)): ?>
    <div class="text-center py-14 sm:py-16">
        <div class="w-16 h-16 bg-white/85 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-md border border-white/60 chat-bubble-shadow-soft">
            <svg class="h-8 w-8 text-primary/45" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </div>
        <p class="text-gray-600/90 text-sm font-medium tracking-tight"><?= htmlspecialchars(__('message_chat_partial.start_conversation'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php else: ?>
        <?php
        $last_date = null;
        foreach ($messages as $msg):
            $msg_date = date('Y-m-d', strtotime((string) $msg['created_at']));
            $show_date = $msg_date !== $last_date;
            $last_date = $msg_date;
            $is_me = $msg['sender_type'] === 'me';
            ?>
            <?php if ($show_date): ?>
            <div class="flex justify-center py-1">
                <span class="text-[10px] uppercase tracking-wide text-gray-500 font-semibold bg-white/80 backdrop-blur-sm shadow-sm border border-gray-100/80 px-3 py-1 rounded-full">
                    <?= htmlspecialchars(date('d/m/Y', strtotime((string) $msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>

            <?php
            $lock_received = $no_message_credits && !$is_me;
            $has_att = !empty($msg['has_attachment']) && !empty($msg['attachment_path']);
            ?>
            <div class="flex <?= $is_me ? 'justify-end' : 'justify-start' ?><?= $chat_bubble_row_class ?>">
                <div class="max-w-[min(92%,28rem)] sm:max-w-[min(82%,28rem)]">
                    <?php if ($lock_received): ?>
                    <div class="message-bubble-other rounded-2xl px-[14px] py-3 sm:px-4 sm:py-3.5 text-[13px] leading-snug border border-amber-100 bg-amber-50/95 text-amber-950 shadow-inner chat-bubble-shadow-soft">
                        <div class="flex flex-col gap-2">
                            <?php if ($has_att): ?>
                            <div class="flex items-center gap-2 text-amber-800/95">
                                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[11px] font-bold uppercase tracking-wide"><?= htmlspecialchars(__('message_chat.locked_attachment'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex items-start gap-2">
                                <svg class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6l-4-4V5a2 2 0 012-2h12a2 2 0 012 2v4.586a2 2 0 01-.707 1.414L10 15v0z" />
                                </svg>
                                <span class="font-medium"><?= htmlspecialchars(__('message_chat.preview_locked'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php if ($has_att): ?>
                    <div class="mb-1.5 rounded-2xl overflow-hidden chat-bubble-shadow-soft <?= $is_me ? 'ring-1 ring-white/25' : 'ring-1 ring-gray-900/10' ?>">
                        <img src="../<?= htmlspecialchars((string) $msg['attachment_path']) ?>" alt="" class="max-w-full max-h-52 sm:max-h-64 object-cover w-full">
                    </div>
                    <?php endif; ?>

                    <div class="message-bubble-<?= $is_me ? 'me' : 'other' ?> px-[14px] py-2.5 sm:px-4 sm:py-3 text-[15px] leading-[1.45] tracking-tight break-words whitespace-pre-wrap">
                        <?= htmlspecialchars((string) $msg['content']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-1 mt-0.5 px-1 <?= $is_me ? 'justify-end' : 'justify-start' ?>">
                        <time class="text-[10px] text-gray-500/90 tabular-nums font-medium opacity-80" datetime="<?= htmlspecialchars(date('c', strtotime((string) $msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(date('H:i', strtotime((string) $msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
