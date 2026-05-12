<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_delete.php
 * DESCRIPTION : Suppression d'un journal (auteur peut supprimer si privé ou sans lecteur)
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

$user_code = $_SESSION['user_id'];
$journal_id = (int)($_GET['id'] ?? 0);

if (!$journal_id) {
    header("Location: dashboard_6.php?error=invalid_journal");
    exit;
}

try {
    // Récupérer les infos du journal
    try {
        $stmt = $pdo->prepare("
            SELECT j.*, 
                   (SELECT COUNT(*) FROM journal_readers WHERE journal_id = j.id) as reader_count
            FROM journals j 
            WHERE j.id = ? AND j.user_code = ?
            LIMIT 1
        ");
        $stmt->execute([$journal_id, $user_code]);
        $journal = $stmt->fetch();
    } catch (PDOException $e) {
        // Fallback si la table journal_readers n'existe pas encore
        $stmt = $pdo->prepare("SELECT * FROM journals WHERE id = ? AND user_code = ? LIMIT 1");
        $stmt->execute([$journal_id, $user_code]);
        $journal = $stmt->fetch();
        $journal['reader_count'] = 0;
    }
    
    if (!$journal) {
        header("Location: dashboard_6.php?error=journal_not_found");
        exit;
    }
    
    // Vérifier si suppression autorisée (privé ou sans lecteur)
    $can_delete = ($journal['status'] === 'private' || ($journal['reader_count'] ?? 0) == 0);
    
    if (!$can_delete) {
        header("Location: journal_edit_13.php?id=" . $journal_id . "&error=cannot_delete_public_journal");
        exit;
    }
    
    // Traiter la suppression uniquement si POST avec confirmation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
        // Supprimer la couverture si existe
        if (!empty($journal['cover_image']) && file_exists('../' . $journal['cover_image'])) {
            unlink('../' . $journal['cover_image']);
        }
        
        // Supprimer les images des pages
        $pages_stmt = $pdo->prepare("SELECT image_path FROM journal_pages WHERE journal_id = ?");
        $pages_stmt->execute([$journal_id]);
        while ($page = $pages_stmt->fetch()) {
            if (!empty($page['image_path']) && file_exists('../' . $page['image_path'])) {
                unlink('../' . $page['image_path']);
            }
        }
        
        // Supprimer le journal (cascade supprimera les pages, demandes, etc.)
        $delete_stmt = $pdo->prepare("DELETE FROM journals WHERE id = ? AND user_code = ?");
        $delete_stmt->execute([$journal_id, $user_code]);
        
        header("Location: dashboard_6.php?success=journal_deleted");
        exit;
    }
    
    // Calculer le numéro du journal
    $num_stmt = $pdo->prepare("
        SELECT COUNT(*) as position 
        FROM journals 
        WHERE user_code = ? AND id <= ?
    ");
    $num_stmt->execute([$user_code, $journal_id]);
    $journal_num = $num_stmt->fetchColumn();
    $journal_code = $user_code . 'J' . $journal_num;
    
    // Déterminer la raison de la suppression autorisée
    $delete_reason = ($journal['status'] === 'private')
        ? __('journal_delete.reason_private')
        : __('journal_delete.reason_no_readers');
    
    // Calculer le temps restant avant expiration
    $expires_at = new DateTime($journal['expires_at'] ?? '+10 years');
    $now = new DateTime();
    $interval = $now->diff($expires_at);
    $years_left = $interval->y;
    
} catch (PDOException $e) {
    error_log("Erreur suppression journal : " . $e->getMessage());
    header("Location: dashboard_6.php?error=system_error");
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('journal_delete.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
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
            background: linear-gradient(135deg, #e6eff9 0%, #f4f7fb 100%);
            position: relative;
            overflow-x: hidden;
        }
        /* Motif décoratif élégant */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(0, 122, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 149, 0, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 122, 255, 0.02) 0%, transparent 30%);
            pointer-events: none;
            z-index: -1;
        }
        .pattern-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 122, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 122, 255, 0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: -2;
        }
        /* Motif de points subtil */
        .pattern-dots {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(0, 122, 255, 0.05) 1px, transparent 1px);
            background-size: 30px 30px;
            pointer-events: none;
            z-index: -3;
        }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(25px); 
            border: 1px solid rgba(255, 255, 255, 1); 
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.08); 
        }
        .glass-panel-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(255, 255, 255, 0.95) 100%);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(239, 68, 68, 0.1);
            box-shadow: 0 30px 60px rgba(239, 68, 68, 0.1);
        }
        .delete-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }
        .delete-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(239, 68, 68, 0.4);
        }
        .journal-card-pattern {
            background: 
                linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23007AFF' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <!-- Motifs de fond -->
    <div class="pattern-dots"></div>
    <div class="pattern-grid"></div>

    <div class="max-w-lg w-full">
        <div class="glass-panel-danger rounded-[2.5rem] p-8">
            
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-dark mb-2"><?= htmlspecialchars(__('journal_delete.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-gray-500 text-sm"><?= htmlspecialchars(__('journal_delete.irreversible'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <!-- Carte du journal avec motif -->
            <div class="journal-card-pattern rounded-2xl p-5 mb-6 border border-red-100">
                <div class="flex items-start space-x-4">
                    <?php if (!empty($journal['cover_image'])): ?>
                        <img src="../<?= htmlspecialchars($journal['cover_image']) ?>" alt="" class="w-20 h-20 rounded-xl object-cover shadow-md">
                    <?php else: ?>
                        <div class="w-20 h-20 bg-gradient-to-br from-red-100 to-orange-100 rounded-xl flex items-center justify-center shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <span class="inline-block px-2 py-1 bg-red-100 text-red-700 text-[10px] font-black uppercase rounded-full mb-2">
                            <?= htmlspecialchars($delete_reason, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <h3 class="font-bold text-dark text-lg mb-1"><?= htmlspecialchars($journal['title']) ?></h3>
                        <p class="text-sm text-gray-500 font-bold"><?= $journal_code ?></p>
                        
                        <div class="mt-3 flex items-center space-x-4 text-xs text-gray-400">
                            <span class="flex items-center space-x-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span><?= htmlspecialchars(__('journal_delete.years_left', ['n' => (string) $years_left]), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="flex items-center space-x-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span><?= htmlspecialchars(__('journal_delete.readers', ['n' => (string) $journal['reader_count']]), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-red-50 border border-red-100 rounded-2xl p-4 mb-6">
                <p class="text-sm text-red-700 text-center">
                    <?= __('journal_delete.warning') ?>
                </p>
            </div>

            <form method="POST" class="space-y-4">
                <label class="flex items-start space-x-3 cursor-pointer">
                    <input type="checkbox" name="confirm_delete" required class="mt-1 w-5 h-5 text-red-600 rounded border-gray-300 focus:ring-red-500">
                    <span class="text-sm text-gray-600">
                        <?= __('journal_delete.confirm_checkbox', ['title' => htmlspecialchars((string) $journal['title'], ENT_QUOTES, 'UTF-8')]) ?>
                    </span>
                </label>

                <div class="flex space-x-3 pt-4">
                    <a href="journal_edit_13.php?id=<?= $journal_id ?>" class="flex-1 bg-gray-200 text-dark font-bold py-4 rounded-2xl hover:bg-gray-300 transition-all text-center">
                        <?= htmlspecialchars(__('journal_delete.cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <button type="submit" class="flex-1 delete-btn text-white font-bold py-4 rounded-2xl transition-all">
                        <?= htmlspecialchars(__('journal_delete.confirm_delete'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
