<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_requests_list.php
 * DESCRIPTION : Liste des demandes d'accès pour l'auteur (système D1, D2, Dn)
 * L'auteur peut voir toutes les demandes et les approuver/refuser
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$author_code = $_SESSION['user_id'];
$filter_status = $_GET['status'] ?? 'pending'; // pending, approved, rejected, all
$search_request = trim((string)($_GET['search'] ?? ''));

// Statistiques
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
try {
    $stats_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM access_requests 
        WHERE author_user_code = ? 
        GROUP BY status
    ");
    $stats_stmt->execute([$author_code]);
    while ($row = $stats_stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
} catch (PDOException $e) {
    error_log("Erreur stats demandes : " . $e->getMessage());
}

// Récupérer les demandes
$requests = [];
$table_missing = false;

try {
    // Vérifier si la table access_requests existe
    $check_table = $pdo->query("SHOW TABLES LIKE 'access_requests'");
    $table_exists = $check_table->fetchColumn() !== false;
    if (!$table_exists) {
        $table_missing = true;
    } else {
        $requesterProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'requester_profile_pic');
        $sql = "
            SELECT ar.*, j.title as journal_title, j.cover_image, j.price, j.price_currency,
                   u.first_name, u.last_name, {$requesterProfilePicSelect},
                   (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM access_requests ar
            JOIN journals j ON ar.journal_id = j.id
            JOIN users u ON ar.requester_user_code = u.user_code
            WHERE ar.author_user_code = ?
        ";
        $params = [$author_code];
        
        if ($filter_status !== 'all') {
            $sql .= " AND ar.status = ?";
            $params[] = $filter_status;
        }
        
        // Recherche par numéro de demande (D1, D15, etc.)
        if (!empty($search_request)) {
            $sql .= " AND ar.request_number LIKE ?";
            $params[] = '%' . $search_request . '%';
        }
        
        $sql .= " ORDER BY ar.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Erreur récupération demandes : " . $e->getMessage());
    $requests = [];
    $table_missing = true;
}

// Fonction pour formater la date
function formatDate(string $date): string {
    $dt = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($dt);

    if ($diff->days === 0) {
        if ($diff->h === 0) {
            return __('requests_list.time_minutes', ['m' => (string) $diff->i]);
        }

        return __('requests_list.time_hours', ['h' => (string) $diff->h]);
    }
    if ($diff->days === 1) {
        return __('requests_list.time_yesterday');
    }
    if ($diff->days < 7) {
        return __('requests_list.time_days', ['d' => (string) $diff->days]);
    }

    return $dt->format('d/m/Y');
}

// Fonction pour obtenir le numéro sans le D
function getRequestNumber(string $requestNumber): string {
    return preg_replace('/[^0-9]/', '', $requestNumber);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('requests_list.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.05); }
        .input-lucide { background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        .input-lucide:focus { background: #fff; border-color: #007AFF; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1); outline: none; }
        
        /* Mobile-first improvements */
        @media (max-width: 640px) {
            .glass-panel { border-radius: 1.5rem; padding: 1rem; }
            .text-2xl { font-size: 1.25rem; }
            .text-xl { font-size: 1.125rem; }
            .p-12 { padding: 2rem 1rem; }
            .py-8 { padding-top: 1rem; padding-bottom: 1rem; }
            .px-4 { padding-left: 0.75rem; padding-right: 0.75rem; }
        }
    </style>
</head>
<body class="min-h-screen pb-20">

    <!-- Header sticky moderne -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-3 sm:px-4 py-3 sm:py-4">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-2">
            <a href="dashboard_6.php" class="w-9 h-9 sm:w-10 sm:h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-base sm:text-lg font-bold text-dark flex-1 text-center"><?= htmlspecialchars(__('requests_list.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-3 sm:px-4 py-4 sm:py-6">

        <?php if ($table_missing): ?>
        <!-- Warning: Table not found -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 mb-6">
            <div class="flex items-center space-x-3">
                <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-sm text-yellow-800">
                    <span class="font-bold"><?= htmlspecialchars(__('requests_list.migration_warning'), ENT_QUOTES, 'UTF-8') ?></span> <?= htmlspecialchars(__('requests_list.migration_body'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-4 sm:mb-6">
            <a href="?status=pending" class="glass-panel rounded-xl sm:rounded-2xl p-3 sm:p-4 text-center transition-all <?= $filter_status === 'pending' ? 'ring-2 ring-orange-500' : 'hover:ring-1 hover:ring-orange-300' ?>">
                <p class="text-xl sm:text-2xl font-black text-orange-600"><?= $stats['pending'] ?></p>
                <p class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide"><?= htmlspecialchars(__('requests_list.stat_pending'), ENT_QUOTES, 'UTF-8') ?></p>
            </a>
            <a href="?status=approved" class="glass-panel rounded-xl sm:rounded-2xl p-3 sm:p-4 text-center transition-all <?= $filter_status === 'approved' ? 'ring-2 ring-green-500' : 'hover:ring-1 hover:ring-green-300' ?>">
                <p class="text-xl sm:text-2xl font-black text-green-600"><?= $stats['approved'] ?></p>
                <p class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide"><?= htmlspecialchars(__('requests_list.stat_approved'), ENT_QUOTES, 'UTF-8') ?></p>
            </a>
            <a href="?status=rejected" class="glass-panel rounded-xl sm:rounded-2xl p-3 sm:p-4 text-center transition-all <?= $filter_status === 'rejected' ? 'ring-2 ring-red-500' : 'hover:ring-1 hover:ring-red-300' ?>">
                <p class="text-xl sm:text-2xl font-black text-red-600"><?= $stats['rejected'] ?></p>
                <p class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide"><?= htmlspecialchars(__('requests_list.stat_rejected'), ENT_QUOTES, 'UTF-8') ?></p>
            </a>
            <a href="?status=all" class="glass-panel rounded-xl sm:rounded-2xl p-3 sm:p-4 text-center transition-all <?= $filter_status === 'all' ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-blue-300' ?>">
                <p class="text-xl sm:text-2xl font-black text-dark"><?= $stats['total'] ?></p>
                <p class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide"><?= htmlspecialchars(__('requests_list.stat_total'), ENT_QUOTES, 'UTF-8') ?></p>
            </a>
        </div>

        <!-- Search -->
        <div class="glass-panel rounded-xl sm:rounded-2xl p-3 sm:p-4 mb-4 sm:mb-6">
            <form method="GET" class="flex items-center gap-2 sm:gap-3">
                <input type="hidden" name="status" value="<?= $filter_status ?>">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-3 sm:left-4 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_request) ?>" 
                           placeholder="<?= htmlspecialchars(__('requests_list.search_placeholder'), ENT_QUOTES, 'UTF-8') ?>" 
                           class="w-full input-lucide rounded-xl py-2.5 sm:py-3 pl-10 sm:pl-12 pr-3 font-medium text-xs sm:text-sm">
                </div>
                <button type="submit" class="bg-dark text-white font-bold py-2.5 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-black transition-all text-xs sm:text-sm">
                    <?= htmlspecialchars(__('requests_list.search_btn'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>
        </div>

        <!-- Requests List -->
        <?php if (empty($requests)): ?>
            <div class="glass-panel rounded-[1.5rem] sm:rounded-[2.5rem] p-8 sm:p-12 text-center">
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-10 sm:w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-lg sm:text-xl font-black text-dark mb-2"><?= htmlspecialchars(__('requests_list.empty_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="text-gray-500 text-xs sm:text-sm"><?= htmlspecialchars($filter_status === 'pending' ? __('requests_list.empty_text_pending') : __('requests_list.empty_text_other'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php else: ?>
            <div class="space-y-3 sm:space-y-4">
                <?php foreach ($requests as $request): 
                    $journal_code = $author_code . 'J' . $request['journal_num'];
                    $status_colors = [
                        'pending' => 'bg-orange-100 text-orange-700 border-orange-200',
                        'approved' => 'bg-green-100 text-green-700 border-green-200',
                        'rejected' => 'bg-red-100 text-red-700 border-red-200'
                    ];
                    $status_labels = [
                        'pending' => __('requests_list.status_pending'),
                        'approved' => __('requests_list.status_approved'),
                        'rejected' => __('requests_list.status_rejected'),
                    ];
                    $badge_color = $request['status'] === 'pending' ? 'bg-orange-500' : ($request['status'] === 'approved' ? 'bg-green-500' : 'bg-red-500');
                ?>
                    <div class="glass-panel rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4">
                            
                            <div class="flex items-start gap-3 sm:gap-4 flex-1">
                                <!-- Request Number Badge -->
                                <div class="flex-shrink-0 w-14 h-14 sm:w-16 sm:h-16 <?= $badge_color ?> rounded-xl sm:rounded-2xl flex flex-col items-center justify-center text-white shadow-lg">
                                    <span class="text-[8px] sm:text-xs font-bold uppercase"><?= htmlspecialchars(__('requests_list.badge_request'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-lg sm:text-2xl font-black leading-none"><?= getRequestNumber($request['request_number']) ?></span>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center flex-wrap gap-1.5 mb-1">
                                        <span class="px-2 py-0.5 text-[9px] sm:text-[10px] font-black uppercase tracking-wider rounded-full <?= $status_colors[$request['status']] ?>">
                                            <?= htmlspecialchars($status_labels[$request['status']] ?? $request['status'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="text-[10px] sm:text-xs text-gray-400"><?= formatDate($request['created_at']) ?></span>
                                    </div>
                                    
                                    <?php
                                        $requesterDisplayName = trim((string)($request['first_name'] ?? '') . ' ' . (string)($request['last_name'] ?? ''));
                                        if ($requesterDisplayName === '') {
                                            $requesterDisplayName = (string) $request['requester_user_code'];
                                        }
                                        $requesterProfilePic = !empty($request['requester_profile_pic']) ? '../' . $request['requester_profile_pic'] : '../images/user_default.png';
                                    ?>
                                    <div class="flex items-center gap-2 mt-1">
                                        <img src="<?= htmlspecialchars($requesterProfilePic) ?>" alt="" class="w-7 h-7 rounded-lg object-cover border border-gray-100">
                                        <p class="text-xs sm:text-sm text-gray-500">
                                        <span class="font-bold text-dark"><?= htmlspecialchars($requesterDisplayName) ?></span>
                                        <span class="text-[10px] text-primary font-black ml-1"><?= htmlspecialchars($request['requester_user_code']) ?></span>
                                        <?= htmlspecialchars(__('requests_list.requests_access'), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                    
                                    <p class="font-bold text-dark text-sm sm:text-base mt-0.5 truncate"><?= htmlspecialchars($request['journal_title']) ?> 
                                        <span class="text-primary text-xs sm:text-sm">(<?= $journal_code ?>)</span>
                                    </p>
                                    
                                    <?php if (!empty($request['message'])): ?>
                                        <p class="text-xs sm:text-sm text-gray-600 mt-2 italic line-clamp-2">"<?= htmlspecialchars($request['message']) ?>"</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($request['response_message'])): ?>
                                        <p class="text-xs sm:text-sm text-blue-600 mt-2 line-clamp-2"><?= htmlspecialchars(__('requests_list.your_reply'), ENT_QUOTES, 'UTF-8') ?> "<?= htmlspecialchars($request['response_message']) ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <?php if ($request['status'] === 'pending'): ?>
                                <div class="flex gap-2 md:flex-col md:gap-2 flex-shrink-0">
                                    <a href="message_send.php?to=<?= urlencode($request['requester_user_code']) ?>&context=access_request&request_id=<?= (int)$request['id'] ?>&journal_id=<?= (int)$request['journal_id'] ?>"
                                       class="flex-1 md:flex-none bg-blue-100 text-blue-700 font-bold py-2 px-4 rounded-xl hover:bg-blue-200 transition-all text-xs sm:text-sm text-center flex items-center justify-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <?= htmlspecialchars(__('requests_list.discuss'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <a href="journal_request_approve.php?id=<?= $request['id'] ?>&action=approve"
                                       class="flex-1 md:flex-none bg-green-500 text-white font-bold py-2 px-4 rounded-xl hover:bg-green-600 transition-all text-xs sm:text-sm text-center shadow-md shadow-green-500/30">
                                        <?= htmlspecialchars(__('requests_list.accept'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <a href="journal_request_approve.php?id=<?= $request['id'] ?>&action=reject"
                                       class="flex-1 md:flex-none bg-red-100 text-red-600 font-bold py-2 px-4 rounded-xl hover:bg-red-200 transition-all text-xs sm:text-sm text-center">
                                        <?= htmlspecialchars(__('requests_list.reject'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </div>
                            <?php elseif ($request['status'] === 'rejected'): ?>
                                <div class="flex gap-2 md:flex-col md:gap-2 flex-shrink-0">
                                    <a href="message_send.php?to=<?= urlencode($request['requester_user_code']) ?>&context=access_request&request_id=<?= (int)$request['id'] ?>&journal_id=<?= (int)$request['journal_id'] ?>"
                                       class="flex-1 md:flex-none bg-blue-100 text-blue-700 font-bold py-2 px-4 rounded-xl hover:bg-blue-200 transition-all text-xs sm:text-sm text-center flex items-center justify-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <?= htmlspecialchars(__('requests_list.discuss'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <a href="journal_request_approve.php?id=<?= $request['id'] ?>&action=reactivate"
                                       class="flex-1 md:flex-none bg-orange-500 text-white font-bold py-2 px-4 rounded-xl hover:bg-orange-600 transition-all text-xs sm:text-sm text-center shadow-md shadow-orange-500/30">
                                        <?= htmlspecialchars(__('requests_list.reactivate'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <a href="journal_request_approve.php?id=<?= $request['id'] ?>&action=view"
                                       class="flex-1 md:flex-none bg-gray-100 text-dark font-bold py-2 px-4 rounded-xl hover:bg-gray-200 transition-all text-xs sm:text-sm text-center">
                                        <?= htmlspecialchars(__('requests_list.view_details'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="flex gap-2 md:flex-col md:gap-2 flex-shrink-0">
                                    <a href="message_send.php?to=<?= urlencode($request['requester_user_code']) ?>&context=access_request&request_id=<?= (int)$request['id'] ?>&journal_id=<?= (int)$request['journal_id'] ?>"
                                       class="flex-1 md:flex-none bg-blue-100 text-blue-700 font-bold py-2 px-4 rounded-xl hover:bg-blue-200 transition-all text-xs sm:text-sm text-center flex items-center justify-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <?= htmlspecialchars(__('requests_list.discuss'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <a href="journal_request_approve.php?id=<?= $request['id'] ?>&action=view"
                                       class="flex-1 md:flex-none bg-gray-100 text-dark font-bold py-2 px-4 rounded-xl hover:bg-gray-200 transition-all text-xs sm:text-sm text-center">
                                        <?= htmlspecialchars(__('requests_list.view_details'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
