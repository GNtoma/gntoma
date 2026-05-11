<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/messages_filters.php
 * DESCRIPTION : Filtres pour les messages reçus (par mots-clés, expéditeur, etc.)
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];

// Récupérer les filtres
$filter_type = $_GET['filter'] ?? 'all';
$keyword_filter = trim((string)($_GET['keyword'] ?? ''));

try {
    // Récupérer les crédits
    $senderProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u_sender', 'sender_profile_pic');
    $credits_stmt = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ?");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    $remaining = $credits['remaining_credits'] ?? 0;

    // Construire la requête selon le filtre
    $sql = "
        SELECT DISTINCT
            m.id as message_id,
            m.content,
            m.keywords,
            m.created_at,
            m.has_attachment,
            m.attachment_path,
            m.is_read,
            t.id as thread_id,
            u_sender.user_code as sender_code,
            u_sender.first_name as sender_first_name,
            u_sender.last_name as sender_last_name,
            {$senderProfilePicSelect}
        FROM messages m
        JOIN message_threads t ON m.thread_id = t.id
        JOIN users u_sender ON m.sender_user_code = u_sender.user_code
        WHERE m.recipient_user_code = ?
    ";
    $params = [$user_code];

    if ($filter_type === 'unread') {
        $sql .= " AND m.is_read = 0";
    } elseif ($filter_type === 'with_images') {
        $sql .= " AND m.has_attachment = 1";
    } elseif ($filter_type === 'keyword' && !empty($keyword_filter)) {
        $sql .= " AND m.keywords LIKE ?";
        $params[] = '%' . $keyword_filter . '%';
    }

    $sql .= " ORDER BY m.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Récupérer les mots-clés uniques de mes messages reçus
    $keywords_stmt = $pdo->prepare("
        SELECT DISTINCT keywords 
        FROM messages 
        WHERE recipient_user_code = ? AND keywords IS NOT NULL AND keywords != ''
        LIMIT 50
    ");
    $keywords_stmt->execute([$user_code]);
    $all_keywords = [];
    while ($row = $keywords_stmt->fetch()) {
        $kws = explode(',', $row['keywords']);
        foreach ($kws as $kw) {
            $kw = trim($kw);
            if (!empty($kw) && !in_array($kw, $all_keywords)) {
                $all_keywords[] = $kw;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Erreur filtres messages : " . $e->getMessage());
    $messages = [];
    $all_keywords = [];
    $remaining = 0;
}

$mois_fr = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtres Messages - GNTOMA</title>
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
            <h1 class="text-lg font-bold text-dark">Filtres Messages</h1>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-4">
        
        <!-- Crédits -->
        <div class="glass-panel rounded-[2rem] p-4 flex items-center justify-between">
            <p class="text-xs text-gray-500 font-bold uppercase">Crédits disponibles</p>
            <p class="text-xl font-black text-dark"><?= number_format($remaining) ?></p>
        </div>

        <!-- Type de filtre -->
        <div class="glass-panel rounded-[2rem] p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Type de filtre</h2>
            <div class="space-y-2">
                <a href="?filter=all" class="flex items-center space-x-3 p-3 rounded-xl <?= $filter_type === 'all' ? 'bg-primary/10 border-2 border-primary' : 'hover:bg-gray-50 border border-transparent' ?> transition-all">
                    <svg class="h-5 w-5 <?= $filter_type === 'all' ? 'text-primary' : 'text-gray-400' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span class="font-bold <?= $filter_type === 'all' ? 'text-primary' : 'text-dark' ?> text-sm">Tous les messages</span>
                </a>
                <a href="?filter=unread" class="flex items-center space-x-3 p-3 rounded-xl <?= $filter_type === 'unread' ? 'bg-primary/10 border-2 border-primary' : 'hover:bg-gray-50 border border-transparent' ?> transition-all">
                    <svg class="h-5 w-5 <?= $filter_type === 'unread' ? 'text-primary' : 'text-gray-400' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <span class="font-bold <?= $filter_type === 'unread' ? 'text-primary' : 'text-dark' ?> text-sm">Non lus</span>
                </a>
                <a href="?filter=with_images" class="flex items-center space-x-3 p-3 rounded-xl <?= $filter_type === 'with_images' ? 'bg-primary/10 border-2 border-primary' : 'hover:bg-gray-50 border border-transparent' ?> transition-all">
                    <svg class="h-5 w-5 <?= $filter_type === 'with_images' ? 'text-primary' : 'text-gray-400' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="font-bold <?= $filter_type === 'with_images' ? 'text-primary' : 'text-dark' ?> text-sm">Avec images</span>
                </a>
            </div>
        </div>

        <!-- Filtre par mot-clé -->
        <?php if (!empty($all_keywords)): ?>
        <div class="glass-panel rounded-[2rem] p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Par mot-clé</h2>
            <form method="GET" class="mb-4">
                <input type="text" name="keyword" 
                       value="<?= htmlspecialchars($keyword_filter) ?>"
                       placeholder="Rechercher un mot-clé..." 
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                <input type="hidden" name="filter" value="keyword">
            </form>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($all_keywords as $kw): ?>
                <a href="?filter=keyword&keyword=<?= urlencode($kw) ?>" 
                   class="px-3 py-1.5 text-xs font-bold rounded-full <?= ($filter_type === 'keyword' && $keyword_filter === $kw) ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> transition-all">
                    <?= htmlspecialchars($kw) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Résultats -->
        <div class="glass-panel rounded-[2rem] p-4">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4 px-2">
                <?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?>
            </h2>
            
            <?php if (empty($messages)): ?>
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">Aucun message</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($messages as $msg): 
                    $date_obj = new DateTime($msg['created_at']);
                    $jour = $date_obj->format('d');
                    $mois = $mois_fr[(int)$date_obj->format('m') - 1];
                    $date_formatee = "$jour $mois";
                    $heure = $date_obj->format('H:i');
                    
                    $profile_pic = !empty($msg['sender_profile_pic']) ? '../' . $msg['sender_profile_pic'] : '../images/user_default.png';
                ?>
                <a href="message_chat.php?thread=<?= $msg['thread_id'] ?>" 
                   class="flex items-start space-x-3 p-3 rounded-xl <?= $msg['is_read'] ? 'bg-gray-50/50' : 'bg-blue-50/50 border border-blue-100' ?> hover:bg-gray-100 transition-all">
                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-10 h-10 rounded-xl object-cover border border-gray-100 flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-bold text-dark text-sm truncate">
                                <?= htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']) ?>
                            </p>
                            <span class="text-[10px] text-gray-400 flex-shrink-0 ml-2"><?= $heure ?></span>
                        </div>
                        <p class="text-xs text-gray-600 line-clamp-2"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                        <?php if (!empty($msg['keywords'])): ?>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <?php foreach (explode(',', $msg['keywords']) as $kw): ?>
                            <span class="text-[9px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= htmlspecialchars(trim($kw)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($msg['has_attachment']): ?>
                        <div class="mt-1 text-[10px] text-primary font-bold flex items-center">
                            <svg class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Image
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>

</body>
</html>
