<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_send.php
 * DESCRIPTION : Composer un message - À une personne OU en masse (filtres)
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$BULK_COST = 100;

// Mode : 'single' (1 personne) ou 'bulk' (envoi en masse)
$mode = ($_GET['mode'] ?? '') === 'bulk' ? 'bulk' : 'single';

// Vérifier les crédits (créer par défaut si inexistant)
$credits_stmt = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ?");
$credits_stmt->execute([$user_code]);
$credits = $credits_stmt->fetch();
if (!$credits) {
    $pdo->prepare("INSERT INTO message_credits (user_code, total_credits, remaining_credits) VALUES (?, 100, 100)")->execute([$user_code]);
    $remaining = 100;
} else {
    $remaining = (int) ($credits['remaining_credits'] ?? 0);
}
$has_enough_single = $remaining >= 1;
$has_enough_bulk = $remaining >= $BULK_COST;

// === MODE SINGLE === Récupérer le destinataire pré-sélectionné si présent
$to_code = trim((string)($_GET['to'] ?? ''));
$recipient = null;
$recipientProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'users', 'profile_pic');
$context = trim((string)($_GET['context'] ?? ''));
$request_id = (int)($_GET['request_id'] ?? 0);
$journal_id = (int)($_GET['journal_id'] ?? 0);
$is_access_request_context = ($context === 'access_request' && $request_id > 0);
$request_context_note = '';

