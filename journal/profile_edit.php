<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/profile_edit.php
 * DESCRIPTION : Édition du profil utilisateur avec champs enrichis
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];

// Récupérer les infos actuelles
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_code = ? LIMIT 1");
$stmt->execute([$user_code]);
$user = $stmt->fetch();

if (is_array($user) && !array_key_exists('profile_pic', $user)) {
    $user['profile_pic'] = null;
}

if (!$user) {
    header("Location: dashboard_6.php?error=user_not_found");
    exit;
}

// Récupérer les crédits messages
try {
    $credits_stmt = $pdo->prepare("SELECT remaining_credits, total_credits, used_credits FROM message_credits WHERE user_code = ?");
    $credits_stmt->execute([$user_code]);
    $msg_credits = $credits_stmt->fetch();
    if (!$msg_credits) {
        $pdo->prepare("INSERT INTO message_credits (user_code, total_credits, remaining_credits) VALUES (?, 100, 100)")->execute([$user_code]);
        $msg_credits = ['remaining_credits' => 100, 'total_credits' => 100, 'used_credits' => 0];
    }

    // Messages non lus
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_user_code = ? AND is_read = 0");
    $unread_stmt->execute([$user_code]);
    $unread_count = $unread_stmt->fetch()['total'] ?? 0;

    // Nombre de conversations
    $threads_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM message_threads WHERE participant_1 = ? OR participant_2 = ?");
    $threads_stmt->execute([$user_code, $user_code]);
    $threads_count = $threads_stmt->fetch()['total'] ?? 0;

    // Nombre de journaux
    $journals_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM journals WHERE user_code = ?");
    $journals_stmt->execute([$user_code]);
    $journals_count = $journals_stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $msg_credits = ['remaining_credits' => 0, 'total_credits' => 0, 'used_credits' => 0];
    $unread_count = 0;
    $threads_count = 0;
    $journals_count = 0;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? null;
    $birth_date = $_POST['birth_date'] ?? null;
    $city = trim($_POST['city'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $country = trim($_POST['country'] ?? 'RDC');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $profile_visibility = $_POST['profile_visibility'] ?? 'public';
    
    try {
        $update = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, gender = ?, birth_date = ?, 
                city = ?, commune = ?, country = ?, bio = ?, phone = ?, 
                profile_visibility = ?, updated_at = NOW()
            WHERE user_code = ?
        ");
        $update->execute([
            $first_name, $last_name, $gender, $birth_date,
            $city, $commune, $country, $bio, $phone,
            $profile_visibility, $user_code
        ]);
        
        // Mise à jour de la session
        $_SESSION['name'] = $first_name . ' ' . $last_name;
        
        header("Location: profile_edit.php?success=updated");
        exit;
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour";
    }
}

$success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - GNTOMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
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

        function selectGeo(name, type) {
            if (type === 'city') {
                document.getElementById('city-input').value = name;
                document.getElementById('city-suggestions').innerHTML = '';
                document.getElementById('city-suggestions').classList.add('hidden');
            } else if (type === 'commune') {
                document.getElementById('commune-input').value = name;
                document.getElementById('commune-suggestions').innerHTML = '';
                document.getElementById('commune-suggestions').classList.add('hidden');
            }
        }

        // Cacher les suggestions quand on clique ailleurs
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#city-input') && !e.target.closest('#city-suggestions')) {
                document.getElementById('city-suggestions').classList.add('hidden');
            }
            if (!e.target.closest('#commune-input') && !e.target.closest('#commune-suggestions')) {
                document.getElementById('commune-suggestions').classList.add('hidden');
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
        
        /* Mobile-first improvements */
        @media (max-width: 640px) {
            .glass-panel { border-radius: 1.5rem; padding: 1rem; }
            .text-2xl { font-size: 1.25rem; }
            .text-xl { font-size: 1.125rem; }
            .p-6 { padding: 0.875rem; }
            .p-8 { padding: 1rem; }
            .py-4 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
            .px-4 { padding-left: 0.75rem; padding-right: 0.75rem; }
            .py-3 { padding-top: 0.625rem; padding-bottom: 0.625rem; }
            .space-y-6 > div { margin-bottom: 0.875rem; }
            .gap-4 { gap: 0.875rem; }
        }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-3 sm:px-4 py-3 sm:py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <a href="dashboard_6.php" class="w-9 h-9 sm:w-10 sm:h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-base sm:text-lg font-bold text-dark">Mon Profil</h1>
            <div class="w-9 sm:w-10"></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-6">
        
        <?php if ($success === 'updated'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center">Profil mis à jour avec succès !</p>
        </div>
        <?php elseif ($success === 'photo_updated'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center">Photo de profil mise à jour !</p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center">Erreur lors de l'upload de la photo</p>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'file_too_large'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center">Image trop grande (max 5 Mo)</p>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'invalid_type'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center">Format non autorisé (JPG, PNG, GIF, WebP)</p>
        </div>
        <?php endif; ?>

        <!-- Photo de profil + identité rapide -->
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-6 shadow-sm">
                <?php $profile_pic = !empty($user['profile_pic']) ? '../' . $user['profile_pic'] : '../images/user_default.png'; ?>
                <div class="relative">
                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profil" class="w-16 h-16 sm:w-20 sm:h-20 rounded-[1.25rem] sm:rounded-[1.5rem] border-2 border-white object-cover shadow-md">
                    <label for="photo" class="absolute -bottom-2 -right-2 bg-primary text-white p-1.5 sm:p-2 rounded-xl cursor-pointer hover:bg-blue-600 transition-all shadow-lg">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 6H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/*" class="hidden" onchange="document.getElementById('photo-form').submit()">
                </div>
                <div class="flex-1">
                    <p class="font-black text-dark text-sm sm:text-base"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
                    <p class="text-primary font-bold text-xs sm:text-sm"><?= htmlspecialchars($user['user_code']) ?></p>
                    <?php if (!empty($user['city'])): ?>
                    <p class="text-xs text-gray-500 mt-1 flex items-center space-x-1">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                        <span><?= htmlspecialchars($user['city']) ?><?= !empty($user['commune']) ? ', ' . htmlspecialchars($user['commune']) : '' ?></span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="grid grid-cols-3 gap-3 mt-5 pt-5 border-t border-gray-100">
                <div class="text-center">
                    <p class="text-lg font-black text-dark"><?= $journals_count ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Journaux</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-black text-dark"><?= $threads_count ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Conversations</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-black text-primary"><?= number_format($msg_credits['remaining_credits'] ?? 0) ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase">Crédits msg</p>
                </div>
            </div>
        </div>

        <!-- Section Messagerie -->
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-bold text-dark mb-3 sm:mb-4 flex items-center space-x-2">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <span>Messagerie</span>
                <?php if ($unread_count > 0): ?>
                <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $unread_count ?> non lu<?= $unread_count > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </h2>

            <div class="space-y-2">
                <a href="messages_list.php" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-dark text-sm">Mes conversations</p>
                            <p class="text-[10px] text-gray-500"><?= $threads_count ?> conversation<?= $threads_count > 1 ? 's' : '' ?></p>
                        </div>
                    </div>
                    <?php if ($unread_count > 0): ?>
                    <span class="bg-red-500 text-white text-[10px] font-bold w-6 h-6 rounded-full flex items-center justify-center"><?= $unread_count ?></span>
                    <?php else: ?>
                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <?php endif; ?>
                </a>

                <a href="message_send.php" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-dark text-sm">Envoyer un message</p>
                            <p class="text-[10px] text-gray-500">1 crédit par message</p>
                        </div>
                    </div>
                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>

                <a href="message_bulk.php" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                            <svg class="h-5 w-5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-dark text-sm">Message groupé</p>
                            <p class="text-[10px] text-gray-500">Filtrer par sexe, ville, âge • 100 crédits</p>
                        </div>
                    </div>
                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>

                <a href="messages_filters.php" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                            <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-dark text-sm">Filtrer mes messages</p>
                            <p class="text-[10px] text-gray-500">Non lus, images, mots-clés</p>
                        </div>
                    </div>
                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>

            <!-- Crédits et achat -->
            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase">Crédits restants</p>
                    <p class="text-2xl font-black text-dark"><?= number_format($msg_credits['remaining_credits'] ?? 0) ?></p>
                </div>
                <a href="messages_buy.php" class="bg-primary text-white font-bold py-2.5 px-5 rounded-xl text-sm hover:bg-blue-600 transition-all flex items-center space-x-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <span>Acheter</span>
                </a>
            </div>
        </div>

        <!-- Formulaire profil -->
        <form method="POST" class="space-y-6">
            
            <!-- Identité -->
            <div class="glass-panel rounded-[2rem] p-6 shadow-sm">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Identité</span>
                </h2>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Prénom</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nom</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Sexe</label>
                            <select name="gender" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                                <option value="">Sélectionner</option>
                                <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Homme</option>
                                <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Femme</option>
                                <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Date de naissance</label>
                            <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Localisation -->
            <div class="glass-panel rounded-[2rem] p-6 shadow-sm">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Localisation</span>
                </h2>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="relative">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Ville</label>
                            <input type="text" name="city" id="city-input" value="<?= htmlspecialchars($user['city'] ?? '') ?>" 
                                   placeholder="Ex: Paris"
                                   hx-get="geo_autocomplete.php?q={value}&type=city"
                                   hx-trigger="keyup changed delay:300ms"
                                   hx-target="#city-suggestions"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                            <div id="city-suggestions" class="absolute z-10 w-full bg-white border border-gray-200 rounded-xl mt-1 shadow-lg hidden max-h-48 overflow-y-auto"></div>
                        </div>
                        <div class="relative">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Commune/Quartier</label>
                            <input type="text" name="commune" id="commune-input" value="<?= htmlspecialchars($user['commune'] ?? '') ?>" 
                                   placeholder="Ex: Le Marais"
                                   hx-get="geo_autocomplete.php?q={value}&type=commune"
                                   hx-trigger="keyup changed delay:300ms"
                                   hx-target="#commune-suggestions"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                            <div id="commune-suggestions" class="absolute z-10 w-full bg-white border border-gray-200 rounded-xl mt-1 shadow-lg hidden max-h-48 overflow-y-auto"></div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Pays</label>
                        <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? 'RDC') ?>" 
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>
            </div>

            <!-- Contact & Bio -->
            <div class="glass-panel rounded-[2rem] p-6 shadow-sm">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>Contact & Bio</span>
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Téléphone</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               placeholder="+243..."
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Biographie</label>
                        <textarea name="bio" rows="3" placeholder="Décrivez-vous en quelques mots..."
                                  class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none resize-none"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Confidentialité -->
            <div class="glass-panel rounded-[2rem] p-6 shadow-sm">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>Confidentialité</span>
                </h2>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Visibilité du profil</label>
                    <select name="profile_visibility" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="public" <?= ($user['profile_visibility'] ?? '') === 'public' ? 'selected' : '' ?>>Public - Tout le monde peut voir</option>
                        <option value="friends" <?= ($user['profile_visibility'] ?? '') === 'friends' ? 'selected' : '' ?>>Amis uniquement</option>
                        <option value="private" <?= ($user['profile_visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Privé - Personne ne peut voir</option>
                    </select>
                </div>
            </div>

            <!-- Bouton de sauvegarde -->
            <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-blue-600 transition-all">
                Enregistrer les modifications
            </button>
            
        </form>
    </main>

</body>
</html>
