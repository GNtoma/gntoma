<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$success = '';
$error = '';

// Traitement des actions (accepter/rejeter)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer la demande
        $stmt = $pdo->prepare("
            SELECT fr.*, u.first_name, u.last_name 
            FROM follow_requests fr
            LEFT JOIN users u ON u.user_code = fr.requester_user_code
            WHERE fr.id = ? AND fr.followed_user_code = ?
        ");
        $stmt->execute([$request_id, $user_code]);
        $request = $stmt->fetch();
        
        if (!$request) {
            throw new Exception(__('follow_requests_list.err_not_found'));
        }
        
        if ($action === 'accept') {
            // Mettre à jour le statut de la demande
            $update_stmt = $pdo->prepare("
                UPDATE follow_requests 
                SET status = 'accepted', updated_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$request_id]);
            
            // Ajouter dans author_follows
            $follow_stmt = $pdo->prepare("
                INSERT INTO author_follows (follower_user_code, followed_user_code, followed_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE followed_at = NOW()
            ");
            $follow_stmt->execute([$request['requester_user_code'], $user_code]);
            
            $success = __('follow_requests_list.success_accept');
        } elseif ($action === 'reject') {
            // Mettre à jour le statut de la demande
            $update_stmt = $pdo->prepare("
                UPDATE follow_requests 
                SET status = 'rejected', updated_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$request_id]);
            
            $success = __('follow_requests_list.success_reject');
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur action follow request : " . $e->getMessage());
        $error = __('follow_requests_list.err_process');
    }
}

// Récupérer les demandes de suivi
$pending_requests = [];
$other_requests = [];

