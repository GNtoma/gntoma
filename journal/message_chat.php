<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_chat.php
 * DESCRIPTION : Page de conversation avec un utilisateur
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/message_chat_queries.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = strtoupper(trim((string) $_SESSION['user_id']));
$thread_id = (int) ($_GET['thread'] ?? 0);

if (!$thread_id) {
    header("Location: messages_list.php");
    exit;
}

try {
    // Vérifier que la conversation existe et que l'utilisateur en fait partie
    $otherProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'other_profile_pic');
    $thread_stmt = $pdo->prepare("
        SELECT t.*,
            CASE 
                WHEN t.participant_1 = ? THEN t.participant_2 
                ELSE t.participant_1 
            END as other_user_code,
            u.first_name as other_first_name,
            u.last_name as other_last_name,
            {$otherProfilePicSelect}
        FROM message_threads t
        JOIN users u ON u.user_code = CASE 
            WHEN t.participant_1 = ? THEN t.participant_2 
            ELSE t.participant_1 
        END
        WHERE t.id = ? 
        AND (t.participant_1 = ? OR t.participant_2 = ?)
    ");
    $thread_stmt->execute([$user_code, $user_code, $thread_id, $user_code, $user_code]);
    $thread = $thread_stmt->fetch();

    if (!$thread) {
        header("Location: messages_list.php?error=thread_not_found");
        exit;
    }

    $other_user_code = $thread['other_user_code'];

    // Vérifier si bloqué
    $blocked_stmt = $pdo->prepare("
        SELECT COUNT(*) as is_blocked 
        FROM user_blocks 
        WHERE blocker_user_code = ? AND blocked_user_code = ?
    ");
    $blocked_stmt->execute([$user_code, $other_user_code]);
    $is_blocked = $blocked_stmt->fetch()['is_blocked'] > 0;

    // Récupérer les crédits
    $credits_stmt = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ?");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    $remaining_credits = $credits['remaining_credits'] ?? 0;

    // Récupérer les messages
    $messages = gntoma_fetch_chat_messages($pdo, $user_code, $thread_id);

    // Marquer les messages comme lus
    $pdo->prepare("
        UPDATE messages 
        SET is_read = 1, read_at = NOW() 
        WHERE thread_id = ? AND recipient_user_code = ? AND is_read = 0
    ")->execute([$thread_id, $user_code]);

} catch (PDOException $e) {
    error_log("Erreur chat : " . $e->getMessage());
    header("Location: messages_list.php?error=chat_error");
    exit;
}

$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($thread['other_first_name']) ?> - GNTOMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#007AFF', dark: '#1D1D1F', surface: '#F5F5F7' }
                }
            }
        }
    </script>
    <style>
        .chat-page-root {
            font-family: 'Outfit', sans-serif;
            -webkit-font-smoothing: antialiased;
            background-color: #dcd7cd;
            background-image:
                radial-gradient(ellipse 120% 80% at 50% -20%, rgba(0, 122, 255, 0.09), transparent 50%),
                radial-gradient(ellipse 80% 60% at 100% 50%, rgba(124, 58, 237, 0.06), transparent 45%),
                radial-gradient(ellipse 70% 50% at 0% 80%, rgba(52, 199, 89, 0.05), transparent 40%),
                repeating-linear-gradient(
                    135deg,
                    transparent,
                    transparent 11px,
                    rgba(255, 255, 255, 0.06) 11px,
                    rgba(255, 255, 255, 0.06) 12px
                ),
                radial-gradient(rgba(0, 0, 0, 0.045) 1px, transparent 1px);
            background-size: auto, auto, auto, auto, 14px 14px;
            background-attachment: fixed;
        }
        .message-bubble-me {
            background: #007AFF;
            color: white;
            border-radius: 20px 20px 4px 20px;
        }
        .message-bubble-other {
            background: white;
            color: #1D1D1F;
            border-radius: 20px 20px 20px 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .chat-container {
            scroll-behavior: smooth;
            min-height: 0;
            flex: 1 1 0%;
            background-color: rgba(252, 250, 245, 0.78);
            background-image:
                radial-gradient(rgba(0, 0, 0, 0.028) 1px, transparent 1px),
                linear-gradient(180deg, rgba(255, 255, 255, 0.35) 0%, transparent 28%);
            background-size: 13px 13px, auto;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        @keyframes chatBubbleRowIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .chat-bubble-row {
            animation: chatBubbleRowIn 0.22s ease-out both;
        }
    </style>
</head>
<body class="min-h-[100dvh] h-[100dvh] flex flex-col overflow-hidden chat-page-root">
    
    <!-- Header -->
    <header class="bg-white/90 backdrop-blur-xl border-b border-gray-100 px-4 py-3 flex-shrink-0">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-2">
            <a href="messages_list.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all flex-shrink-0">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            
            <div class="flex items-center space-x-3 flex-1 min-w-0 justify-center">
                <?php 
                $profile_pic = !empty($thread['other_profile_pic']) ? '../' . $thread['other_profile_pic'] : '../images/user_default.png';
                ?>
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-10 h-10 rounded-xl object-cover border border-gray-100">
                <div class="min-w-0 text-center sm:text-left">
                    <h1 class="font-bold text-dark text-sm truncate">
                        <?= htmlspecialchars($thread['other_first_name'] . ' ' . $thread['other_last_name']) ?>
                    </h1>
                    <p class="text-[10px] text-gray-400 font-medium"><?= htmlspecialchars((string) $thread['other_user_code'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-1 flex-shrink-0">
                <?= gntoma_lang_switch_markup() ?>
                <a href="search_code.php?code=<?= htmlspecialchars((string) $thread['other_user_code'], ENT_QUOTES, 'UTF-8') ?>" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                    <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Crédits warning -->
    <?php if ($remaining_credits < 1): ?>
    <div class="bg-red-50 border-b border-red-100 px-4 py-2 flex-shrink-0">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <p class="text-xs text-red-600 font-bold"><?= htmlspecialchars(__('message_chat.low_credits'), ENT_QUOTES, 'UTF-8') ?></p>
            <a href="messages_buy.php" class="text-xs bg-red-500 text-white px-3 py-1 rounded-lg font-bold"><?= htmlspecialchars(__('message_chat.recharge'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages : rendu serveur immédiat, puis rafraîchissement périodique -->
    <div class="flex-1 min-h-0 overflow-y-auto px-3 sm:px-4 py-4 chat-container" id="chat-container"
         hx-get="message_chat_partial.php?thread=<?= (int)$thread_id ?>"
         hx-trigger="every 4s"
         hx-target="#chat-container"
         hx-swap="innerHTML">
        <?php require __DIR__ . '/message_chat_bubbles.php'; ?>
    </div>

    <!-- Formulaire d'envoi -->
    <?php if (!$is_blocked && $remaining_credits >= 1): ?>
    <div class="bg-white/95 backdrop-blur-xl border-t border-gray-100 px-3 sm:px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] flex-shrink-0">
        <div class="max-w-2xl mx-auto">
            <div id="chat-send-feedback"></div>
            <?php if ($error === 'blocked'): ?>
            <div class="bg-red-50 text-red-600 text-sm font-bold py-3 px-4 rounded-xl text-center mb-3">
                <?= htmlspecialchars(__('message_chat.blocked_by_other'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            
            <form id="chat-send-form" method="POST" enctype="multipart/form-data"
                  hx-post="message_send_process.php"
                  hx-target="#chat-container"
                  hx-swap="innerHTML"
                  hx-encoding="multipart/form-data"
                  class="flex items-end space-x-2">
                <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                <input type="hidden" name="recipient_code" value="<?= htmlspecialchars($other_user_code) ?>">
                
                <div class="flex-1 bg-gray-100 rounded-2xl flex items-end border border-gray-200 focus-within:ring-2 focus-within:ring-primary/30">
                    <textarea name="content" rows="1" 
                              placeholder="<?= htmlspecialchars(__('message_chat.placeholder'), ENT_QUOTES, 'UTF-8') ?>" 
                              class="flex-1 bg-transparent px-4 py-3 text-sm outline-none resize-none max-h-40 min-h-[44px]"
                              oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
                              required></textarea>
                    
                    <label class="p-3 cursor-pointer text-gray-400 hover:text-primary transition-all">
                        <input type="file" name="attachment" accept="image/*" class="hidden" onchange="previewImage(this)">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </label>
                </div>
                
                <button id="send-button" type="submit" class="w-12 h-12 bg-primary text-white rounded-2xl flex items-center justify-center hover:bg-blue-600 transition-all flex-shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
            
            <!-- Prévisualisation image -->
            <div id="image-preview" class="hidden mt-2">
                <div class="relative inline-block">
                    <img id="preview-img" src="" alt="" class="h-20 w-20 object-cover rounded-xl">
                    <button type="button" onclick="clearImage()" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($is_blocked): ?>
    <div class="bg-red-50 border-t border-red-100 px-4 py-4 text-center flex-shrink-0">
        <p class="text-red-600 font-bold text-sm"><?= htmlspecialchars(__('message_chat.you_blocked'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="messages_blocked.php" class="text-xs text-primary font-bold mt-1 inline-block"><?= htmlspecialchars(__('message_chat.manage_blocks'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <?php endif; ?>

    <script>
        function isNearBottom(el) {
            const threshold = 120;
            return (el.scrollHeight - el.scrollTop - el.clientHeight) < threshold;
        }

        document.body.addEventListener('htmx:beforeRequest', function(evt) {
            const chat = document.getElementById('chat-container');
            if (!chat) return;
            const fromPoll = evt.target && evt.target.id === 'chat-container';
            const fromSend = evt.target && evt.target.id === 'chat-send-form';
            if (fromPoll || fromSend) {
                chat.dataset.shouldAutoscroll = isNearBottom(chat) ? '1' : '0';
            }
            if (fromSend) {
                const btn = document.getElementById('send-button');
                if (btn) btn.disabled = true;
            }
        });

        document.body.addEventListener('htmx:afterRequest', function(evt) {
            if (evt.target && evt.target.id === 'chat-send-form') {
                const btn = document.getElementById('send-button');
                if (btn) btn.disabled = false;
            }
        });

        document.body.addEventListener('htmx:afterSwap', function(evt) {
            if (evt.target && evt.target.id === 'chat-container') {
                if (evt.target.dataset.shouldAutoscroll !== '0') {
                    evt.target.scrollTop = evt.target.scrollHeight;
                }
                const cfg = evt.detail && evt.detail.requestConfig;
                const xhr = evt.detail && evt.detail.xhr;
                const isPost = (cfg && cfg.verb === 'post')
                    || (xhr && xhr.method && xhr.method.toUpperCase() === 'POST');
                if (isPost) {
                    const form = document.getElementById('chat-send-form');
                    if (form) {
                        form.reset();
                        const ta = form.querySelector('textarea[name="content"]');
                        if (ta) {
                            ta.style.height = '';
                        }
                        const fileInput = form.querySelector('input[name="attachment"]');
                        if (fileInput) fileInput.value = '';
                        const preview = document.getElementById('image-preview');
                        if (preview) preview.classList.add('hidden');
                        const previewImg = document.getElementById('preview-img');
                        if (previewImg) previewImg.src = '';
                    }
                }
            }
        });

        const sendForm = document.getElementById('chat-send-form');
        const contentTextarea = sendForm ? sendForm.querySelector('textarea[name="content"]') : null;

        if (contentTextarea) {
            contentTextarea.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendForm.requestSubmit();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('chat-container');
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        });

        // Preview image
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearImage() {
            document.querySelector('input[name="attachment"]').value = '';
            document.getElementById('image-preview').classList.add('hidden');
        }
    </script>

</body>
</html>
