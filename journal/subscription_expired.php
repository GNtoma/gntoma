<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/subscription_expired.php
 * DESCRIPTION : Page pour les comptes expirés - affiche uniquement le message d'achat et le formulaire
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
    $stmt = $pdo->prepare("SELECT name, user_code, sub_expires_at FROM users WHERE user_code = ?");
    $stmt->execute([$user_code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: ../index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log('Erreur subscription_expired : ' . $e->getMessage());
    header("Location: ../index.php");
    exit;
}

// Calculer le temps depuis l'expiration
try {
    $expiry = new DateTime($user['sub_expires_at'] ?? '+48 hours');
    $now = new DateTime();
    $interval = $now->diff($expiry);
    $days_expired = $interval->days;
} catch (Throwable $e) {
    $days_expired = 0;
}

$payment_alert = '';
if (isset($_GET['error'])) {
    $errMap = [
        'payment_failed' => 'payment_failed',
        'payment_init_failed' => 'payment_init_failed',
        'invalid_target' => 'invalid_target',
        'curl_error' => 'curl_error',
        'http_error' => 'http_error',
        'flexpay_error' => 'flexpay_error',
    ];
    $code = (string) ($_GET['error'] ?? '');
    $msg = isset($errMap[$code])
        ? __('subscription_page.' . $errMap[$code])
        : __('subscription_page.payment_error_generic');
    $payment_alert = '<div class="bg-red-50 border border-red-200 text-red-800 text-sm font-bold p-4 rounded-2xl mb-6">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
} elseif (isset($_GET['success']) && $_GET['success'] === 'payment_success') {
    $payment_alert = '<div class="bg-green-50 border border-green-200 text-green-800 text-sm font-bold p-4 rounded-2xl mb-6">' . htmlspecialchars(__('subscription_page.success_html'), ENT_QUOTES, 'UTF-8') . ' <a href="dashboard_6.php" class="underline text-primary">' . htmlspecialchars(__('subscription_page.success_link'), ENT_QUOTES, 'UTF-8') . '</a></div>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('subscription_page.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
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
            background: linear-gradient(135deg, #F0F4F8 0%, #F5F5F7 100%);
            min-height: 100vh;
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(25px); 
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    
    <div class="glass-panel rounded-[2.5rem] shadow-2xl max-w-lg w-full p-8">
        <div class="flex justify-end mb-2"><?= gntoma_lang_switch_markup() ?></div>
        <!-- Icône d'alerte -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>

        <!-- Titre -->
        <h1 class="text-2xl font-black text-dark text-center mb-2"><?= htmlspecialchars(__('subscription_page.heading_expired'), ENT_QUOTES, 'UTF-8') ?></h1>
        <?= $payment_alert ?>
        <p class="text-gray-500 text-center text-sm mb-6">
            <?= htmlspecialchars(__('subscription_page.greeting', ['name' => (string) $user['name']]), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <!-- Info expiration -->
        <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-6">
            <p class="text-sm text-orange-800 text-center">
                <?php if ($days_expired > 0): ?>
                <?= htmlspecialchars(__('subscription_page.days_ago', ['days' => (string) $days_expired]), ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                <?= htmlspecialchars(__('subscription_page.expired_today'), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Message d'achat -->
        <p class="text-center text-dark font-medium mb-6">
            <?= htmlspecialchars(__('subscription_page.renew_pitch'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <!-- Formulaire d'achat -->
        <form action="payment_init_11.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(gntoma_payment_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('subscription_page.label_plan'), ENT_QUOTES, 'UTF-8') ?></label>
                <select name="forfait" class="w-full bg-white border border-gray-200 rounded-2xl py-4 px-4 font-bold text-dark outline-none focus:ring-2 focus:ring-primary cursor-pointer">
                    <option value="2"><?= htmlspecialchars(__('dashboard_body.plan_60'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="3"><?= htmlspecialchars(__('dashboard_body.plan_90'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>

            <button type="submit" class="w-full bg-primary text-white font-black py-4 rounded-2xl shadow-lg shadow-blue-500/20 active:scale-95 transition-all text-sm flex items-center justify-center space-x-2">
                <span><?= htmlspecialchars(__('subscription_page.pay_extend'), ENT_QUOTES, 'UTF-8') ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </form>

        <!-- Déconnexion -->
        <div class="mt-6 text-center">
            <a href="auth_logout_7.php" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                <?= htmlspecialchars(__('subscription_page.logout'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </div>

</body>
</html>
