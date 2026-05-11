<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/messages_list.php
 * DESCRIPTION : Liste des conversations et notifications
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];

try {
    // Récupérer les crédits de l'utilisateur
    $otherProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'other_profile_pic');
    $credits_stmt = $pdo->prepare("
        SELECT total_credits, used_credits, remaining_credits 
        FROM message_credits 
        WHERE user_code = ?
    ");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    
    if (!$credits) {
        // Créer les crédits par défaut
        $pdo->prepare("INSERT INTO message_credits (user_code, total_credits, remaining_credits) VALUES (?, 100, 100)")->execute([$user_code]);
        $credits = ['total_credits' => 100, 'used_credits' => 0, 'remaining_credits' => 100];
    }
    
    // Récupérer les conversations
    $threads_stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.participant_1,
            t.participant_2,
            t.last_message_at,
            t.last_message_preview,
            CASE 
                WHEN t.participant_1 = ? THEN t.participant_2 
                ELSE t.participant_1 
            END as other_user_code,
            u.first_name as other_first_name,
            u.last_name as other_last_name,
            {$otherProfilePicSelect},
            (SELECT COUNT(*) FROM messages m 
             WHERE m.thread_id = t.id 
             AND m.recipient_user_code = ? 
             AND m.is_read = 0) as unread_count
        FROM message_threads t
        JOIN users u ON u.user_code = CASE 
            WHEN t.participant_1 = ? THEN t.participant_2 
            ELSE t.participant_1 
        END
        WHERE t.participant_1 = ? OR t.participant_2 = ?
        ORDER BY t.last_message_at DESC
        LIMIT 50
    ");
    $threads_stmt->execute([$user_code, $user_code, $user_code, $user_code, $user_code]);
    $threads = $threads_stmt->fetchAll();
    
    // Compter les messages non lus totaux
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_unread 
        FROM messages 
        WHERE recipient_user_code = ? AND is_read = 0
    ");
    $unread_stmt->execute([$user_code]);
    $total_unread = $unread_stmt->fetch()['total_unread'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Erreur messagerie : " . $e->getMessage());
    $credits = ['total_credits' => 0, 'remaining_credits' => 0];
    $threads = [];
    $total_unread = 0;
}

$success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - GNTOMA</title>
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
        .message-preview { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-4 py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="dashboard_6.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div class="text-center">
                <h1 class="text-lg font-bold text-dark">Messages</h1>
                <?php if ($total_unread > 0): ?>
                <span class="text-[10px] text-primary font-bold"><?= $total_unread ?> non lu<?= $total_unread > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-4">
        
        <?php if ($success === 'sent'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4 animate__animated animate__bounceIn">
            <p class="text-sm font-bold text-green-700 text-center">Message envoyé !</p>
        </div>
        <?php endif; ?>

        <!-- Crédits -->
        <div class="glass-panel rounded-[2rem] p-5 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center">
                    <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase">Crédits Messages</p>
                    <p class="text-2xl font-black text-dark"><?= number_format($credits['remaining_credits'] ?? 0) ?></p>
                </div>
            </div>
            <a href="messages_buy.php" class="bg-primary text-white font-bold py-2 px-4 rounded-xl text-sm hover:bg-blue-600 transition-all">
                + Acheter
            </a>
        </div>

        <!-- Actions rapides -->
        <div class="grid grid-cols-2 gap-3">
            <a href="message_send.php" class="glass-panel rounded-2xl p-4 flex items-center justify-center space-x-2 hover:bg-white transition-all">
                <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                <span class="font-bold text-dark text-sm">Nouveau</span>
            </a>
            <a href="message_bulk.php" class="glass-panel rounded-2xl p-4 flex items-center justify-center space-x-2 hover:bg-white transition-all">
                <svg class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="font-bold text-dark text-sm">Groupe (50)</span>
            </a>
        </div>

        <!-- Liste des conversations -->
        <div class="glass-panel rounded-[2rem] p-4">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4 px-2">Conversations</h2>
            
            <?php if (empty($threads)): ?>
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">Aucune conversation</p>
                <p class="text-gray-400 text-sm mt-1">Commencez à discuter avec d'autres membres</p>
            </div>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($threads as $thread): ?>
                <a href="message_chat.php?thread=<?= $thread['id'] ?>" class="flex items-center space-x-3 p-3 rounded-2xl hover:bg-gray-50 transition-all <?= ($thread['unread_count'] ?? 0) > 0 ? 'bg-blue-50/50' : '' ?>">
                    <div class="relative">
                        <?php 
                        $profile_pic = !empty($thread['other_profile_pic']) ? '../' . $thread['other_profile_pic'] : '../images/user_default.png';
                        ?>
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-12 h-12 rounded-xl object-cover border border-gray-100">
                        <?php if (($thread['unread_count'] ?? 0) > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                            <?= $thread['unread_count'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-bold text-dark text-sm truncate">
                                <?= htmlspecialchars($thread['other_first_name'] . ' ' . $thread['other_last_name']) ?>
                            </p>
                            <?php if ($thread['last_message_at']): ?>
                            <span class="text-[10px] text-gray-400">
                                <?= date('d/m', strtotime($thread['last_message_at'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 message-preview <?= ($thread['unread_count'] ?? 0) > 0 ? 'font-semibold text-gray-700' : '' ?>">
                            <?= htmlspecialchars($thread['last_message_preview'] ?? 'Nouvelle conversation') ?>
                        </p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions secondaires -->
        <div class="flex gap-3">
            <a href="messages_blocked.php" class="flex-1 glass-panel rounded-2xl p-3 text-center hover:bg-white transition-all">
                <svg class="h-5 w-5 text-red-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <span class="text-xs font-bold text-gray-600">Bloqués</span>
            </a>
            <a href="messages_filters.php" class="flex-1 glass-panel rounded-2xl p-3 text-center hover:bg-white transition-all">
                <svg class="h-5 w-5 text-green-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-xs font-bold text-gray-600">Filtres</span>
            </a>
        </div>

    </main>

</body>
</html>