try {
    // Demandes en attente
    $stmt = $pdo->prepare("
        SELECT fr.*, u.first_name, u.last_name, u.profile_pic
        FROM follow_requests fr
        LEFT JOIN users u ON u.user_code = fr.requester_user_code
        WHERE fr.followed_user_code = ? AND fr.status = 'pending'
        ORDER BY fr.created_at DESC
    ");
    $stmt->execute([$user_code]);
    $pending_requests = $stmt->fetchAll();
    
    // Autres demandes (acceptées/rejetées)
    $stmt = $pdo->prepare("
        SELECT fr.*, u.first_name, u.last_name, u.profile_pic
        FROM follow_requests fr
        LEFT JOIN users u ON u.user_code = fr.requester_user_code
        WHERE fr.followed_user_code = ? AND fr.status != 'pending'
        ORDER BY fr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_code]);
    $other_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur récupération follow requests : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('follow_requests_list.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
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
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); }
        
        /* Table styles pour meilleur alignement */
        table { width: 100%; border-collapse: separate; border-spacing: 0 0.5rem; }
        th { text-align: left; padding: 0.5rem 1rem; color: #9ca3af; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; }
        td { padding: 1rem; background: #f9fafb; }
        tr td:first-child { border-top-left-radius: 1rem; border-bottom-left-radius: 1rem; }
        tr td:last-child { border-top-right-radius: 1rem; border-bottom-right-radius: 1rem; }
        
        /* Mobile-first improvements */
        @media (max-width: 640px) {
            .glass-panel { border-radius: 1.5rem; padding: 1rem; }
            .text-2xl { font-size: 1.25rem; }
            .text-lg { font-size: 1rem; }
            .p-5 { padding: 0.875rem; }
            .p-6 { padding: 1rem; }
            .py-4 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
            .px-4 { padding-left: 0.75rem; padding-right: 0.75rem; }
            .py-3 { padding-top: 0.625rem; padding-bottom: 0.625rem; }
            .space-y-4 > div { margin-bottom: 0.875rem; }
            .gap-3 { gap: 0.75rem; }
            
            /* Responsive table for mobile */
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 1rem; background: #f9fafb; border-radius: 1rem; overflow: hidden; }
            td { border: none; position: relative; padding-left: 50%; padding-top: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6; }
            td:last-child { border-bottom: none; }
            td:before { position: absolute; top: 0.75rem; left: 1rem; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: 800; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; }
            td:nth-of-type(1):before { content: "<?= htmlspecialchars(__('follow_requests_list.th_user'), ENT_QUOTES, 'UTF-8') ?>"; }
            td:nth-of-type(2):before { content: "<?= htmlspecialchars(__('follow_requests_list.th_request'), ENT_QUOTES, 'UTF-8') ?>"; }
            td:nth-of-type(3):before { content: "<?= htmlspecialchars(__('follow_requests_list.th_date'), ENT_QUOTES, 'UTF-8') ?>"; }
            td:nth-of-type(4):before { content: "<?= htmlspecialchars(__('follow_requests_list.th_status'), ENT_QUOTES, 'UTF-8') ?>"; }
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
            <h1 class="text-base sm:text-lg font-bold text-dark flex-1 text-center"><?= htmlspecialchars(__('follow_requests_list.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-3 sm:space-y-4">

        <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-center text-sm font-bold">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 text-green-700 p-4 rounded-2xl text-center text-sm font-bold">
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <!-- Demandes en attente -->
        <?php if (!empty($pending_requests)): ?>
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4"><?= htmlspecialchars(__('follow_requests_list.pending_title', ['count' => (string) count($pending_requests)]), ENT_QUOTES, 'UTF-8') ?></h2>
            
            <div class="space-y-3 sm:space-y-4">
                <?php foreach ($pending_requests as $request): 
                    $profile_pic = !empty($request['profile_pic']) ? '../' . $request['profile_pic'] : '../images/user_default.png';
                    $name = htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
                ?>
                <div class="bg-purple-50 border border-purple-200 rounded-[1.25rem] sm:rounded-xl p-3 sm:p-4">
                    <div class="flex items-start space-x-3">
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl object-cover border-2 border-white shadow-md flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-bold text-dark text-sm sm:text-base truncate"><?= $name ?></h3>
                                <span class="bg-purple-600 text-white text-[10px] sm:text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0 ml-2">
                                    <?= htmlspecialchars($request['request_number']) ?>
                                </span>
                            </div>
                            <?php if ($request['message']): ?>
                            <p class="text-xs text-gray-600 mb-3 italic">"<?= htmlspecialchars($request['message']) ?>"</p>
                            <?php endif; ?>
                            <div class="flex gap-2">
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <button type="submit" class="w-full bg-green-500 text-white font-bold py-2 sm:py-2.5 px-3 sm:px-4 rounded-xl text-xs sm:text-sm hover:bg-green-600 transition-all">
                                        <?= htmlspecialchars(__('follow_requests_list.accept'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <button type="submit" class="w-full bg-red-100 text-red-600 font-bold py-2 sm:py-2.5 px-3 sm:px-4 rounded-xl text-xs sm:text-sm hover:bg-red-200 transition-all">
                                        <?= htmlspecialchars(__('follow_requests_list.reject'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-5 text-center">
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-purple-100 rounded-[1.5rem] sm:rounded-[2rem] flex items-center justify-center mx-auto mb-4">
                <svg class="h-8 w-8 sm:h-10 sm:w-10 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
            </div>
            <h2 class="text-lg sm:text-xl font-black text-dark mb-2"><?= htmlspecialchars(__('follow_requests_list.empty_pending'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-gray-500 text-xs sm:text-sm mb-4"><?= htmlspecialchars(__('follow_requests_list.empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <!-- Historique des demandes -->
        <?php if (!empty($other_requests)): ?>
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4"><?= htmlspecialchars(__('follow_requests_list.history'), ENT_QUOTES, 'UTF-8') ?></h2>
            
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(__('follow_requests_list.th_user'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('follow_requests_list.th_request'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('follow_requests_list.th_date'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(__('follow_requests_list.th_status'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($other_requests as $request): 
                            $profile_pic = !empty($request['profile_pic']) ? '../' . $request['profile_pic'] : '../images/user_default.png';
                            $name = htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
                            $status_class = $request['status'] === 'accepted' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                            $status_text = $request['status'] === 'accepted'
                                ? __('follow_requests_list.status_accepted')
                                : __('follow_requests_list.status_rejected');
                        ?>
                        <tr>
                            <td>
                                <div class="flex items-center space-x-3">
                                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl object-cover border-2 border-white shadow-sm flex-shrink-0">
                                    <span class="font-bold text-dark text-xs sm:text-sm truncate"><?= $name ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs font-bold text-gray-600 bg-gray-100 px-2 py-1 rounded-lg">
                                    <?= htmlspecialchars($request['request_number']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-xs text-gray-500 font-medium">
                                    <?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?= $status_class ?> text-[10px] sm:text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap">
                                    <?= htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>

</body>
</html>