if (!empty($to_code)) {
    $recipient_stmt = $pdo->prepare("
        SELECT user_code, first_name, last_name, {$recipientProfilePicSelect}, city 
        FROM users 
        WHERE user_code = ? AND user_code != ?
        LIMIT 1
    ");
    $recipient_stmt->execute([strtoupper($to_code), $user_code]);
    $recipient = $recipient_stmt->fetch();
    if ($recipient) {
        $mode = 'single'; // Force single mode si destinataire spécifique
    }
}

if ($is_access_request_context && !empty($recipient)) {
    $request_context_note = 'Discussion liée à la demande D' . $request_id . ' (journal #' . $journal_id . ').';
}

// === MODE SINGLE === Recherche d'utilisateurs
$search_results = [];
$search_term = trim((string)($_GET['search'] ?? ''));

if ($mode === 'single' && !empty($search_term) && strlen($search_term) >= 2) {
    $search_sql = "
        SELECT user_code, first_name, last_name, {$recipientProfilePicSelect}, city, gender
        FROM users 
        WHERE user_code != ? 
        AND (user_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR city LIKE ?)
        AND user_code NOT IN (SELECT blocked_user_code FROM user_blocks WHERE blocker_user_code = ?)
        LIMIT 10
    ";
    $search_param = '%' . $search_term . '%';
    $search_stmt = $pdo->prepare($search_sql);
    $search_stmt->execute([$user_code, $search_param, $search_param, $search_param, $search_param, $user_code]);
    $search_results = $search_stmt->fetchAll();
}

$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Message - GNTOMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
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

        function selectGeo(name, type) {
            if (type === 'city') {
                document.getElementById('filter-city-input').value = name;
                document.getElementById('filter-city-suggestions').innerHTML = '';
                document.getElementById('filter-city-suggestions').classList.add('hidden');
            } else if (type === 'commune') {
                document.getElementById('filter-commune-input').value = name;
                document.getElementById('filter-commune-suggestions').innerHTML = '';
                document.getElementById('filter-commune-suggestions').classList.add('hidden');
            }
        }

        document.addEventListener('click', function(e) {
            const cityS = document.getElementById('filter-city-suggestions');
            const commS = document.getElementById('filter-commune-suggestions');
            if (cityS && !e.target.closest('#filter-city-input') && !e.target.closest('#filter-city-suggestions')) {
                cityS.classList.add('hidden');
            }
            if (commS && !e.target.closest('#filter-commune-input') && !e.target.closest('#filter-commune-suggestions')) {
                commS.classList.add('hidden');
            }
        });
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
        .mode-tab.active { background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.08); color: #1D1D1F; }
        .mode-tab.inactive { color: #6B7280; }
        .mode-tab.inactive:hover { color: #1D1D1F; }
        
        @media (max-width: 640px) {
            .glass-panel { border-radius: 1.5rem; }
            .text-2xl { font-size: 1.25rem; }
        }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-3 sm:px-4 py-3 sm:py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="messages_list.php" class="w-9 h-9 sm:w-10 sm:h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-base sm:text-lg font-bold text-dark">Nouveau Message</h1>
            <div class="w-9 sm:w-10"></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-3 sm:space-y-4">

        <!-- Crédits -->
        <div class="glass-panel rounded-2xl sm:rounded-[2rem] p-3 sm:p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a1 1 0 11-2 0 1 1 0 012 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] sm:text-xs text-gray-500 font-bold uppercase tracking-wide">Crédits disponibles</p>
                    <p class="text-lg sm:text-xl font-black text-dark"><?= number_format($remaining) ?></p>
                </div>
            </div>
            <a href="messages_buy.php" class="text-xs sm:text-sm text-primary font-bold hover:underline">+ Recharger</a>
        </div>

        <?php if ($error === 'blocked'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center">Cet utilisateur vous a bloqué</p>
        </div>
        <?php endif; ?>
        <?php if ($error === 'no_recipients'): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-yellow-700 text-center">Aucun utilisateur ne correspond à vos critères</p>
        </div>
        <?php endif; ?>

        <?php if (!$recipient): ?>
        <!-- Toggle de mode (Single vs Bulk) -->
        <div class="glass-panel rounded-2xl sm:rounded-[2rem] p-1.5 grid grid-cols-2 gap-1.5">
            <a href="?mode=single" class="mode-tab <?= $mode === 'single' ? 'active' : 'inactive' ?> py-2.5 sm:py-3 px-3 rounded-xl sm:rounded-2xl text-center font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-1.5 sm:gap-2">
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>À une personne</span>
            </a>
            <a href="?mode=bulk" class="mode-tab <?= $mode === 'bulk' ? 'active' : 'inactive' ?> py-2.5 sm:py-3 px-3 rounded-xl sm:rounded-2xl text-center font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-1.5 sm:gap-2">
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>En masse</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($mode === 'single'): ?>
        <!-- ============================ MODE SINGLE ============================ -->
        <?php if (!$recipient): ?>
        <!-- Recherche de destinataire -->
        <div class="glass-panel rounded-2xl sm:rounded-[2rem] p-4 sm:p-5">
            <h2 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Choisir le destinataire</h2>
            
            <form method="GET" class="relative mb-4">
                <input type="hidden" name="mode" value="single">
                <div class="absolute inset-y-0 left-3 sm:left-4 flex items-center pointer-events-none text-gray-400">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="search" 
                       value="<?= htmlspecialchars($search_term) ?>"
                       placeholder="Nom, code (A3) ou ville..." 
                       autofocus
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 sm:pl-12 pr-4 py-2.5 sm:py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
            </form>

            <?php if (!empty($search_results)): ?>
            <div class="space-y-2">
                <?php foreach ($search_results as $user): ?>
                <a href="?to=<?= $user['user_code'] ?>" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-blue-50 transition-all border border-transparent hover:border-blue-100">
                    <?php $profile_pic = !empty($user['profile_pic']) ? '../' . $user['profile_pic'] : '../images/user_default.png'; ?>
                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl object-cover border border-gray-100">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-dark text-xs sm:text-sm truncate">
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </p>
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            <?= $user['user_code'] ?> 
                            <?php if ($user['city']): ?>• <?= htmlspecialchars($user['city']) ?><?php endif; ?>
                        </p>
                    </div>
                    <svg class="h-4 w-4 sm:h-5 sm:w-5 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($search_term)): ?>
            <p class="text-center text-gray-400 text-sm py-6">Aucun utilisateur trouvé pour "<?= htmlspecialchars($search_term) ?>"</p>
            <?php else: ?>
            <div class="text-center py-6">
                <div class="w-14 h-14 sm:w-16 sm:h-16 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="h-7 w-7 sm:h-8 sm:w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <p class="text-gray-400 text-xs sm:text-sm">Tapez un nom, code ou ville pour rechercher</p>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Formulaire de message à une personne -->
        <div class="glass-panel rounded-2xl sm:rounded-[2rem] p-4 sm:p-5">
            <!-- Carte du destinataire sélectionné -->
            <div class="flex items-center space-x-3 mb-5 pb-4 border-b border-gray-100">
                <?php $profile_pic = !empty($recipient['profile_pic']) ? '../' . $recipient['profile_pic'] : '../images/user_default.png'; ?>
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="" class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl object-cover border border-gray-100">
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Destinataire</p>
                    <p class="font-bold text-dark text-sm sm:text-base truncate"><?= htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']) ?></p>
                    <p class="text-[10px] sm:text-xs text-gray-500"><?= $recipient['user_code'] ?><?php if ($recipient['city']): ?> • <?= htmlspecialchars($recipient['city']) ?><?php endif; ?></p>
                </div>
                <a href="message_send.php?mode=single" class="text-xs text-primary font-bold hover:underline flex-shrink-0">Changer</a>
            </div>

            <?php if ($request_context_note !== ''): ?>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-3 mb-4">
                <p class="text-[11px] font-bold text-orange-700"><?= htmlspecialchars($request_context_note) ?></p>
            </div>
            <?php endif; ?>

            <form action="message_send_process.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="recipient_code" value="<?= htmlspecialchars($recipient['user_code']) ?>">
                <input type="hidden" name="context" value="<?= htmlspecialchars($context) ?>">
                <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
                <input type="hidden" name="journal_id" value="<?= (int)$journal_id ?>">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Votre message</label>
                    <textarea name="content" rows="5" 
                              placeholder="<?= $request_context_note !== '' ? 'Ex: Bonjour, concernant la demande D' . $request_id . ', voici ma proposition...' : 'Écrivez votre message...' ?>"
                              class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm focus:ring-2 focus:ring-primary outline-none resize-none"
                              required></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Mots-clés (optionnel)</label>
                    <input type="text" name="keywords" 
                           placeholder="Ex: travail, opportunité, amitié" 
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Image (optionnel)</label>
                    <input type="file" name="attachment" accept="image/*" 
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                </div>

                <div class="bg-gray-50 rounded-2xl p-4 flex items-center justify-between mt-2">
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold">Coût</p>
                        <p class="text-base sm:text-lg font-black text-primary">1 crédit</p>
                    </div>
                    <?php if ($has_enough_single): ?>
                    <button type="submit" class="bg-primary text-white font-bold py-2.5 sm:py-3 px-5 sm:px-7 rounded-xl hover:bg-blue-600 transition-all flex items-center space-x-2 text-sm shadow-md shadow-blue-500/30">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        <span>Envoyer</span>
                    </button>
                    <?php else: ?>
                    <a href="messages_buy.php" class="bg-gray-300 text-gray-600 font-bold py-2.5 sm:py-3 px-5 sm:px-7 rounded-xl text-sm">
                        Acheter crédits
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ============================ MODE BULK ============================ -->
        
        <?php if (!$has_enough_bulk): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-start gap-3">
            <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold text-red-800">Crédits insuffisants</p>
                <p class="text-xs text-red-700 mt-1">Vous avez <?= number_format($remaining) ?> crédits, il en faut <?= $BULK_COST ?>. <a href="messages_buy.php" class="font-bold underline">Acheter →</a></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info coût -->
        <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="h-5 w-5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-xs text-orange-600 font-bold uppercase tracking-wide">Coût fixe</p>
                <p class="text-sm font-bold text-dark">100 crédits par envoi en masse</p>
                <p class="text-[10px] text-gray-500 mt-0.5">Quel que soit le nombre de destinataires correspondants</p>
            </div>
        </div>

        <form action="message_bulk_process.php" method="POST" enctype="multipart/form-data" class="glass-panel rounded-2xl sm:rounded-[2rem] p-4 sm:p-5 space-y-4">
            
            <!-- Filtres de destinataires -->
            <div>
                <h2 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Filtres de destinataires</h2>
                
                <div class="space-y-4">
                    <!-- Sexe -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Sexe</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="flex items-center justify-center gap-1.5 p-2.5 sm:p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-all border-2 border-transparent has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" name="filter_gender" value="all" checked class="sr-only">
                                <span class="text-xs sm:text-sm font-bold text-dark">Tous</span>
                            </label>
                            <label class="flex items-center justify-center gap-1.5 p-2.5 sm:p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-all border-2 border-transparent has-[:checked]:border-pink-500 has-[:checked]:bg-pink-50">
                                <input type="radio" name="filter_gender" value="female" class="sr-only">
                                <span class="text-xs sm:text-sm font-bold text-dark">♀ Femmes</span>
                            </label>
                            <label class="flex items-center justify-center gap-1.5 p-2.5 sm:p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-all border-2 border-transparent has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="filter_gender" value="male" class="sr-only">
                                <span class="text-xs sm:text-sm font-bold text-dark">♂ Hommes</span>
                            </label>
                        </div>
                    </div>

                    <!-- Ville -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Ville (optionnel)</label>
                        <input type="text" name="filter_city" id="filter-city-input"
                               placeholder="Ex: Paris, Kinshasa, Londres..."
                               hx-get="geo_autocomplete.php?type=city"
                               hx-trigger="keyup changed delay:300ms"
                               hx-target="#filter-city-suggestions"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 sm:py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <div id="filter-city-suggestions" class="absolute z-10 w-full bg-white border border-gray-200 rounded-xl mt-1 shadow-lg hidden max-h-48 overflow-y-auto"></div>
                    </div>

                    <!-- Commune -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Commune / Quartier (optionnel)</label>
                        <input type="text" name="filter_commune" id="filter-commune-input"
                               placeholder="Ex: Le Marais, Gombe..."
                               hx-get="geo_autocomplete.php?type=commune"
                               hx-trigger="keyup changed delay:300ms"
                               hx-target="#filter-commune-suggestions"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 sm:py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <div id="filter-commune-suggestions" class="absolute z-10 w-full bg-white border border-gray-200 rounded-xl mt-1 shadow-lg hidden max-h-48 overflow-y-auto"></div>
                    </div>

                    <!-- Tranche d'âge -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tranche d'âge (optionnel)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <select name="filter_age_min" class="bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                                <option value="">Âge min</option>
                                <?php for ($i = 18; $i <= 70; $i += 5): ?>
                                <option value="<?= $i ?>"><?= $i ?> ans</option>
                                <?php endfor; ?>
                            </select>
                            <select name="filter_age_max" class="bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                                <option value="">Âge max</option>
                                <?php for ($i = 25; $i <= 80; $i += 5): ?>
                                <option value="<?= $i ?>"><?= $i ?> ans</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="border-gray-100">

            <!-- Contenu du message -->
            <div>
                <h2 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Votre message</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Contenu</label>
                        <textarea name="content" rows="5" 
                                  placeholder="Écrivez votre message à diffuser..." 
                                  class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm focus:ring-2 focus:ring-primary outline-none resize-none"
                                  required></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Mots-clés (pour filtrage)</label>
                        <input type="text" name="keywords" 
                               placeholder="Ex: opportunité, collaboration, networking"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 sm:py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Image (optionnel)</label>
                        <input type="file" name="attachment" accept="image/*" 
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 sm:py-3 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    </div>
                </div>
            </div>

            <!-- Coût et envoi -->
            <div class="bg-gray-50 rounded-2xl p-4 flex items-center justify-between mt-2">
                <div>
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold">Coût total</p>
                    <p class="text-base sm:text-lg font-black text-orange-600">100 crédits</p>
                </div>
                <?php if ($has_enough_bulk): ?>
                <button type="submit" class="bg-orange-500 text-white font-bold py-2.5 sm:py-3 px-5 sm:px-7 rounded-xl hover:bg-orange-600 transition-all flex items-center space-x-2 text-sm shadow-md shadow-orange-500/30">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    <span>Envoyer en masse</span>
                </button>
                <?php else: ?>
                <a href="messages_buy.php" class="bg-gray-300 text-gray-600 font-bold py-2.5 sm:py-3 px-5 sm:px-7 rounded-xl text-sm">
                    Acheter crédits
                </a>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>

    </main>

</body>
</html>
