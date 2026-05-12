<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_edit_13.php
 * VERSION : 13
 * DESCRIPTION : Édition d'un journal et gestion de ses pages (texte + images)
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

if ($journal_id === 0) {
    header("Location: dashboard_6.php");
    exit;
}

// Récupérer le journal
$stmt = $pdo->prepare("
    SELECT j.*, 
           (SELECT COUNT(*) FROM journal_readers WHERE journal_id = j.id) as reader_count
    FROM journals j 
    WHERE j.id = ? AND j.user_code = ?
");

try {
    $stmt->execute([$journal_id, $user_code]);
    $journal = $stmt->fetch();
} catch (PDOException $e) {
    // Fallback si la table journal_readers n'existe pas encore (migration 012 pas exécutée)
    $stmt = $pdo->prepare("SELECT * FROM journals WHERE id = ? AND user_code = ?");
    $stmt->execute([$journal_id, $user_code]);
    $journal = $stmt->fetch();
    $journal['reader_count'] = 0; // Par défaut, considérer 0 lecteurs
}

if (!$journal) {
    header("Location: dashboard_6.php?error=journal_not_found");
    exit;
}

// Calculer si le journal peut être supprimé (privé ou sans lecteur)
$can_delete = ($journal['status'] === 'private' || ($journal['reader_count'] ?? 0) == 0);

// Calculer le temps restant avant expiration (10 ans)
$expires_at = new DateTime($journal['expires_at'] ?? '+10 years');
$now = new DateTime();
$interval = $now->diff($expires_at);
$years_left = $interval->y;
$days_left = $interval->days;

// Récupérer les pages du journal
$stmt = $pdo->prepare("SELECT * FROM journal_pages WHERE journal_id = ? ORDER BY page_order ASC, id ASC");
$stmt->execute([$journal_id]);
$pages = $stmt->fetchAll();

// Si aucune page, en créer une par défaut
if (empty($pages)) {
    $stmt = $pdo->prepare("INSERT INTO journal_pages (journal_id, title, page_order) VALUES (?, 'Page 1', 1)");
    $stmt->execute([$journal_id]);
    $page_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM journal_pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $pages = [$stmt->fetch()];
}

$page_count = count($pages);
$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1 || $current_page > $page_count) {
    $current_page = 1;
}

