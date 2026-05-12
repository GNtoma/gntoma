<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/following_feed.php
 * DESCRIPTION : Affiche les journaux des auteurs suivis
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];

try {
    $profilePicSelect = gntoma_users_profile_pic_expr($pdo, 'users', 'profile_pic');
    
    // Récupérer les auteurs suivis
    $follows_stmt = $pdo->prepare("
        SELECT u.user_code, u.name, u.first_name, u.last_name, {$profilePicSelect}, u.bio, u.city, u.country, af.created_at as followed_at
        FROM author_follows af
        JOIN users u ON u.user_code = af.followed_user_code
        WHERE af.follower_user_code = ?
        ORDER BY af.created_at DESC
    ");
    $follows_stmt->execute([$user_code]);
    $follows = $follows_stmt->fetchAll();
    
    // Récupérer les crédits messages
    $credits_stmt = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ?");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    $remaining_credits = $credits['remaining_credits'] ?? 0;
    
} catch (PDOException $e) {
    error_log('Erreur following_feed : ' . $e->getMessage());
    $follows = [];
    $remaining_credits = 0;
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(__('following_feed.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
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
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); }
        
        /* Mobile-first improvements */
        @media (max-width: 640px) {
            .glass-panel { border-radius: 1.5rem; padding: 1rem; }
            .text-xl { font-size: 1.125rem; }
            .p-8 { padding: 1.25rem; }
            .p-5 { padding: 0.75rem; }
            .py-4 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
            .px-4 { padding-left: 0.75rem; padding-right: 0.75rem; }
            .py-3 { padding-top: 0.625rem; padding-bottom: 0.625rem; }
        }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-3 sm:px-4 py-3 sm:py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-2">
            <a href="dashboard_6.php" class="w-9 h-9 sm:w-10 sm:h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-base sm:text-lg font-bold text-dark flex-1 text-center"><?= htmlspecialchars(__('following_feed.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-3 sm:space-y-4">
        
        <?php if (empty($follows)): ?>
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2.5rem] p-5 sm:p-8 text-center">
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-[1.5rem] sm:rounded-[2rem] flex items-center justify-center mx-auto mb-4">
                <svg class="h-8 w-8 sm:h-10 sm:w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
            </div>
            <h2 class="text-lg sm:text-xl font-black text-dark mb-2"><?= htmlspecialchars(__('following_feed.empty_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-gray-500 text-xs sm:text-sm mb-4"><?= htmlspecialchars(__('following_feed.empty_text'), ENT_QUOTES, 'UTF-8') ?></p>
            <a href="search_code.php" class="inline-block bg-primary text-white font-bold py-2.5 sm:py-3 px-5 sm:px-6 rounded-2xl text-xs sm:text-sm hover:bg-blue-600 transition-all">
                <?= htmlspecialchars(__('following_feed.cta_search'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
        <?php else: ?>
        
        <?php foreach ($follows as $follow): 
            $profile_pic = !empty($follow['profile_pic']) ? '../' . $follow['profile_pic'] : '../images/user_default.png';
            $follow_date = new DateTime($follow['followed_at']);
            $monthKey = (string) (int) $follow_date->format('m');
            $followed_since = $follow_date->format('d') . ' ' . __('months.' . $monthKey);
        ?>
        
        <!-- Carte auteur -->
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2.5rem] p-4 sm:p-5">
            <div class="flex items-center space-x-3 sm:space-x-4">
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-12 h-12 sm:w-16 sm:h-16 rounded-[1rem] sm:rounded-[1.5rem] object-cover border-2 border-white shadow-md">
                <div class="flex-1 min-w-0">
                    <h3 class="font-black text-dark text-sm sm:text-base truncate"><?= htmlspecialchars($follow['first_name'] . ' ' . $follow['last_name']) ?></h3>
                    <p class="text-primary font-bold text-xs sm:text-sm"><?= htmlspecialchars($follow['user_code']) ?></p>
                    <?php if ($follow['city']): ?>
                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($follow['city']) ?><?= !empty($follow['country']) ? ', ' . htmlspecialchars($follow['country']) : '' ?></p>
                    <?php endif; ?>
                    <p class="text-[10px] sm:text-xs text-gray-400 mt-1"><?= htmlspecialchars(__('following_feed.followed_since', ['date' => $followed_since]), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <a href="search_code.php?code=<?= htmlspecialchars($follow['user_code']) ?>" 
                   class="bg-primary text-white font-bold py-2 sm:py-2.5 px-3 sm:px-4 rounded-xl text-xs sm:text-sm hover:bg-blue-600 transition-all flex-shrink-0">
                    <?= htmlspecialchars(__('following_feed.view_journals'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
        
        <?php endforeach; ?>
        <?php endif; ?>
        
    </main>

</body>
</html>
