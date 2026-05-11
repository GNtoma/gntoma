<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/messages_blocked.php
 * DESCRIPTION : Gestion des utilisateurs bloqués
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];

// Traitement du déblocage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_code'])) {
    $unblock_code = strtoupper(trim($_POST['unblock_code']));
    try {
        $pdo->prepare("
            DELETE FROM user_blocks 
            WHERE blocker_user_code = ? AND blocked_user_code = ?
        ")->execute([$user_code, $unblock_code]);
        header("Location: messages_blocked.php?success=unblocked");
        exit;
    } catch (PDOException $e) {
        error_log("Erreur déblocage : " . $e->getMessage());
    }
}

// Traitement du blocage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_code'])) {
    $block_code = strtoupper(trim($_POST['block_code']));
    $reason = trim($_POST['reason'] ?? '');
    
    if ($block_code !== $user_code) {
        try {
            // Vérifier si l'utilisateur existe
            $check_stmt = $pdo->prepare("SELECT user_code FROM users WHERE user_code = ? LIMIT 1");
            $check_stmt->execute([$block_code]);
            
            if ($check_stmt->fetch()) {
                // Insérer le blocage (ignore duplicate)
                $pdo->prepare("
                    INSERT IGNORE INTO user_blocks (blocker_user_code, blocked_user_code, reason)
                    VALUES (?, ?, ?)
                ")->execute([$user_code, $block_code, $reason]);
                header("Location: messages_blocked.php?success=blocked");
                exit;
            } else {
                header("Location: messages_blocked.php?error=user_not_found");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erreur blocage : " . $e->getMessage());
        }
    }
}

// Récupérer la liste des utilisateurs bloqués
try {
    $blockedProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'profile_pic');
    $blocked_stmt = $pdo->prepare("
        SELECT b.*, u.first_name, u.last_name, {$blockedProfilePicSelect}
        FROM user_blocks b
        JOIN users u ON b.blocked_user_code = u.user_code
        WHERE b.blocker_user_code = ?
        ORDER BY b.created_at DESC
    ");
    $blocked_stmt->execute([$user_code]);
    $blocked_users = $blocked_stmt->fetchAll();
} catch (PDOException $e) {
    $blocked_users = [];
}

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs Bloqués - GNTOMA</title>
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
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #F0F4F8 0%, #F5F5F7 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-4 py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="messages_list.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-lg font-bold text-dark">Bloqués</h1>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-4">

        <?php if ($success === 'blocked'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center">Utilisateur bloqué avec succès</p>
        </div>
        <?php elseif ($success === 'unblocked'): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-blue-700 text-center">Utilisateur débloqué</p>
        </div>
        <?php elseif ($error === 'user_not_found'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center">Utilisateur non trouvé</p>
        </div>
        <?php endif; ?>

        <!-- Ajouter un blocage -->
        <div class="glass-panel rounded-[2rem] p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Bloquer un utilisateur</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Code utilisateur</label>
                    <input type="text" name="block_code" required
                           placeholder="Ex: A3, B12..."
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm uppercase focus:ring-2 focus:ring-red-500 outline-none">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Raison (optionnel)</label>
                    <input type="text" name="reason"
                           placeholder="Pourquoi bloquer ?"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 outline-none">
                </div>
                
                <button type="submit" class="w-full bg-red-500 text-white font-bold py-3 rounded-xl hover:bg-red-600 transition-all flex items-center justify-center space-x-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <span>Bloquer</span>
                </button>
            </form>
        </div>

        <!-- Liste des bloqués -->
        <div class="glass-panel rounded-[2rem] p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Utilisateurs bloqués (<?= count($blocked_users) ?>)</h2>
            
            <?php if (empty($blocked_users)): ?>
            <div class="text-center py-8">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-gray-500 text-sm">Aucun utilisateur bloqué</p>
                <p class="text-gray-400 text-xs mt-1">Vous ne recevrez pas de messages des utilisateurs bloqués</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($blocked_users as $blocked): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                    <div class="flex items-center space-x-3">
                        <?php 
                        $profile_pic = !empty($blocked['profile_pic']) ? '../' . $blocked['profile_pic'] : '../images/user_default.png';
                        ?>
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-10 h-10 rounded-lg object-cover border border-gray-100">
                        <div>
                            <p class="font-bold text-dark text-sm">
                                <?= htmlspecialchars($blocked['first_name'] . ' ' . $blocked['last_name']) ?>
                            </p>
                            <p class="text-xs text-gray-500"><?= $blocked['blocked_user_code'] ?></p>
                            <?php if ($blocked['reason']): ?>
                            <p class="text-[10px] text-red-400"><?= htmlspecialchars($blocked['reason']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" class="ml-2">
                        <input type="hidden" name="unblock_code" value="<?= $blocked['blocked_user_code'] ?>">
                        <button type="submit" class="text-xs font-bold text-primary hover:text-blue-600 py-1 px-3 bg-blue-50 rounded-lg">
                            Débloquer
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>

</body>
</html>
