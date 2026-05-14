<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/messages_list.php
 * DESCRIPTION : Liste des conversations et notifications
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

// Session GNTOMA : le code métier est dans `user_id` (nom historique) ; `user_code` est accepté en secours.
$user_code = gntoma_resolve_logged_in_user_code($pdo);
if ($user_code === null || $user_code === '') {
    header('Location: ../index.php');
    exit;
}

try {
    // Récupérer les crédits de l'utilisateur
    $otherProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'other_profile_pic');
    $credits_stmt = $pdo->prepare("
        SELECT total_credits, used_credits, remaining_credits 
        FROM message_credits 
        WHERE UPPER(TRIM(user_code)) = ?
    ");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    
    if (!$credits) {
        // Créer les crédits par défaut
        $pdo->prepare("INSERT INTO message_credits (user_code, total_credits, remaining_credits) VALUES (?, 100, 100)")->execute([$user_code]);
        $credits = ['total_credits' => 100, 'used_credits' => 0, 'remaining_credits' => 100];
    }
    
    // Conversations : LEFT JOIN users pour ne pas perdre les fils (ex. SYSTEM).
    // Tri type Telegram : d’abord les fils avec au moins un non lu (visibles dans la liste),
    // puis par récence d’activité (dernier message ou création du fil).
    $threads_stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.participant_1,
            t.participant_2,
            t.last_message_at,
            t.created_at AS thread_created_at,
            t.last_message_preview,
            CASE 
                WHEN UPPER(TRIM(t.participant_1)) = ? THEN t.participant_2 
                ELSE t.participant_1 
            END AS other_user_code,
            u.first_name AS other_first_name,
            u.last_name AS other_last_name,
            {$otherProfilePicSelect},
            (SELECT COUNT(*) FROM messages m 
             WHERE m.thread_id = t.id 
             AND UPPER(TRIM(m.recipient_user_code)) = ? 
             AND m.is_read = 0) AS unread_count
        FROM message_threads t
        LEFT JOIN users u ON UPPER(TRIM(u.user_code)) = UPPER(TRIM(CASE 
            WHEN UPPER(TRIM(t.participant_1)) = ? THEN t.participant_2 
            ELSE t.participant_1 
        END))
        WHERE UPPER(TRIM(t.participant_1)) = ? OR UPPER(TRIM(t.participant_2)) = ?
        ORDER BY 
            (SELECT COUNT(*) FROM messages m 
             WHERE m.thread_id = t.id 
             AND UPPER(TRIM(m.recipient_user_code)) = ? 
             AND m.is_read = 0) DESC,
            COALESCE(t.last_message_at, t.created_at) DESC
        LIMIT 80
    ");
    $threads_stmt->execute([
        $user_code,
        $user_code,
        $user_code,
        $user_code,
        $user_code,
        $user_code,
    ]);
    $threads = $threads_stmt->fetchAll();
    
    // Non lus : uniquement messages rattachés à une conversation (cohérent avec message_chat.php)
    $total_unread = gntoma_unread_messages_in_inbox_count($pdo, $user_code);
    
} catch (PDOException $e) {
    error_log("Erreur messagerie : " . $e->getMessage());
    $credits = ['total_credits' => 0, 'remaining_credits' => 0];
    $threads = [];
    $total_unread = 0;
}

$remaining_credits = (int) ($credits['remaining_credits'] ?? 0);
$no_message_credits = $remaining_credits < 1;

