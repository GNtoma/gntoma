<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_chat.php
 * DESCRIPTION : Page de conversation avec un utilisateur
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$thread_id = (int)($_GET['thread'] ?? 0);

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
<html lang="fr">
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
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 10% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 10%, rgba(249, 115, 22, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(168, 85, 247, 0.08) 0px, transparent 50%),
                radial-gradient(at 10% 90%, rgba(59, 130, 246, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
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
        }
    </style>
</head>
<body class="h-[100dvh] flex flex-col overflow-hidden">
    
    <!-- Header -->
    <header class="bg-white/90 backdrop-blur-xl border-b border-gray-100 px-4 py-3 flex-shrink-0">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="messages_list.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            
            <div class="flex items-center space-x-3">
                <?php 
                $profile_pic = !empty($thread['other_profile_pic']) ? '../' . $thread['other_profile_pic'] : '../images/user_default.png';
                ?>
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-10 h-10 rounded-xl object-cover border border-gray-100">
                <div>
                    <h1 class="font-bold text-dark text-sm">
                        <?= htmlspecialchars($thread['other_first_name'] . ' ' . $thread['other_last_name']) ?>
                    </h1>
                    <p class="text-[10px] text-gray-400 font-medium"><?= $thread['other_user_code'] ?></p>
                </div>
            </div>
            
            <a href="search_code.php?code=<?= $thread['other_user_code'] ?>" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </a>
        </div>
    </header>

    <!-- Crédits warning -->
    <?php if ($remaining_credits < 1): ?>
    <div class="bg-red-50 border-b border-red-100 px-4 py-2 flex-shrink-0">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <p class="text-xs text-red-600 font-bold">Crédits insuffisants</p>
            <a href="messages_buy.php" class="text-xs bg-red-500 text-white px-3 py-1 rounded-lg font-bold">Recharger</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages -->
    <div class="flex-1 overflow-y-auto px-3 sm:px-4 py-4 chat-container" id="chat-container"
         hx-get="message_chat_partial.php?thread=<?= (int)$thread_id ?>"
         hx-trigger="load, every 4s"
         hx-target="#chat-container"
         hx-swap="innerHTML">
    </div>

    <!-- Formulaire d'envoi -->
    <?php if (!$is_blocked && $remaining_credits >= 1): ?>
    <div class="bg-white/95 backdrop-blur-xl border-t border-gray-100 px-3 sm:px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] flex-shrink-0">
        <div class="max-w-2xl mx-auto">
            <?php if ($error === 'blocked'): ?>
            <div class="bg-red-50 text-red-600 text-sm font-bold py-3 px-4 rounded-xl text-center mb-3">
                Cet utilisateur vous a bloqué
            </div>
            <?php endif; ?>
            
            <form id="chat-send-form" action="message_send_process.php" method="POST" enctype="multipart/form-data" class="flex items-end space-x-2">
                <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                <input type="hidden" name="recipient_code" value="<?= htmlspecialchars($other_user_code) ?>">
                
                <div class="flex-1 bg-gray-100 rounded-2xl flex items-end border border-gray-200 focus-within:ring-2 focus-within:ring-primary/30">
                    <textarea name="content" rows="1" 
                              placeholder="Votre message..." 
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
        <p class="text-red-600 font-bold text-sm">Vous avez bloqué cet utilisateur</p>
        <a href="messages_blocked.php" class="text-xs text-primary font-bold mt-1 inline-block">Gérer les blocages</a>
    </div>
    <?php endif; ?>

    <script>
        function isNearBottom(el) {
            const threshold = 120;
            return (el.scrollHeight - el.scrollTop - el.clientHeight) < threshold;
        }

        document.body.addEventListener('htmx:beforeRequest', function(evt) {
            if (evt.target && evt.target.id === 'chat-container') {
                evt.target.dataset.shouldAutoscroll = isNearBottom(evt.target) ? '1' : '0';
            }
        });

        document.body.addEventListener('htmx:afterSwap', function(evt) {
            if (evt.target && evt.target.id === 'chat-container') {
                if (evt.target.dataset.shouldAutoscroll !== '0') {
                    evt.target.scrollTop = evt.target.scrollHeight;
                }
            }
        });

        const sendForm = document.getElementById('chat-send-form');
        const sendButton = document.getElementById('send-button');
        const contentTextarea = sendForm ? sendForm.querySelector('textarea[name="content"]') : null;

        if (contentTextarea) {
            contentTextarea.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendForm.requestSubmit();
                }
            });
        }

        if (sendForm && sendButton) {
            sendForm.addEventListener('submit', function () {
                sendButton.disabled = true;
            });
        }

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
