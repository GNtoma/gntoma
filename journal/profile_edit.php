<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/profile_edit.php
 * DESCRIPTION : Édition du profil utilisateur avec champs enrichis
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/notif_sound_toggle_inc.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = gntoma_normalize_user_code((string) $_SESSION['user_id']) ?? '';
if ($user_code === '') {
    header("Location: ../index.php");
    exit;
}

// Infos actuelles : uniquement la ligne dont le code correspond à la session normalisée
$stmt = $pdo->prepare("SELECT * FROM users WHERE UPPER(TRIM(user_code)) = ? LIMIT 1");
$stmt->execute([$user_code]);
$user = $stmt->fetch();

if (is_array($user) && !array_key_exists('profile_pic', $user)) {
    $user['profile_pic'] = null;
}
if (is_array($user) && !array_key_exists('commune', $user)) {
    $user['commune'] = '';
}
if (is_array($user) && !array_key_exists('gender', $user)) {
    $user['gender'] = '';
}
if (is_array($user) && !array_key_exists('birth_date', $user)) {
    $user['birth_date'] = '';
}

if (!$user) {
    header("Location: dashboard_6.php?error=user_not_found");
    exit;
}

$canonical_code = gntoma_normalize_user_code((string) ($user['user_code'] ?? '')) ?? $user_code;

