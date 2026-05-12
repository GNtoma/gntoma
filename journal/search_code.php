<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/search_code.php
 * DESCRIPTION : Recherche par code A3 ou A3J2 - Affiche profil auteur ou journal spécifique
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$current_user_code = $_SESSION['user_id'];
$code = trim(strtoupper((string)($_GET['code'] ?? '')));

if (empty($code)) {
    header("Location: dashboard_6.php");
    exit;
}

// Parser le code : A3 ou A3J2
$is_journal_search = false;
$target_user_code = '';
$target_journal_num = 0;

if (preg_match('/^([A-Z]\d+)J(\d+)$/i', $code, $matches)) {
    // Format A3J2 - Recherche d'un journal spécifique
    $is_journal_search = true;
    $target_user_code = $matches[1];
    $target_journal_num = (int)$matches[2];
} elseif (preg_match('/^([A-Z]\d+)$/i', $code, $matches)) {
    // Format A3 - Recherche d'un auteur
    $target_user_code = $matches[1];
} else {
    header("Location: dashboard_6.php?error=invalid_code");
    exit;
}

try {
    // Récupérer les infos de l'auteur (insensible à la casse)
    $profilePicSelect = gntoma_users_profile_pic_expr($pdo, 'users', 'profile_pic');
    $author_stmt = $pdo->prepare("
        SELECT user_code, name, first_name, last_name, {$profilePicSelect}, 
               bio, city, country, profile_visibility,
               (SELECT COUNT(*) FROM journals WHERE user_code = users.user_code) as journal_count,
               (SELECT COUNT(*) FROM journal_views 
                JOIN journals ON journal_views.journal_id = journals.id 
                WHERE journals.user_code = users.user_code) as total_views
        FROM users 
        WHERE UPPER(user_code) = UPPER(?)
    ");
    $author_stmt->execute([$target_user_code]);
    $author = $author_stmt->fetch();

    if (!$author) {
        header("Location: dashboard_6.php?error=author_not_found");
        exit;
    }

    // Si recherche d'un journal spécifique
    $specific_journal = null;
    if ($is_journal_search) {
        $journal_stmt = $pdo->prepare("
            SELECT j.*, 
                   (SELECT COUNT(*) FROM journals 
                    WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM journals j
            WHERE j.user_code = ? 
            AND j.status IN ('public', 'paid')
            HAVING journal_num = ?
            LIMIT 1
        ");
        $journal_stmt->execute([$author['user_code'], $target_journal_num]);
        $specific_journal = $journal_stmt->fetch();

        if (!$specific_journal) {
            header("Location: dashboard_6.php?error=journal_not_found");
            exit;
        }
    }

    // Récupérer tous les journaux publics de l'auteur
    $journals_stmt = $pdo->prepare("
        SELECT j.*, 
               (SELECT COUNT(*) FROM journals 
                WHERE user_code = j.user_code AND id <= j.id) as journal_num,
               (SELECT COUNT(*) FROM journal_views WHERE journal_id = j.id) as view_count
        FROM journals j
        WHERE j.user_code = ? 
        AND j.status IN ('public', 'paid')
        ORDER BY j.created_at DESC
    ");
    $journals_stmt->execute([$author['user_code']]);
    $journals = $journals_stmt->fetchAll();

    // Vérifier si déjà bloqué
    $blocked_stmt = $pdo->prepare("
        SELECT COUNT(*) as is_blocked 
        FROM user_blocks 
        WHERE blocker_user_code = ? AND blocked_user_code = ?
    ");
    $blocked_stmt->execute([$current_user_code, $author['user_code']]);
    $is_blocked = $blocked_stmt->fetch()['is_blocked'] > 0;

    // Vérifier si conversation existe déjà
    $thread_stmt = $pdo->prepare("
        SELECT id FROM message_threads 
        WHERE (participant_1 = ? AND participant_2 = ?) 
           OR (participant_1 = ? AND participant_2 = ?)
        LIMIT 1
    ");
    $thread_stmt->execute([$current_user_code, $author['user_code'], $author['user_code'], $current_user_code]);
    $existing_thread = $thread_stmt->fetch();

} catch (PDOException $e) {
    error_log("Erreur recherche code GNTOMA : " . $e->getMessage());
    header("Location: dashboard_6.php?error=search_error");
    exit;
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($author['first_name'] . ' ' . $author['last_name']) ?> - GNTOMA</title>
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
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 10% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 10%, rgba(249, 115, 22, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(168, 85, 247, 0.08) 0px, transparent 50%),
                radial-gradient(at 10% 90%, rgba(59, 130, 246, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(25px); 
            -webkit-backdrop-filter: blur(25px);
        }
        .journal-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            border: 1px solid rgba(255,255,255,0.8);
        }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 glass-panel border-b border-gray-100 px-4 py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-2">
            <a href="dashboard_6.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all flex-shrink-0">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-lg font-bold text-dark flex-1 text-center"><?= htmlspecialchars(__('search_code.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-5">
        
        <?php if ($specific_journal): ?>
        <!-- Affichage du journal spécifique recherché -->
        <div class="bg-primary/10 border border-primary/20 rounded-[2rem] p-4">
            <p class="text-sm text-primary font-bold text-center">
                <?= htmlspecialchars(__('search_code.journal_requested', ['code' => $code]), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Carte Profil -->
        <div class="glass-panel rounded-[2.5rem] p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16"></div>
            
            <div class="flex items-start space-x-4 relative z-10">
                <?php 
                $profile_pic = !empty($author['profile_pic']) ? '../' . $author['profile_pic'] : '../images/user_default.png';
                ?>
                <img src="<?= htmlspecialchars($profile_pic) ?>" 
                     alt="" 
                     class="w-24 h-24 rounded-[2rem] object-cover border-4 border-white shadow-lg">
                
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-black text-dark leading-tight">
                        <?= htmlspecialchars($author['first_name'] . ' ' . $author['last_name']) ?>
                    </h2>
                    <p class="text-primary font-bold text-sm mt-1"><?= htmlspecialchars((string) $author['user_code'], ENT_QUOTES, 'UTF-8') ?></p>
                    
                    <?php if ($author['city'] || $author['country']): ?>
                    <p class="text-gray-500 text-xs mt-2 flex items-center">
                        <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        <?= htmlspecialchars(($author['city'] ? $author['city'] . ', ' : '') . $author['country']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($author['bio']): ?>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-sm text-gray-600 leading-relaxed">
                    <?= nl2br(htmlspecialchars($author['bio'])) ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3 mt-5 pt-5 border-t border-gray-100">
                <div class="text-center">
                    <p class="text-2xl font-black text-dark"><?= number_format($author['journal_count']) ?></p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider"><?= htmlspecialchars(__('search_code.stats_journals'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="text-center border-x border-gray-100">
                    <p class="text-2xl font-black text-dark"><?= number_format($author['total_views'] ?? 0) ?></p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider"><?= htmlspecialchars(__('search_code.stats_views'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-black text-primary"><?= count($journals) ?></p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider"><?= htmlspecialchars(__('search_code.stats_public'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 mt-5">
                <?php if ($author['user_code'] !== $current_user_code): ?>
                    <?php if ($is_blocked): ?>
                    <button disabled class="flex-1 bg-gray-100 text-gray-400 font-bold py-3 rounded-2xl text-sm">
                        <?= htmlspecialchars(__('search_code.user_blocked'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php else: ?>
                    <a href="message_send.php?to=<?= $author['user_code'] ?>" 
                       class="flex-1 bg-primary text-white font-bold py-3 rounded-2xl text-sm text-center hover:bg-blue-600 transition-all flex items-center justify-center space-x-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <span><?= htmlspecialchars(__('search_code.message'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($existing_thread): ?>
                    <a href="message_chat.php?thread=<?= $existing_thread['id'] ?>" 
                       class="flex-1 bg-dark text-white font-bold py-3 rounded-2xl text-sm text-center hover:bg-gray-800 transition-all">
                        <?= htmlspecialchars(__('search_code.conversation'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php else: ?>
                    <button type="button" onclick="alert(<?= json_encode(__('search_code.first_message_alert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)"
                            class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-2xl text-sm">
                        <?= htmlspecialchars(__('search_code.no_messages_hint'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="profile_edit.php" 
                       class="flex-1 bg-dark text-white font-bold py-3 rounded-2xl text-sm text-center hover:bg-gray-800 transition-all">
                        <?= htmlspecialchars(__('search_code.edit_profile'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Journal spécifique (si recherche A3J2) -->
        <?php if ($specific_journal): ?>
        <div class="space-y-4">
            <h3 class="text-sm font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('search_code.journal_found'), ENT_QUOTES, 'UTF-8') ?></h3>
            
            <div class="journal-card rounded-[2.5rem] overflow-hidden shadow-lg">
                <?php if (!empty($specific_journal['cover_image'])): ?>
                <div class="h-48 bg-gray-100 relative">
                    <img src="../<?= htmlspecialchars($specific_journal['cover_image']) ?>" 
                         alt="" class="w-full h-full object-cover">
                    <div class="absolute top-4 left-4">
                        <span class="px-3 py-1.5 text-xs font-black uppercase tracking-widest rounded-full bg-white/90 text-dark shadow-sm">
                            <?= $code ?>
                        </span>
                    </div>
                    <div class="absolute top-4 right-4">
                        <span class="px-3 py-1.5 text-xs font-black uppercase tracking-widest rounded-full <?= $specific_journal['status'] === 'paid' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' ?>">
                            <?= htmlspecialchars($specific_journal['status'] === 'paid' ? __('search_code.status_paid') : __('search_code.status_public'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="p-6">
                    <?php if (empty($specific_journal['cover_image'])): ?>
                    <div class="flex justify-between items-start mb-3">
                        <span class="px-3 py-1.5 text-xs font-black uppercase tracking-widest rounded-full bg-primary/10 text-primary">
                            <?= $code ?>
                        </span>
                        <span class="px-3 py-1.5 text-xs font-black uppercase tracking-widest rounded-full <?= $specific_journal['status'] === 'paid' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' ?>">
                            <?= htmlspecialchars($specific_journal['status'] === 'paid' ? __('search_code.status_paid') : __('search_code.status_public'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <h4 class="text-xl font-black text-dark mb-2"><?= htmlspecialchars($specific_journal['title']) ?></h4>
                    
                    <?php if ($specific_journal['keywords']): ?>
                    <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($specific_journal['keywords']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($specific_journal['status'] === 'paid' && $specific_journal['price']): ?>
                    <p class="text-2xl font-black text-orange-600 mb-4">
                        <?= number_format($specific_journal['price'], 0) ?> <?= $specific_journal['price_currency'] ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="flex gap-3">
                        <a href="journal_view.php?id=<?= $specific_journal['id'] ?>" 
                           class="flex-1 bg-primary text-white font-bold py-3 rounded-2xl text-center hover:bg-blue-600 transition-all">
                            <?= htmlspecialchars(__('search_code.view_journal'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php if ($author['user_code'] !== $current_user_code): ?>
                        <a href="message_send.php?to=<?= $author['user_code'] ?>" 
                           class="flex-1 bg-white border border-gray-200 text-dark font-bold py-3 rounded-2xl text-center hover:bg-gray-50 transition-all">
                            <?= htmlspecialchars(__('search_code.write_author'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($specific_journal['status'] === 'paid' && $author['user_code'] !== $current_user_code): ?>
                        <a href="journal_access_request.php?journal_id=<?= $specific_journal['id'] ?>" 
                           class="flex-1 bg-orange-100 text-orange-700 font-bold py-3 rounded-2xl text-center hover:bg-orange-200 transition-all">
                            <?= htmlspecialchars(__('search_code.request_access'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <hr class="border-gray-200 my-6">
        <?php endif; ?>

        <!-- Tous les journaux de l'auteur -->
        <div>
            <h3 class="text-sm font-black uppercase tracking-widest text-gray-400 ml-2 mb-4">
                <?= htmlspecialchars($specific_journal ? __('search_code.other_journals') : __('search_code.public_journals'), ENT_QUOTES, 'UTF-8') ?>
                <span class="text-primary">(<?= count($journals) ?>)</span>
            </h3>
            
            <?php if (empty($journals)): ?>
            <div class="bg-yellow-50 border-2 border-dashed border-yellow-200 rounded-[2.5rem] p-8 text-center">
                <p class="text-gray-500 font-medium"><?= htmlspecialchars(__('search_code.no_public_journals'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($journals as $journal): 
                    if ($specific_journal && $journal['id'] === $specific_journal['id']) continue; // Skip si déjà affiché en haut
                    
                    $journal_code = $author['user_code'] . 'J' . $journal['journal_num'];
                    $date_obj = new DateTime($journal['created_at']);
                    $monthKey = (string)(int)$date_obj->format('m');
                    $date_formatee = $date_obj->format('d') . ' ' . __('months.' . $monthKey) . ' ' . $date_obj->format('Y');
                ?>
                <?php if ($journal['status'] === 'paid' && $author['user_code'] !== $current_user_code): ?>
                <!-- Journal payant : carte avec boutons -->
                <div class="journal-card rounded-[2rem] p-4 sm:p-5 hover:shadow-md transition-all">
                    <div class="flex items-start space-x-3 sm:space-x-4 mb-3">
                        <?php if (!empty($journal['cover_image'])): ?>
                        <img src="../<?= htmlspecialchars($journal['cover_image']) ?>" 
                             alt="" class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-cover border border-gray-100 flex-shrink-0">
                        <?php else: ?>
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20 flex-shrink-0">
                            <svg class="h-7 w-7 sm:h-8 sm:w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-1.5 mb-1">
                                <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full bg-gray-100 text-gray-600">
                                    <?= $journal_code ?>
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full bg-orange-100 text-orange-600">
                                    <?= htmlspecialchars(__('search_code.status_paid'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full bg-orange-50 text-orange-700 border border-orange-200">
                                    <?= number_format($journal['price'] ?? 0, 0) ?> <?= $journal['price_currency'] ?? 'CDF' ?>
                                </span>
                            </div>
                            <h4 class="font-bold text-dark text-sm sm:text-base truncate"><?= htmlspecialchars($journal['title']) ?></h4>
                            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($date_formatee, ENT_QUOTES, 'UTF-8') ?> • <?= number_format($journal['view_count'] ?? 0) ?> <?= htmlspecialchars(__('search_code.views_suffix'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    
                    <a href="journal_access_request.php?journal_id=<?= $journal['id'] ?>" 
                       class="w-full bg-orange-500 text-white font-bold py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm text-center hover:bg-orange-600 transition-all flex items-center justify-center space-x-1.5 shadow-md shadow-orange-500/30">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span><?= htmlspecialchars(__('search_code.request_access'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </div>
                <?php else: ?>
                <!-- Journal public : toute la carte est cliquable et redirige vers le contenu -->
                <a href="journal_view.php?id=<?= $journal['id'] ?>" 
                   class="journal-card rounded-[2rem] p-4 sm:p-5 hover:shadow-lg hover:scale-[1.01] transition-all flex items-center space-x-3 sm:space-x-4 group">
                    <?php if (!empty($journal['cover_image'])): ?>
                    <img src="../<?= htmlspecialchars($journal['cover_image']) ?>" 
                         alt="" class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-cover border border-gray-100 flex-shrink-0">
                    <?php else: ?>
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20 flex-shrink-0">
                        <svg class="h-7 w-7 sm:h-8 sm:w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center flex-wrap gap-1.5 mb-1">
                            <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full bg-gray-100 text-gray-600">
                                <?= $journal_code ?>
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full bg-green-100 text-green-700">
                                <?= htmlspecialchars(__('search_code.status_public'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-dark text-sm sm:text-base truncate"><?= htmlspecialchars($journal['title']) ?></h4>
                        <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($date_formatee, ENT_QUOTES, 'UTF-8') ?> • <?= number_format($journal['view_count'] ?? 0) ?> <?= htmlspecialchars(__('search_code.views_suffix'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-primary/10 group-hover:bg-primary text-primary group-hover:text-white rounded-xl flex items-center justify-center transition-all flex-shrink-0">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>

</body>
</html>