$success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="view-transition" content="same-origin">
    <meta name="theme-color" content="#dcd7cd">
    <title><?= htmlspecialchars(__('messages_list.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            -webkit-font-smoothing: antialiased;
            overscroll-behavior-y: none;
            background-color: #dcd7cd;
            background-image:
                radial-gradient(ellipse 100% 70% at 50% -15%, rgba(0, 122, 255, 0.08), transparent 52%),
                radial-gradient(ellipse 70% 50% at 100% 30%, rgba(124, 58, 237, 0.06), transparent 45%),
                repeating-linear-gradient(135deg, transparent, transparent 11px, rgba(255, 255, 255, 0.07) 11px, rgba(255, 255, 255, 0.07) 12px),
                radial-gradient(rgba(0, 0, 0, 0.04) 1px, transparent 1px);
            background-size: auto, auto, auto, 14px 14px;
            background-attachment: fixed;
        }
        .glass-panel { background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); border: 1px solid rgba(255, 255, 255, 0.85); box-shadow: 0 12px 40px rgba(15, 23, 42, 0.06); }
        .message-preview { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35; }
        .inbox-row { border-bottom: 1px solid rgba(0, 0, 0, 0.06); }
        .inbox-row:last-child { border-bottom: 0; }
        .inbox-thread-link { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        .inbox-shell { background: #fff; border-radius: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        @supports (view-transition-name: none) {
            ::view-transition-old(root), ::view-transition-new(root) {
                animation-duration: 0.28s;
                animation-timing-function: cubic-bezier(0.32, 0.72, 0, 1);
            }
        }
    </style>
</head>
<body class="min-h-[100dvh] min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="dashboard_6.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div class="text-center flex-1">
                <h1 class="text-lg font-bold text-dark"><?= htmlspecialchars(__('messages_list.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($total_unread > 0): ?>
                <span class="text-[10px] text-primary font-bold"><?= htmlspecialchars($total_unread === 1 ? __('messages_list.unread_one', ['n' => (string) $total_unread]) : __('messages_list.unread_many', ['n' => (string) $total_unread]), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-4 space-y-3">
        
        <?php if ($success === 'sent'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4 animate__animated animate__bounceIn">
            <p class="text-sm font-bold text-green-700 text-center"><?= htmlspecialchars(__('messages_list.sent_success'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php elseif ($success === 'credits_purchased'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center"><?= htmlspecialchars(__('messages_list.success_credits_purchased'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <?php if ($no_message_credits): ?>
        <div class="rounded-2xl border-2 border-amber-200 bg-amber-50/95 p-4 sm:p-5 shadow-sm space-y-4">
            <div class="flex gap-3">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-amber-100 flex items-center justify-center text-amber-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="min-w-0 space-y-2">
                    <h2 class="text-base font-black text-amber-950 leading-tight"><?= htmlspecialchars(__('messages_list.zero_credits_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-sm text-amber-900/95 leading-relaxed"><?= htmlspecialchars(__('messages_list.zero_credits_body'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-sm font-bold text-amber-950"><?= htmlspecialchars(__('messages_list.zero_credits_price'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <form action="messages_buy_process.php" method="POST" class="rounded-xl bg-white border border-amber-100/80 p-4 space-y-3">
                <input type="hidden" name="return" value="messages_list">
                <input type="hidden" name="pack_size" value="1000">
                <input type="hidden" name="pack_price" value="2">
                <p class="text-xs font-bold text-gray-600 uppercase tracking-wide"><?= htmlspecialchars(__('messages_list.zero_credits_pack_label'), ENT_QUOTES, 'UTF-8') ?></p>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="is_gift" value="1" class="mt-1 w-4 h-4 text-primary rounded border-gray-300" id="inline-gift-msgs">
                    <span class="text-sm text-gray-700"><?= htmlspecialchars(__('messages_list.zero_credits_gift'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <div id="inline-gift-recipient" class="hidden space-y-1">
                    <label for="inline-recipient-code" class="text-xs font-bold text-gray-500 uppercase"><?= htmlspecialchars(__('messages_list.zero_credits_recipient_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="inline-recipient-code" type="text" name="recipient_code" placeholder="<?= htmlspecialchars(__('messages_list.zero_credits_recipient_ph'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full uppercase bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary/30">
                </div>
                <button type="submit" class="w-full bg-primary text-white font-black py-3.5 rounded-xl shadow-lg shadow-primary/25 hover:bg-blue-600 transition-all text-sm">
                    <?= htmlspecialchars(__('messages_list.zero_credits_submit'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <p class="text-[11px] text-gray-500 text-center">
                    <a href="messages_buy.php" class="font-bold text-primary hover:underline"><?= htmlspecialchars(__('messages_list.zero_credits_more_packs'), ENT_QUOTES, 'UTF-8') ?></a>
                </p>
            </form>
            <script>
            (function () {
                var cb = document.getElementById('inline-gift-msgs');
                var box = document.getElementById('inline-gift-recipient');
                if (!cb || !box) return;
                function sync() { box.classList.toggle('hidden', !cb.checked); }
                cb.addEventListener('change', sync);
                sync();
            })();
            </script>
        </div>
        <?php endif; ?>

        <!-- Liste des conversations en premier (modèle type Telegram / WhatsApp) -->
        <div class="inbox-shell overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50/80">
                <h2 class="text-sm font-bold text-gray-700"><?= htmlspecialchars(__('messages_list.conversations'), ENT_QUOTES, 'UTF-8') ?></h2>
                <a href="message_send.php?composer=1" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary text-white shadow-md hover:bg-blue-600 transition-all" title="<?= htmlspecialchars(__('messages_list.new'), ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </a>
            </div>

            <?php if (empty($threads)): ?>
            <div class="text-center py-14 px-4">
                <div class="w-20 h-20 bg-gradient-to-br from-primary/15 to-primary/5 rounded-2xl flex items-center justify-center mx-auto mb-4 ring-1 ring-primary/10">
                    <svg class="h-10 w-10 text-primary/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <p class="text-gray-600 font-semibold"><?= htmlspecialchars(__('messages_list.empty'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-gray-400 text-sm mt-2"><?= htmlspecialchars(__('messages_list.empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                <a href="message_send.php" class="inline-flex mt-6 bg-primary text-white font-bold text-sm px-6 py-3 rounded-2xl shadow-lg shadow-blue-500/25 hover:bg-blue-600 transition-all">
                    <?= htmlspecialchars(__('messages_list.cta_start_chat'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100/90">
                <?php foreach ($threads as $thread): ?>
                <?php
                $ts = null;
                if (!empty($thread['last_message_at'])) {
                    $ts = strtotime((string) $thread['last_message_at']);
                } elseif (!empty($thread['thread_created_at'])) {
                    $ts = strtotime((string) $thread['thread_created_at']);
                }
                $timeLabel = '';
                if ($ts) {
                    $timeLabel = date('Y-m-d', $ts) === date('Y-m-d')
                        ? date('H:i', $ts)
                        : date('d/m/y', $ts);
                }
                ?>
                <a href="message_chat.php?thread=<?= (int) $thread['id'] ?>" class="inbox-thread-link inbox-row flex items-center gap-3 px-4 py-3.5 hover:bg-white/95 active:bg-gray-100/90 transition-colors <?= ($thread['unread_count'] ?? 0) > 0 ? 'bg-blue-50/70' : '' ?> <?= $no_message_credits ? 'opacity-95' : '' ?>">
                    <div class="relative flex-shrink-0">
                        <?php
                        $profile_pic = !empty($thread['other_profile_pic']) ? '../' . $thread['other_profile_pic'] : '../images/user_default.png';
                        ?>
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-14 h-14 rounded-2xl object-cover border border-white shadow-sm ring-1 ring-black/5">
                        <?php if (($thread['unread_count'] ?? 0) > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 min-w-[1.25rem] h-5 px-1 bg-primary text-white text-[10px] font-black rounded-full flex items-center justify-center ring-2 ring-white">
                            <?= (int) $thread['unread_count'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0 py-0.5">
                        <div class="flex items-start justify-between gap-2 mb-0.5">
                            <p class="font-bold text-dark text-[15px] leading-tight truncate">
                                <?php
                                $displayName = trim((string) ($thread['other_first_name'] ?? '') . ' ' . (string) ($thread['other_last_name'] ?? ''));
                                if ($displayName === '') {
                                    $ou = (string) ($thread['other_user_code'] ?? '');
                                    if (strtoupper($ou) === 'SYSTEM') {
                                        $displayName = __('messages_list.conv_system');
                                    } elseif ($ou !== '') {
                                        $displayName = $ou;
                                    } else {
                                        $displayName = __('messages_list.conv_unknown');
                                    }
                                }
                                ?>
                                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <?php if ($timeLabel !== ''): ?>
                            <span class="text-[11px] text-gray-400 font-semibold tabular-nums flex-shrink-0 pt-0.5"><?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[13px] text-gray-500 message-preview <?= ($thread['unread_count'] ?? 0) > 0 ? 'font-semibold text-gray-800' : '' ?>">
                            <?= htmlspecialchars($thread['last_message_preview'] ?? __('messages_list.new_thread_preview'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Crédits + actions secondaires (compact, sous la liste) -->
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-white/90 border border-gray-100 px-4 py-3 shadow-sm">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs text-gray-500 font-semibold"><?= htmlspecialchars(__('messages_list.credits_label'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-sm font-black text-dark tabular-nums"><?= number_format($credits['remaining_credits'] ?? 0) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <a href="messages_buy.php" class="text-xs font-bold text-primary hover:underline"><?= htmlspecialchars(__('messages_list.buy'), ENT_QUOTES, 'UTF-8') ?></a>
                <span class="text-gray-200">|</span>
                <a href="message_send.php?composer=1" class="text-xs font-bold text-dark hover:underline"><?= htmlspecialchars(__('messages_list.new'), ENT_QUOTES, 'UTF-8') ?></a>
                <span class="text-gray-200">|</span>
                <a href="message_bulk.php" class="text-xs font-bold text-orange-600 hover:underline"><?= htmlspecialchars(__('messages_list.group_50'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>

        <div class="flex gap-3">
            <a href="messages_blocked.php" class="flex-1 glass-panel rounded-2xl p-3 text-center hover:bg-white transition-all">
                <svg class="h-5 w-5 text-red-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <span class="text-xs font-bold text-gray-600"><?= htmlspecialchars(__('messages_list.blocked'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <a href="messages_filters.php" class="flex-1 glass-panel rounded-2xl p-3 text-center hover:bg-white transition-all">
                <svg class="h-5 w-5 text-green-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-xs font-bold text-gray-600"><?= htmlspecialchars(__('messages_list.filters'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        </div>

    </main>

</body>
</html>