// Crédits messages & stats
try {
    $credits_stmt = $pdo->prepare("SELECT remaining_credits, total_credits, used_credits FROM message_credits WHERE UPPER(TRIM(user_code)) = ?");
    $credits_stmt->execute([$canonical_code]);
    $msg_credits = $credits_stmt->fetch();
    if (!$msg_credits) {
        $pdo->prepare("INSERT INTO message_credits (user_code, total_credits, remaining_credits) VALUES (?, 100, 100)")->execute([$canonical_code]);
        $msg_credits = ['remaining_credits' => 100, 'total_credits' => 100, 'used_credits' => 0];
    }

    $unread_count = gntoma_unread_messages_in_inbox_count($pdo, $canonical_code);

    $threads_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM message_threads WHERE participant_1 = ? OR participant_2 = ?");
    $threads_stmt->execute([$canonical_code, $canonical_code]);
    $threads_count = $threads_stmt->fetch()['total'] ?? 0;

    $journals_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM journals WHERE UPPER(TRIM(user_code)) = ?");
    $journals_stmt->execute([$canonical_code]);
    $journals_count = $journals_stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $msg_credits = ['remaining_credits' => 0, 'total_credits' => 0, 'used_credits' => 0];
    $unread_count = 0;
    $threads_count = 0;
    $journals_count = 0;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf'] ?? '');
    if (!gntoma_profile_validate_csrf($postedCsrf)) {
        $error = __('profile_edit.err_csrf');
    } else {
        $first_name = trim((string) ($_POST['first_name'] ?? ''));
        $last_name = trim((string) ($_POST['last_name'] ?? ''));
        $gender_raw = trim((string) ($_POST['gender'] ?? ''));
        $birth_raw = trim((string) ($_POST['birth_date'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $profile_visibility = (string) ($_POST['profile_visibility'] ?? 'public');

        if ($first_name === '' || $last_name === '') {
            $error = __('profile_edit.err_names_required');
        } elseif (mb_strlen($first_name) > 80 || mb_strlen($last_name) > 80) {
            $error = __('profile_edit.err_names_length');
        } elseif ($gender_raw !== '' && !in_array($gender_raw, ['male', 'female', 'other'], true)) {
            $error = __('profile_edit.err_gender');
        } elseif (!in_array($profile_visibility, ['public', 'friends', 'private'], true)) {
            $error = __('profile_edit.err_visibility');
        } elseif (mb_strlen($bio) > 5000) {
            $error = __('profile_edit.err_bio_length');
        } elseif (mb_strlen($phone) > 50) {
            $error = __('profile_edit.err_phone_length');
        }

        $gender = $gender_raw === '' ? null : $gender_raw;
        $birth_date = null;
        if ($error === '' && $birth_raw !== '') {
            $bd = DateTimeImmutable::createFromFormat('Y-m-d', $birth_raw);
            if ($bd === false || $bd->format('Y-m-d') !== $birth_raw) {
                $error = __('profile_edit.err_birth_invalid');
            } else {
                $today = new DateTimeImmutable('today');
                if ($bd > $today) {
                    $error = __('profile_edit.err_birth_future');
                } else {
                    $age = $bd->diff($today)->y;
                    if ($age < 13 || $age > 115) {
                        $error = __('profile_edit.err_birth_age');
                    } else {
                        $birth_date = $birth_raw;
                    }
                }
            }
        }

        $locale_pref = strtolower(trim((string) ($_POST['locale'] ?? '')));
        if ($error === '' && in_array($locale_pref, ['fr', 'en'], true)) {
            gntoma_set_locale($locale_pref);
        }

        $locationPlace = null;
        if ($error === '') {
            $locValidation = gntoma_profile_resolve_location_for_save($user, $_POST);
            if (!$locValidation['ok']) {
                $error = match ($locValidation['error'] ?? '') {
                    'required' => __('profile_edit.err_location_required'),
                    'unavailable' => __('geonames.error_unavailable'),
                    default => __('profile_edit.err_location_invalid'),
                };
            } else {
                $locationPlace = $locValidation['place'];
            }
        }

        if ($error === '' && is_array($locationPlace)) {
            try {
                $legacyCity = (string) $locationPlace['name'];
                $legacyCommune = $locationPlace['admin2'] ?? $locationPlace['admin1'] ?? null;
                $legacyCountry = (string) ($locationPlace['country_name'] ?? $user['country'] ?? 'RDC');

                if (gntoma_users_has_geonames_columns($pdo) && (int) ($locationPlace['geoname_id'] ?? 0) > 0) {
                    gntoma_apply_user_location($pdo, $user_code, $locationPlace);
                }

                $update = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, gender = ?, birth_date = ?, 
                        city = ?, commune = ?, country = ?, bio = ?, phone = ?, 
                        profile_visibility = ?, updated_at = NOW()
                    WHERE UPPER(TRIM(user_code)) = ?
                ");
                $update->execute([
                    $first_name,
                    $last_name,
                    $gender,
                    $birth_date,
                    $legacyCity,
                    $legacyCommune,
                    $legacyCountry,
                    $bio === '' ? null : $bio,
                    $phone === '' ? null : $phone,
                    $profile_visibility,
                    $user_code,
                ]);

                $_SESSION['name'] = $first_name . ' ' . $last_name;

                header('Location: profile_edit.php?success=updated');
                exit;
            } catch (PDOException $e) {
                error_log('profile_edit update: ' . $e->getMessage());
                $error = __('profile_edit.err_update');
            }
        }

        if ($error !== '') {
            $user['first_name'] = $first_name;
            $user['last_name'] = $last_name;
            $user['gender'] = $gender_raw;
            $user['birth_date'] = $birth_raw;
            $user['bio'] = $bio;
            $user['phone'] = $phone;
            $user['profile_visibility'] = $profile_visibility;
        }
    }
}

$success = $_GET['success'] ?? null;

require_once __DIR__ . '/includes/gntoma_location_picker_inc.php';
$userLocation = gntoma_user_location_from_row($user);
if ($userLocation === null) {
    $legacyLabel = gntoma_location_label_from_parts(
        (string) ($user['city'] ?? ''),
        null,
        isset($user['commune']) && $user['commune'] !== '' ? (string) $user['commune'] : null,
        (string) ($user['country'] ?? '')
    );
    if ($legacyLabel !== '') {
        $userLocation = ['geoname_id' => 0, 'label' => $legacyLabel];
    }
}
$profileLocationLabel = is_array($userLocation) ? (string) ($userLocation['label'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('profile_edit.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/gntoma-location-picker.css">
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
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-2">
            <a href="dashboard_6.php" class="w-9 h-9 sm:w-10 sm:h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all flex-shrink-0">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-base sm:text-lg font-bold text-dark flex-1 text-center"><?= htmlspecialchars(__('profile_edit.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="w-9 sm:w-10 flex-shrink-0" aria-hidden="true"></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-6">
        
        <?php if ($success === 'updated'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center"><?= htmlspecialchars(__('profile_edit.success_updated'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php elseif ($success === 'photo_updated'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center"><?= htmlspecialchars(__('profile_edit.success_photo'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(__('profile_edit.err_upload'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'file_too_large'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(__('profile_edit.err_file_large'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'invalid_type'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(__('profile_edit.err_invalid_type'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(__('profile_edit.err_csrf'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'system'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(__('profile_edit.err_system'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <!-- Photo de profil + identité rapide -->
        <div id="section-photo" class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-6 shadow-sm">
            <form id="photo-form" action="profile_upload_pic.php" method="post" enctype="multipart/form-data" class="flex items-start gap-3 sm:gap-4">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(gntoma_profile_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <?php $profile_pic = !empty($user['profile_pic']) ? '../' . $user['profile_pic'] : '../images/user_default.png'; ?>
                <div class="relative flex-shrink-0">
                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="<?= htmlspecialchars(__('profile_edit.photo_alt'), ENT_QUOTES, 'UTF-8') ?>" class="w-16 h-16 sm:w-20 sm:h-20 rounded-[1.25rem] sm:rounded-[1.5rem] border-2 border-white object-cover shadow-md">
                    <label for="profile_pic_input" class="absolute -bottom-2 -right-2 bg-primary text-white p-1.5 sm:p-2 rounded-xl cursor-pointer hover:bg-blue-600 transition-all shadow-lg">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 6H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </label>
                    <input type="file" id="profile_pic_input" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" onchange="document.getElementById('photo-form').submit()">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-black text-dark text-sm sm:text-base"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
                    <p class="text-primary font-bold text-xs sm:text-sm"><?= htmlspecialchars((string) ($user['user_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($profileLocationLabel !== ''): ?>
                    <p class="text-xs text-gray-500 mt-1 flex items-center space-x-1">
                        <svg class="h-3 w-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                        <span><?= htmlspecialchars($profileLocationLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Statistiques rapides -->
            <div class="grid grid-cols-3 gap-3 mt-5 pt-5 border-t border-gray-100">
                <div class="text-center">
                    <p class="text-lg font-black text-dark"><?= $journals_count ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase"><?= htmlspecialchars(__('profile_edit.stats_journals'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-black text-dark"><?= $threads_count ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase"><?= htmlspecialchars(__('profile_edit.stats_threads'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-black text-primary"><?= number_format($msg_credits['remaining_credits'] ?? 0) ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase"><?= htmlspecialchars(__('profile_edit.stats_credits'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>

        <!-- Section Messagerie -->
        <div class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-bold text-dark mb-3 sm:mb-4 flex items-center space-x-2">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <span><?= htmlspecialchars(__('profile_edit.messaging'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($unread_count > 0): ?>
                <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= htmlspecialchars($unread_count > 1 ? __('profile_edit.unread_many', ['n' => $unread_count]) : __('profile_edit.unread_one', ['n' => $unread_count]), ENT_QUOTES, 'UTF-8') ?></span>
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
                            <p class="font-bold text-dark text-sm"><?= htmlspecialchars(__('profile_edit.my_threads'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[10px] text-gray-500"><?= htmlspecialchars($threads_count > 1 ? __('profile_edit.thread_count_many', ['n' => $threads_count]) : __('profile_edit.thread_count_one', ['n' => $threads_count]), ENT_QUOTES, 'UTF-8') ?></p>
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
                            <p class="font-bold text-dark text-sm"><?= htmlspecialchars(__('profile_edit.send_message'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[10px] text-gray-500"><?= htmlspecialchars(__('profile_edit.one_credit_each'), ENT_QUOTES, 'UTF-8') ?></p>
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
                            <p class="font-bold text-dark text-sm"><?= htmlspecialchars(__('profile_edit.bulk_message'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[10px] text-gray-500"><?= htmlspecialchars(__('profile_edit.bulk_hint'), ENT_QUOTES, 'UTF-8') ?></p>
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
                            <p class="font-bold text-dark text-sm"><?= htmlspecialchars(__('profile_edit.filter_messages'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[10px] text-gray-500"><?= htmlspecialchars(__('profile_edit.filter_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>

            <!-- Crédits et achat -->
            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase"><?= htmlspecialchars(__('profile_edit.credits_remaining'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-2xl font-black text-dark"><?= number_format($msg_credits['remaining_credits'] ?? 0) ?></p>
                </div>
                <a href="messages_buy.php" class="bg-primary text-white font-bold py-2.5 px-5 rounded-xl text-sm hover:bg-blue-600 transition-all flex items-center space-x-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <span><?= htmlspecialchars(__('profile_edit.buy'), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </div>
        </div>

        <!-- Son des notifications (même clé localStorage que le dashboard) -->
        <div id="section-notifications" class="glass-panel rounded-[1.5rem] sm:rounded-[2rem] p-4 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-bold text-dark mb-2 flex items-center space-x-2">
                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z" />
                </svg>
                <span><?= htmlspecialchars(__('profile_edit.notifications_section'), ENT_QUOTES, 'UTF-8') ?></span>
            </h2>
            <p class="text-xs text-gray-500 mb-4 leading-relaxed"><?= htmlspecialchars(__('profile_edit.sound_setting_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="flex flex-wrap items-center gap-3">
                <?php gntoma_render_notif_sound_toggle_button('inline-flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-dark hover:bg-gray-100 transition-all'); ?>
            </div>
        </div>

        <!-- Formulaire profil -->
        <form method="POST" class="space-y-6" id="profile-main-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(gntoma_profile_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <!-- Préférences -->
            <div id="section-preferences" class="glass-panel rounded-[2rem] p-6 shadow-sm scroll-mt-24">
                <h2 class="text-lg font-bold text-dark mb-4"><?= htmlspecialchars(__('profile_edit.preferences'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.locale_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="locale" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="fr" <?= gntoma_locale() === 'fr' ? 'selected' : '' ?>><?= htmlspecialchars(__('dashboard.lang_fr'), ENT_QUOTES, 'UTF-8') ?> — Français</option>
                        <option value="en" <?= gntoma_locale() === 'en' ? 'selected' : '' ?>><?= htmlspecialchars(__('dashboard.lang_en'), ENT_QUOTES, 'UTF-8') ?> — English</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2"><?= htmlspecialchars(__('profile_edit.locale_help'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            
            <!-- Identité -->
            <div id="section-identity" class="glass-panel rounded-[2rem] p-6 shadow-sm scroll-mt-24">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span><?= htmlspecialchars(__('profile_edit.identity'), ENT_QUOTES, 'UTF-8') ?></span>
                </h2>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed"><?= htmlspecialchars(__('profile_edit.demographics_help'), ENT_QUOTES, 'UTF-8') ?></p>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.first_name'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.last_name'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.gender'), ENT_QUOTES, 'UTF-8') ?></label>
                            <select name="gender" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                                <option value=""><?= htmlspecialchars(__('profile_edit.select'), ENT_QUOTES, 'UTF-8') ?></option>
                                <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= htmlspecialchars(__('profile_edit.gender_male'), ENT_QUOTES, 'UTF-8') ?></option>
                                <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= htmlspecialchars(__('profile_edit.gender_female'), ENT_QUOTES, 'UTF-8') ?></option>
                                <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>><?= htmlspecialchars(__('profile_edit.gender_other'), ENT_QUOTES, 'UTF-8') ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.birth_date'), ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Localisation -->
            <div id="section-location" class="glass-panel rounded-[2rem] p-6 shadow-sm scroll-mt-24">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span><?= htmlspecialchars(__('profile_edit.location'), ENT_QUOTES, 'UTF-8') ?></span>
                </h2>
                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 mb-4"><?= htmlspecialchars(__('profile_edit.geo_pick_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                
                <?php gntoma_render_location_picker(['id' => 'profile', 'initial' => $userLocation, 'required' => false]); ?>
            </div>

            <!-- Contact & Bio -->
            <div class="glass-panel rounded-[2rem] p-6 shadow-sm">
                <h2 class="text-lg font-bold text-dark mb-4 flex items-center space-x-2">
                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span><?= htmlspecialchars(__('profile_edit.contact_bio'), ENT_QUOTES, 'UTF-8') ?></span>
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.phone'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               placeholder="<?= htmlspecialchars(__('profile_edit.phone_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.bio'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea name="bio" rows="3" placeholder="<?= htmlspecialchars(__('profile_edit.bio_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
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
                    <span><?= htmlspecialchars(__('profile_edit.privacy'), ENT_QUOTES, 'UTF-8') ?></span>
                </h2>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('profile_edit.visibility'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="profile_visibility" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="public" <?= ($user['profile_visibility'] ?? '') === 'public' ? 'selected' : '' ?>><?= htmlspecialchars(__('profile_edit.vis_public'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="friends" <?= ($user['profile_visibility'] ?? '') === 'friends' ? 'selected' : '' ?>><?= htmlspecialchars(__('profile_edit.vis_friends'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="private" <?= ($user['profile_visibility'] ?? '') === 'private' ? 'selected' : '' ?>><?= htmlspecialchars(__('profile_edit.vis_private'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
            </div>

            <!-- Bouton de sauvegarde -->
            <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-blue-600 transition-all">
                <?= htmlspecialchars(__('profile_edit.save'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            
        </form>
    </main>

<?php gntoma_render_notif_sound_toggle_scripts(); ?>
    <script src="assets/js/gntoma-location-picker.js"></script>
</body>
</html>