$active_page = $pages[$current_page - 1] ?? $pages[0];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('journal_edit.page_title', ['title' => (string) $journal['title']]), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
        }
        /* Motif décoratif géométrique élégant */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 15% 85%, rgba(0, 122, 255, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 85% 15%, rgba(255, 149, 0, 0.03) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(0, 122, 255, 0.02) 0%, transparent 50%);
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
                linear-gradient(rgba(0, 122, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 122, 255, 0.015) 1px, transparent 1px);
            background-size: 80px 80px;
            pointer-events: none;
            z-index: -2;
        }
        .pattern-dots {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(0, 122, 255, 0.04) 1.5px, transparent 1.5px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: -3;
        }
        .journal-card-bg {
            background: 
                linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.8) 100%),
                url("data:image/svg+xml,%3Csvg width='52' height='52' viewBox='0 0 52 52' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23007AFF' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); }
        [contenteditable]:empty:before { content: attr(placeholder); color: #9ca3af; }
        .content-block { min-height: 100px; }
        .content-block img { max-width: 100%; border-radius: 0.75rem; margin: 1rem 0; }
        .delete-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .delete-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-1px);
        }
        .life-indicator {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid rgba(0, 122, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-50 min-h-screen">
    
    <!-- Motifs décoratifs de fond -->
    <div class="pattern-dots"></div>
    <div class="pattern-grid"></div>
    
    <!-- Header -->
    <header class="glass sticky top-0 z-50 border-b border-white/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <a href="dashboard_6.php" class="flex items-center space-x-2 text-primary hover:opacity-80 transition-opacity">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="font-bold text-sm"><?= htmlspecialchars(__('common.back'), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
                <h1 class="font-black text-lg text-dark truncate max-w-xs"><?php echo htmlspecialchars($journal['title']); ?></h1>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold text-gray-400"><?= htmlspecialchars(__('journal_nav.page_short', ['current' => (string) $current_page, 'total' => (string) $page_count]), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        
        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-2">
                <button onclick="addTextBlock()" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all flex items-center space-x-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                    <span><?= htmlspecialchars(__('journal_edit.toolbar_text'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <label class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all flex items-center space-x-2 cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span><?= htmlspecialchars(__('journal_edit.toolbar_image'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="file" id="image-upload" accept="image/*" class="hidden" onchange="uploadImage(this)">
                </label>
            </div>
            
                <div class="flex items-center gap-2">
                    <span class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></span>
                    <button onclick="savePage()" class="bg-primary text-white px-6 py-2 rounded-xl text-xs font-bold hover:opacity-90 transition-all flex items-center space-x-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span><?= htmlspecialchars(__('common.save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
        </div>

        <!-- Page Navigation intelligente -->
        <div class="bg-white rounded-2xl border border-gray-100 p-3 sm:p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs font-black uppercase tracking-widest text-gray-400">
                    <?= htmlspecialchars(__('journal_nav.nav_pages_title', ['current' => (string) $current_page, 'total' => (string) $page_count]), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="id" value="<?= (int)$journal_id ?>">
                    <label for="goto-editor-page" class="text-xs font-bold text-gray-500"><?= htmlspecialchars(__('journal_nav.go_to'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="goto-editor-page" name="page" type="number" min="1" max="<?= $page_count ?>" value="<?= $current_page ?>"
                           class="w-20 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-primary outline-none">
                    <button type="submit" class="bg-primary text-white text-xs font-black px-4 py-2 rounded-xl hover:opacity-90 transition-all"><?= htmlspecialchars(__('common.ok'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>

            <div class="mt-3 flex items-center justify-between gap-2">
                <?php if ($current_page > 1): ?>
                <a href="?id=<?= (int)$journal_id ?>&page=<?= $current_page - 1 ?>" class="bg-white border border-gray-200 text-dark text-xs font-bold px-4 py-2 rounded-xl hover:bg-gray-50 transition-all">
                    <?= htmlspecialchars(__('journal_nav.previous'), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php else: ?>
                <span class="bg-gray-100 text-gray-400 text-xs font-bold px-4 py-2 rounded-xl"><?= htmlspecialchars(__('journal_nav.previous'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>

                <div class="hidden md:flex items-center gap-1">
                    <?php
                    $window_start = max(1, $current_page - 2);
                    $window_end = min($page_count, $current_page + 2);
                    if ($window_start > 1): ?>
                        <a href="?id=<?= (int)$journal_id ?>&page=1" class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-dark hover:bg-gray-50 text-xs font-black flex items-center justify-center">1</a>
                        <?php if ($window_start > 2): ?><span class="text-gray-400 text-xs font-bold px-1">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $window_start; $i <= $window_end; $i++): ?>
                        <a href="?id=<?= (int)$journal_id ?>&page=<?= $i ?>"
                           class="w-9 h-9 rounded-lg text-xs font-black flex items-center justify-center transition-all <?= $i === $current_page ? 'bg-primary text-white shadow-lg' : 'bg-white border border-gray-200 text-dark hover:bg-gray-50'; ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($window_end < $page_count): ?>
                        <?php if ($window_end < $page_count - 1): ?><span class="text-gray-400 text-xs font-bold px-1">…</span><?php endif; ?>
                        <a href="?id=<?= (int)$journal_id ?>&page=<?= $page_count ?>" class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-dark hover:bg-gray-50 text-xs font-black flex items-center justify-center"><?= $page_count ?></a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <form action="journal_page_add_14.php" method="POST" class="inline">
                        <input type="hidden" name="journal_id" value="<?= (int)$journal_id ?>">
                        <button type="submit" class="w-10 h-10 rounded-xl bg-green-500 text-white flex items-center justify-center hover:bg-green-600 transition-all" title="<?= htmlspecialchars(__('journal_nav.add_page_btn_title'), ENT_QUOTES, 'UTF-8') ?>">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </button>
                    </form>

                    <?php if ($current_page < $page_count): ?>
                    <a href="?id=<?= (int)$journal_id ?>&page=<?= $current_page + 1 ?>" class="bg-primary text-white text-xs font-bold px-4 py-2 rounded-xl hover:opacity-90 transition-all">
                        <?= htmlspecialchars(__('journal_nav.next'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php else: ?>
                    <span class="bg-gray-100 text-gray-400 text-xs font-bold px-4 py-2 rounded-xl"><?= htmlspecialchars(__('journal_nav.next'), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Editor -->
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
            <!-- Page Title -->
            <div class="border-b border-gray-100 p-6">
                <input type="text" id="page-title" 
                       value="<?php echo htmlspecialchars($active_page['title'] ?? 'Page ' . $current_page); ?>"
                       class="w-full text-xl font-black text-dark border-0 outline-none placeholder-gray-300"
                       placeholder="<?= htmlspecialchars(__('journal_nav.page_title_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            
            <!-- Content Area -->
            <div id="content-area" class="p-6 min-h-[500px]">
                <?php if (!empty($active_page['content'])): ?>
                    <?php echo $active_page['content']; ?>
                <?php else: ?>
                    <div class="content-block text-gray-600 leading-relaxed" contenteditable="true" placeholder="<?= htmlspecialchars(__('journal_nav.start_writing_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
                        <p class="mb-4"></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page Actions -->
        <div class="flex items-center justify-between mt-6">
            <div class="flex items-center space-x-2">
                <?php if ($page_count > 1): ?>
                    <form action="journal_page_delete_15.php" method="POST" 
                          onsubmit="return confirm(<?= json_encode(__('journal_edit.js_delete_page_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                        <input type="hidden" name="page_id" value="<?php echo $active_page['id']; ?>">
                        <input type="hidden" name="journal_id" value="<?php echo $journal_id; ?>">
                        <button type="submit" class="text-red-500 hover:text-red-600 text-xs font-bold flex items-center space-x-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            <span><?= htmlspecialchars(__('journal_edit.delete_this_page'), ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </form>
                <?php endif; ?>
                
                <!-- Durée de vie restante -->
                <div class="life-indicator rounded-xl px-3 py-2 flex items-center space-x-2">
                    <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs font-bold text-dark"><?= htmlspecialchars(__('journal_nav.years_left', ['years' => (string) $years_left]), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                
                <!-- Bouton Supprimer Journal (si autorisé) -->
                <?php if ($can_delete): ?>
                    <a href="journal_delete.php?id=<?php echo $journal_id; ?>" 
                       class="delete-btn text-white text-xs font-bold px-4 py-2 rounded-xl flex items-center space-x-1 transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span><?= htmlspecialchars(__('journal_edit.delete_journal'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endif; ?>
            </div>
            
            <div id="save-status" class="text-xs font-bold text-gray-400"></div>
        </div>

        <!-- Bouton Ajouter une page (en bas) -->
        <div class="flex justify-center mt-6">
            <form action="journal_page_add_14.php" method="POST" class="w-full max-w-sm">
                <input type="hidden" name="journal_id" value="<?php echo $journal_id; ?>">
                <button type="submit" class="w-full bg-green-500 text-white font-bold py-4 rounded-2xl flex items-center justify-center space-x-2 hover:bg-green-600 transition-all shadow-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span><?= htmlspecialchars(__('journal_nav.add_page'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </form>
        </div>

    </main>

    <script>
        function addTextBlock() {
            const contentArea = document.getElementById('content-area');
            const newBlock = document.createElement('div');
            newBlock.className = 'content-block text-gray-600 leading-relaxed mt-4';
            newBlock.contentEditable = true;
            newBlock.placeholder = <?= json_encode(__('journal_nav.new_paragraph_placeholder'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            newBlock.innerHTML = '<p class="mb-4"><br></p>';
            contentArea.appendChild(newBlock);
            newBlock.focus();
        }

        function uploadImage(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('image', input.files[0]);
                formData.append('journal_id', '<?php echo $journal_id; ?>');
                formData.append('page_id', '<?php echo $active_page['id']; ?>');

                fetch('journal_image_upload_16.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const contentArea = document.getElementById('content-area');
                        const imgContainer = document.createElement('div');
                        imgContainer.className = 'my-4';
                        imgContainer.innerHTML = '<img src="' + data.path + '" alt="" class="max-w-full rounded-xl shadow-lg">';
                        contentArea.appendChild(imgContainer);
                        showStatus(<?= json_encode(__('journal_edit.js_image_added'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
                    } else {
                        alert(String(<?= json_encode(__('journal_edit.js_upload_error'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>).replace(':error', String(data.error)));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(<?= json_encode(__('journal_edit.js_upload_failed'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
                });
            }
        }

        function savePage() {
            const title = document.getElementById('page-title').value;
            const content = document.getElementById('content-area').innerHTML;
            
            const formData = new FormData();
            formData.append('page_id', '<?php echo $active_page['id']; ?>');
            formData.append('journal_id', '<?php echo $journal_id; ?>');
            formData.append('title', title);
            formData.append('content', content);

            fetch('journal_page_save_17.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(<?= json_encode(__('journal_edit.saved'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
                } else {
                    showStatus(<?= json_encode(__('journal_edit.save_error'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatus(<?= json_encode(__('journal_edit.save_error'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            });
        }

        function showStatus(message) {
            const status = document.getElementById('save-status');
            status.textContent = message;
            setTimeout(() => {
                status.textContent = '';
            }, 3000);
        }

        // Auto-save every 30 seconds
        setInterval(savePage, 30000);
    </script>
</body>
</html>
