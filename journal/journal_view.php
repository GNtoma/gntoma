<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_view.php
 * DESCRIPTION : Affichage d'un journal pour les lecteurs avec commentaires/questions
 */

session_start();
require_once 'config.php';

// Récupérer l'ID du journal
$journal_id = (int)($_GET['id'] ?? 0);
$current_user_code = $_SESSION['user_id'] ?? null;

if (!$journal_id) {
    header("Location: dashboard_6.php?error=invalid_journal");
    exit;
}

try {
    // Récupérer les infos du journal
    $journal_stmt = $pdo->prepare("
        SELECT j.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
        FROM journals j
        JOIN users u ON j.user_code = u.user_code
        WHERE j.id = ? AND j.status IN ('public', 'paid')
        LIMIT 1
    ");
    $journal_stmt->execute([$journal_id]);
    $journal = $journal_stmt->fetch();
    
    if (!$journal) {
        header("Location: dashboard_6.php?error=journal_not_found");
        exit;
    }
    
    // Générer le code du journal
    $journal_code = $journal['user_code'] . 'J' . $journal['journal_num'];
    
    // Pagination intelligente des pages du journal (1 page affichee a la fois)
    $requested_page = max(1, (int)($_GET['page'] ?? 1));
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM journal_pages WHERE journal_id = ?");
    $count_stmt->execute([$journal_id]);
    $total_pages = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, $total_pages);
    $current_page = min($requested_page, $total_pages);
    $page_offset = $current_page - 1;

    $pages_stmt = $pdo->prepare("
        SELECT * FROM journal_pages
        WHERE journal_id = ?
        ORDER BY page_order ASC, id ASC
        LIMIT 1 OFFSET ?
    ");
    $pages_stmt->bindValue(1, $journal_id, PDO::PARAM_INT);
    $pages_stmt->bindValue(2, $page_offset, PDO::PARAM_INT);
    $pages_stmt->execute();
    $pages = $pages_stmt->fetchAll();
    
    // Récupérer tous les commentaires approuvés
    $commenterProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'commenter_profile_pic');
    $comments_stmt = $pdo->prepare("
        SELECT c.*, 
               u.first_name as commenter_first_name, 
               u.last_name as commenter_last_name,
               {$commenterProfilePicSelect}
        FROM journal_comments c
        JOIN users u ON c.user_code = u.user_code
        WHERE c.journal_id = ? AND c.status = 'approved'
        ORDER BY c.created_at ASC
    ");
    $comments_stmt->execute([$journal_id]);
    $all_comments = $comments_stmt->fetchAll();
    
    // Organiser les commentaires (parents et réponses)
    $comments = [];
    $replies = [];
    foreach ($all_comments as $c) {
        if (empty($c['parent_id'])) {
            // Inverser l'ordre des parents pour avoir les plus récents en premier
            array_unshift($comments, $c);
        } else {
            if (!isset($replies[$c['parent_id']])) {
                $replies[$c['parent_id']] = [];
            }
            $replies[$c['parent_id']][] = $c;
        }
    }
    
    // Compter les commentaires (total)
    $comments_count = count($all_comments);
    
    // Vérifier si l'utilisateur est l'auteur
    $is_author = ($current_user_code === $journal['user_code']);
    $has_paid_access = $is_author;
    
    // Vérifier si l'utilisateur a déjà demandé l'accès (pour journaux payants)
    $has_access_request = false;
    $access_request_status = null;
    if (!$is_author && $journal['status'] === 'paid' && $current_user_code) {
        try {
            $reader_stmt = $pdo->prepare("SELECT id FROM journal_readers WHERE journal_id = ? AND user_code = ? LIMIT 1");
            $reader_stmt->execute([$journal_id, $current_user_code]);
            $has_paid_access = (bool) $reader_stmt->fetch();

            $access_stmt = $pdo->prepare("
                SELECT status FROM access_requests 
                WHERE journal_id = ? AND requester_user_code = ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $access_stmt->execute([$journal_id, $current_user_code]);
            $access_result = $access_stmt->fetch();
            if ($access_result) {
                $has_access_request = true;
                $access_request_status = $access_result['status'];
                if ($access_request_status === 'approved') {
                    $has_paid_access = true;
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur vérification demande accès journal : " . $e->getMessage());
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur affichage journal : " . $e->getMessage());
    header("Location: dashboard_6.php?error=system_error");
    exit;
}

// Messages de feedback
$success_message = $_GET['success'] ?? null;
$error_message = $_GET['error'] ?? null;
$can_view_full_journal = !($journal['status'] === 'paid' && !$has_paid_access);
$has_next_page = $can_view_full_journal && ($current_page < $total_pages);
$next_page_url = $has_next_page ? ('?id=' . (int)$journal_id . '&page=' . ($current_page + 1)) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($journal['title']) ?> - GNTOMA</title>
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
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #F0F4F8 0%, #F5F5F7 50%, #EDF2F7 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.08); }
        .pattern-dots { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: radial-gradient(rgba(0, 122, 255, 0.04) 2px, transparent 2px); background-size: 32px 32px; pointer-events: none; z-index: -1; }
        .page-content img { max-width: 100%; height: auto; border-radius: 1rem; margin: 1rem 0; }
        .page-content p { margin-bottom: 1rem; line-height: 1.8; }
    </style>
    <?php if ($has_next_page): ?>
    <link rel="prefetch" href="<?= htmlspecialchars($next_page_url) ?>">
    <?php endif; ?>
</head>
<body class="min-h-screen pb-20">
    <div class="pattern-dots"></div>
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-4 py-4">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <a href="dashboard_6.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div class="text-center flex-1 mx-4">
                <p class="text-[10px] font-black text-primary uppercase tracking-widest"><?= $journal_code ?></p>
                <h1 class="text-sm font-bold text-dark truncate"><?= htmlspecialchars($journal['title']) ?></h1>
            </div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-6 space-y-6">
        
        <?php if ($success_message === 'comment_added'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4 animate__animated animate__bounceIn">
            <p class="text-sm font-bold text-green-700 text-center">Votre question a été envoyée à l'auteur !</p>
        </div>
        <?php endif; ?>
        
        <!-- Info auteur -->
        <div class="glass-panel rounded-[2rem] p-5 flex items-center space-x-4">
            <div class="w-14 h-14 bg-gradient-to-br from-primary to-blue-400 rounded-2xl flex items-center justify-center text-white font-black text-xl">
                <?= strtoupper(substr($journal['first_name'], 0, 1)) ?>
            </div>
            <div class="flex-1">
                <p class="font-bold text-dark"><?= htmlspecialchars($journal['first_name'] . ' ' . $journal['last_name']) ?></p>
                <p class="text-xs text-gray-500">Auteur du journal</p>
            </div>
            <?php if ($journal['status'] === 'paid'): ?>
            <span class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-bold rounded-full">
                <?= number_format($journal['price'], 0) ?> <?= $journal['price_currency'] ?>
            </span>
            <?php endif; ?>
        </div>

        <?php if ($journal['status'] === 'paid' && !$has_paid_access): ?>
        <!-- Journal payant - Accès restreint -->
        <div class="glass-panel rounded-[2rem] p-8 text-center">
            <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="h-10 w-10 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="text-xl font-black text-dark mb-2">Journal Payant</h2>
            <p class="text-gray-500 text-sm mb-6">Ce journal est en accès payant. Demandez l'accès à l'auteur pour le lire.</p>
            
            <?php if ($has_access_request): ?>
                <?php if ($access_request_status === 'pending'): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <p class="text-sm font-bold text-yellow-700">Votre demande est en attente d'approbation</p>
                </div>
                <?php elseif ($access_request_status === 'rejected'): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                    <p class="text-sm font-bold text-red-700">Votre demande a été refusée</p>
                </div>
                <a href="journal_access_request.php?journal_id=<?= $journal_id ?>" class="inline-block bg-primary text-white font-bold py-3 px-8 rounded-2xl hover:bg-blue-600 transition-all">
                    Renouveler la demande
                </a>
                <?php endif; ?>
            <?php else: ?>
            <a href="journal_access_request.php?journal_id=<?= $journal_id ?>" class="inline-block bg-primary text-white font-bold py-3 px-8 rounded-2xl hover:bg-blue-600 transition-all">
                Demander l'accès
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Pages du journal -->
        <div class="space-y-4">
            <?php if ($total_pages > 1): ?>
            <div class="glass-panel rounded-[2rem] p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs font-black uppercase tracking-widest text-gray-400">
                        Page <?= $current_page ?> / <?= $total_pages ?>
                    </p>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="id" value="<?= (int)$journal_id ?>">
                        <label for="goto-page" class="text-xs font-bold text-gray-500">Aller a</label>
                        <input id="goto-page" name="page" type="number" min="1" max="<?= $total_pages ?>" value="<?= $current_page ?>"
                               class="w-20 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-primary outline-none">
                        <button type="submit" class="bg-primary text-white text-xs font-black px-4 py-2 rounded-xl hover:bg-blue-600 transition-all">
                            OK
                        </button>
                    </form>
                </div>

                <div class="mt-4 flex items-center justify-between gap-2">
                    <?php if ($current_page > 1): ?>
                    <a href="?id=<?= (int)$journal_id ?>&page=<?= $current_page - 1 ?>" class="bg-white border border-gray-200 text-dark text-xs font-bold px-4 py-2 rounded-xl hover:bg-gray-50 transition-all">
                        ← Precedent
                    </a>
                    <?php else: ?>
                    <span class="bg-gray-100 text-gray-400 text-xs font-bold px-4 py-2 rounded-xl">← Precedent</span>
                    <?php endif; ?>

                    <div class="hidden sm:flex items-center gap-1">
                        <?php
                            $window_start = max(1, $current_page - 2);
                            $window_end = min($total_pages, $current_page + 2);
                            for ($p = $window_start; $p <= $window_end; $p++):
                        ?>
                        <a href="?id=<?= (int)$journal_id ?>&page=<?= $p ?>"
                           class="w-9 h-9 rounded-lg text-xs font-black flex items-center justify-center transition-all <?= $p === $current_page ? 'bg-primary text-white' : 'bg-white border border-gray-200 text-dark hover:bg-gray-50' ?>">
                            <?= $p ?>
                        </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($current_page < $total_pages): ?>
                    <a href="?id=<?= (int)$journal_id ?>&page=<?= $current_page + 1 ?>" class="bg-primary text-white text-xs font-bold px-4 py-2 rounded-xl hover:bg-blue-600 transition-all">
                        Suivant →
                    </a>
                    <?php else: ?>
                    <span class="bg-gray-100 text-gray-400 text-xs font-bold px-4 py-2 rounded-xl">Suivant →</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php foreach ($pages as $index => $page): ?>
            <div class="glass-panel rounded-[2rem] p-6 md:p-8">
                <div class="flex items-center space-x-2 mb-4">
                    <span class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary font-bold text-sm">
                        <?= $current_page ?>
                    </span>
                    <h2 class="font-bold text-dark"><?= htmlspecialchars($page['title'] ?? 'Page ' . $current_page) ?></h2>
                </div>
                
                <?php if (!empty($page['image_path'])): ?>
                <img src="../<?= htmlspecialchars($page['image_path']) ?>" alt="" class="w-full rounded-2xl mb-4 shadow-lg">
                <?php endif; ?>
                
                <?php if (!empty($page['content'])): ?>
                <div class="page-content text-gray-700 leading-relaxed prose prose-blue max-w-none">
                    <?= $page['content'] ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Section Commentaires/Questions -->
        <div class="glass-panel rounded-[2rem] p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-black text-dark flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span>Questions & Commentaires</span>
                </h3>
                <span class="px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full">
                    <?= $comments_count ?>
                </span>
            </div>

            <?php if ($current_user_code): ?>
            <!-- Formulaire de question -->
            <form action="journal_comment_add.php" method="POST" class="mb-6">
                <input type="hidden" name="journal_id" value="<?= $journal_id ?>">
                <input type="hidden" name="author_user_code" value="<?= $journal['user_code'] ?>">
                
                <div class="relative">
                    <textarea name="content" rows="3" 
                              placeholder="Posez votre question à l'auteur..." 
                              class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                              required></textarea>
                </div>
                
                <div class="flex justify-between items-center mt-3">
                    <p class="text-xs text-gray-400">Votre question sera visible par l'auteur et les autres lecteurs</p>
                    <button type="submit" class="bg-primary text-white font-bold py-2 px-6 rounded-xl hover:bg-blue-600 transition-all text-sm flex items-center space-x-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        <span>Envoyer</span>
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-6 text-center">
                <p class="text-sm text-gray-500">Connectez-vous pour poser une question à l'auteur</p>
                <a href="../index.php" class="inline-block mt-2 text-primary font-bold text-sm hover:underline">Se connecter</a>
            </div>
            <?php endif; ?>

            <!-- Liste des commentaires -->
            <div class="space-y-4" id="comments-list">
                <?php if (empty($comments)): ?>
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <p class="text-gray-400 text-sm">Aucune question pour le moment</p>
                    <p class="text-gray-300 text-xs mt-1">Soyez le premier à interagir avec l'auteur !</p>
                </div>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="bg-gray-50 rounded-2xl p-4 relative group">
                        <!-- Commentaire Principal (Question) -->
                        <div class="flex items-start space-x-3">
                            <?php $c_pic = !empty($comment['commenter_profile_pic']) ? '../' . $comment['commenter_profile_pic'] : '../images/user_default.png'; ?>
                            <img src="<?= htmlspecialchars($c_pic) ?>" alt="" class="w-10 h-10 rounded-xl object-cover border border-gray-200 flex-shrink-0">
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="font-bold text-dark text-sm truncate">
                                        <?= htmlspecialchars($comment['commenter_first_name'] . ' ' . $comment['commenter_last_name']) ?>
                                    </p>
                                    <span class="text-[10px] text-gray-400">
                                        <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                    </span>
                                </div>
                                <p class="text-gray-700 text-sm leading-relaxed break-words">
                                    <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Réponses de l'auteur -->
                        <?php if (!empty($replies[$comment['id']])): ?>
                        <div class="mt-3 ml-12 space-y-3">
                            <?php foreach ($replies[$comment['id']] as $reply): ?>
                            <div class="bg-white rounded-xl p-3 border border-gray-100 shadow-sm relative">
                                <div class="absolute -left-5 top-4 w-4 h-px bg-gray-300"></div>
                                <div class="flex items-start space-x-2">
                                    <?php $r_pic = !empty($reply['commenter_profile_pic']) ? '../' . $reply['commenter_profile_pic'] : '../images/user_default.png'; ?>
                                    <img src="<?= htmlspecialchars($r_pic) ?>" alt="" class="w-6 h-6 rounded-lg object-cover flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <p class="font-black text-primary text-xs">L'auteur a répondu</p>
                                            <span class="text-[9px] text-gray-400"><?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></span>
                                        </div>
                                        <p class="text-gray-600 text-xs leading-relaxed break-words">
                                            <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Bouton Répondre pour l'auteur -->
                        <?php if ($is_author): ?>
                        <div class="mt-3 ml-12">
                            <button type="button" onclick="document.getElementById('reply-form-<?= $comment['id'] ?>').classList.toggle('hidden')" class="text-xs font-bold text-primary hover:underline flex items-center space-x-1">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                <span>Répondre à cette question</span>
                            </button>
                            
                            <form id="reply-form-<?= $comment['id'] ?>" action="journal_comment_add.php" method="POST" class="hidden mt-2 flex space-x-2">
                                <input type="hidden" name="journal_id" value="<?= $journal_id ?>">
                                <input type="hidden" name="author_user_code" value="<?= $journal['user_code'] ?>">
                                <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                                <input type="text" name="content" placeholder="Votre réponse..." class="flex-1 bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                <button type="submit" class="bg-primary text-white p-2 rounded-xl hover:bg-blue-600 transition-all flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <?php if ($has_next_page): ?>
    <script>
        // Preload doux de la page suivante pour une navigation plus fluide.
        window.addEventListener('load', function () {
            const nextUrl = <?= json_encode($next_page_url) ?>;
            if (!nextUrl) return;
            setTimeout(function () {
                fetch(nextUrl, { credentials: 'same-origin' }).catch(function () {});
            }, 800);
        });
    </script>
    <?php endif; ?>

</body>
</html>
