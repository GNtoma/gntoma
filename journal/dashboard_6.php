<?php
declare(strict_types=1);
session_start();
require_once 'config.php';

// 1. SÉCURITÉ : Redirection si non connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$dashboardFatalError = '';

try {
    $profilePicSelect = 'NULL AS profile_pic';

    try {
        $profilePicSelect = gntoma_users_profile_pic_expr($pdo, 'users', 'profile_pic');
    } catch (Throwable $e) {
        error_log('Erreur helper profile pic dashboard GNTOMA : ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("SELECT name, user_code, sub_expires_at, access_request_credits, {$profilePicSelect} FROM users WHERE user_code = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'profile_pic') === false) {
            throw $e;
        }

        error_log('Fallback profile pic dashboard GNTOMA : ' . $e->getMessage());
        $stmt = $pdo->prepare("SELECT name, user_code, sub_expires_at, access_request_credits, NULL AS profile_pic FROM users WHERE user_code = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        header("Location: auth_logout_7.php");
        exit;
    }

    try {
        gntoma_ensure_message_credits($pdo, (string) $user['user_code'], 100);
    } catch (Throwable $e) {
        error_log('Erreur crédits dashboard GNTOMA : ' . $e->getMessage());
    }

    $rawExpiry = trim((string) ($user['sub_expires_at'] ?? ''));
    $now = new DateTime();

    try {
        $expiry = new DateTime($rawExpiry !== '' ? $rawExpiry : '+48 hours');
    } catch (Throwable $e) {
        error_log('Erreur date dashboard GNTOMA : ' . $e->getMessage());
        $expiry = (new DateTime())->modify('+48 hours');
    }

    // Si l'abonnement est expiré, rediriger vers la page d'achat
    if ($now > $expiry) {
        header("Location: subscription_expired.php");
        exit;
    }

    $time_remaining = $now->diff($expiry)->format('%a j %h h');
} catch (Throwable $e) {
    error_log('Erreur dashboard GNTOMA : ' . $e->getMessage());

    $user = [
        'name' => (string) ($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Utilisateur'),
        'user_code' => (string) $_SESSION['user_id'],
        'profile_pic' => null,
    ];
    $time_remaining = 'Indisponible';
    $dashboardFatalError = "Certaines données du tableau de bord n'ont pas pu être chargées.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GNTOMA - Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#007AFF', dark: '#1D1D1F' }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            -webkit-font-smoothing: antialiased;
            color: #1D1D1F;
            position: relative;
            min-height: 100vh;
            /* Fond style Telegram : mesh gradient doux avec blobs colorés */
            background:
                radial-gradient(ellipse at 15% 15%, rgba(0, 122, 255, 0.12) 0%, transparent 50%),
                radial-gradient(ellipse at 85% 25%, rgba(162, 89, 255, 0.10) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(255, 149, 0, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 25% 70%, rgba(0, 200, 180, 0.07) 0%, transparent 45%),
                radial-gradient(ellipse at 75% 55%, rgba(255, 59, 130, 0.06) 0%, transparent 40%),
                linear-gradient(160deg, #EEF2FF 0%, #F0F9FF 30%, #FDF4FF 60%, #FFF7ED 100%);
        }
        /* Motif de points subtil style Telegram wallpaper */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-image:
                radial-gradient(rgba(0, 122, 255, 0.08) 1.2px, transparent 1.2px);
            background-size: 28px 28px;
            pointer-events: none;
            z-index: -1;
        }
        /* Overlay de brume légère pour adoucir */
        body::after {
            content: '';
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background:
                radial-gradient(circle at 50% 50%, rgba(255,255,255,0.4) 0%, transparent 70%);
            pointer-events: none;
            z-index: -1;
        }
        .journal-card-pattern {
            background: 
                linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23007AFF' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .ios-blur { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px); 
            border-bottom: 1px solid rgba(0,0,0,0.05); 
        }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="pb-12 gntoma-page-enter">
    
    <header class="ios-blur sticky top-0 z-50 px-4 py-2.5 shadow-sm w-full">
        <div class="max-w-5xl mx-auto flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="gntoma-chip rounded-2xl px-2.5 py-1.5 flex items-center justify-center">
                    <img src="../images/logo.png" alt="GNTOMA" class="h-6 w-auto">
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm font-black text-dark truncate leading-tight"><?= htmlspecialchars($user['name']) ?></h1>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="inline-flex items-center gap-1 bg-primary/10 text-primary text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <?= htmlspecialchars($user['user_code']) ?>
                        </span>
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-[9px] font-bold px-2 py-0.5 rounded-full">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?= htmlspecialchars($time_remaining) ?>
                        </span>
                        <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-600 text-[9px] font-bold px-2 py-0.5 rounded-full">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            <?= (int)($user['access_request_credits'] ?? 100) ?>
                        </span>
                    </div>
                </div>
            </div>
            <a href="auth_logout_7.php" class="w-9 h-9 bg-white/80 border border-gray-100 rounded-full flex items-center justify-center text-red-400 hover:bg-red-50 hover:border-red-100 hover:text-red-600 active:scale-95 transition-all shadow-sm flex-shrink-0" title="Quitter">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </a>
        </div>
    </header>

    <main class="w-full px-4 pt-5 pb-10 sm:pt-6">
        <div class="max-w-5xl mx-auto">
            <?php if ($dashboardFatalError !== ''): ?>
            <div class="bg-red-50 border border-red-100 text-red-600 text-sm p-5 rounded-[2rem] font-medium mb-4">
                <?= htmlspecialchars($dashboardFatalError) ?>
            </div>
            <?php endif; ?>


            <?php
            $dashboardBodyHtml = '';
            $dashboardBufferLevel = ob_get_level();

            try {
                ob_start();
                include 'dashboard_b_6.php';
                $dashboardBodyHtml = (string) ob_get_clean();
            } catch (Throwable $e) {
                while (ob_get_level() > $dashboardBufferLevel) {
                    ob_end_clean();
                }

                error_log('Erreur rendu dashboard GNTOMA : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $dashboardBodyHtml = '<div class="bg-red-50 border border-red-100 text-red-600 text-sm p-5 rounded-[2rem] font-medium">Une erreur est survenue pendant le chargement du tableau de bord.</div>';
            }

            echo $dashboardBodyHtml;
            ?>
        </div>
    </main>

</body>
</html>