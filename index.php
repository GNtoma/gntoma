<?php
/**
 * PROJET : GNTOMA
 * FICHIER : index.php
 * DESCRIPTION : Landing Page Lucide (Neige, Mobile First, Login + Mot de passe oublié).
 */

session_start();

require_once __DIR__ . '/journal/i18n.php';
gntoma_init_locale_from_request();

// 1. REDIRECTION SI CONNECTÉ (session GNTOMA : code dans user_id et/ou user_code)
if (!empty($_SESSION['user_id']) || !empty($_SESSION['user_code'])) {
    header("Location: journal/dashboard_6.php");
    exit;
}

// 2. GESTION DES ÉTATS ET MESSAGES
$step = $_GET['step'] ?? 'login'; // Peut être 'login', 'forgot', ou 'reset'
$reset_code = strtoupper(trim((string)($_GET['code'] ?? '')));
$masked_email = trim((string)($_GET['masked_email'] ?? ''));
$raw_error = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
$journal_error_keys = [
    'journal_invalid_code' => 'landing.err_journal_invalid_code',
    'journal_author_not_found' => 'landing.err_journal_author_not_found',
    'journal_not_found' => 'landing.err_journal_not_found',
    'journal_search_error' => 'landing.err_journal_search_error',
    'journal_invalid_id' => 'landing.err_journal_invalid_id',
    'journal_system_error' => 'landing.err_journal_system_error',
];
if ($raw_error !== '' && isset($journal_error_keys[$raw_error])) {
    $error_msg = htmlspecialchars(__($journal_error_keys[$raw_error]), ENT_QUOTES, 'UTF-8');
} elseif ($raw_error !== '') {
    $error_msg = htmlspecialchars($raw_error, ENT_QUOTES, 'UTF-8');
} else {
    $error_msg = '';
}
$login_redirect_next = isset($_GET['next']) ? trim((string) $_GET['next']) : '';
$login_redirect_jid = (int) ($_GET['jid'] ?? 0);
$success_msg = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : "";
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(__('landing.page_title'), ENT_QUOTES, 'UTF-8') ?></title>

    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <?php require_once __DIR__ . '/ui_head.php'; ?>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        primary: '#007AFF',
                        dark: '#1D1D1F',
                        surface: '#F5F5F7',
                    }
                }
            }
        }
    </script>
    <style>
        body { color: #1D1D1F; -webkit-font-smoothing: antialiased; margin: 0; overflow-x: hidden; }

        /* --- GLASSMORPHISM --- */
        .glass-panel {
            background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }
        .glass-input {
            background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease;
        }
        .glass-input:focus {
            background: #ffffff; border-color: #007AFF; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1); outline: none;
        }

        .smooth-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

<?php require_once __DIR__ . '/ui_background.php'; ?>

<div class="min-h-screen flex flex-col justify-between relative z-10 gntoma-page-enter">

    <main class="flex-grow w-full max-w-7xl mx-auto px-5 py-8 md:px-8 md:pt-16 flex flex-col items-center space-y-16 md:space-y-20">
        
        <div class="w-full flex flex-col lg:grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            
            <div class="order-1 lg:order-2 w-full lg:flex lg:justify-end animate__animated animate__fadeInRight">
                <div class="glass-panel gntoma-section-shell p-6 md:p-10 rounded-[2.5rem] w-full max-w-md relative z-10 overflow-hidden">
                    
                    <div class="flex justify-end mb-2">
                        <?= gntoma_lang_switch_markup() ?>
                    </div>
                    <div class="text-center mb-8">
                        <img src="images/logo.png" alt="GNTOMA" class="h-16 w-auto mx-auto mb-4 drop-shadow-sm hover:scale-105 smooth-transition" onerror="this.style.display='none';">
                    </div>

                    <?php if ($error_msg): ?>
                        <div class="bg-red-50 text-red-600 text-sm p-4 rounded-2xl mb-6 font-semibold text-center animate__animated animate__headShake border border-red-100">
                            <?php echo $error_msg; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success_msg === 'otp_sent'): ?>
                        <div class="bg-blue-50 text-primary text-sm p-4 rounded-2xl mb-6 font-semibold text-center animate__animated animate__fadeIn border border-blue-100">
                            <?= htmlspecialchars(__('landing.otp_sent'), ENT_QUOTES, 'UTF-8') ?><?php echo $masked_email !== '' ? ' ' . htmlspecialchars(__('landing.otp_sent_to'), ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($masked_email, ENT_QUOTES, 'UTF-8') : ''; ?> <?= htmlspecialchars(__('landing.otp_sent_suffix'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php elseif ($success_msg === 'password_reset'): ?>
                        <div class="bg-green-50 text-green-600 text-sm p-4 rounded-2xl mb-6 font-semibold text-center animate__animated animate__fadeIn border border-green-100">
                            <?= htmlspecialchars(__('landing.password_reset_ok'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 'login'): ?>
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold text-dark tracking-tight"><?= htmlspecialchars(__('landing.login_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-gray-500 text-sm mt-1 font-medium"><?= htmlspecialchars(__('landing.login_sub'), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($login_redirect_next === 'journal_access' && $login_redirect_jid > 0): ?>
                            <p class="text-primary text-xs font-semibold mt-3 px-2"><?= htmlspecialchars(__('landing.login_after_journal_access'), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <form action="journal/auth_login_process_3.php" method="POST" class="space-y-5">
                            <?php if ($login_redirect_next === 'journal_access' && $login_redirect_jid > 0): ?>
                            <input type="hidden" name="next" value="journal_access">
                            <input type="hidden" name="jid" value="<?= (int) $login_redirect_jid ?>">
                            <?php endif; ?>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-focus-within:text-primary smooth-transition" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>
                                </div>
                                <input type="text" name="login" required placeholder="<?= htmlspecialchars(__('landing.placeholder_login'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm">
                            </div>

                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-focus-within:text-primary smooth-transition" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="password" name="password" required placeholder="<?= htmlspecialchars(__('landing.placeholder_password'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm">
                            </div>
                            
                            <div class="flex justify-end px-2">
                                <a href="?step=forgot" class="text-xs font-bold text-gray-400 hover:text-primary smooth-transition"><?= htmlspecialchars(__('landing.forgot_link'), ENT_QUOTES, 'UTF-8') ?></a>
                            </div>

                            <button type="submit" class="w-full gntoma-dark-button text-white font-bold py-4 rounded-2xl active:scale-95 smooth-transition mt-2 shadow-lg">
                                <?= htmlspecialchars(__('landing.submit_login'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </form>

                        <!-- Carnet de loyer & Pharmacie : dossiers et BDD séparés ; hors périmètre compte journaux GNTOMA -->
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-center text-xs font-bold text-gray-400 uppercase tracking-wider mb-2"><?= htmlspecialchars(__('landing.services_title'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-center text-[11px] text-gray-500 font-medium leading-snug mb-4 px-1"><?= htmlspecialchars(__('landing.services_note'), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="flex gap-3">
                                <a href="carnetdeloyer/index.php" rel="noopener noreferrer" title="<?= htmlspecialchars(__('landing.services_link_carnet_title'), ENT_QUOTES, 'UTF-8') ?>" class="flex-1 inline-flex items-center justify-center bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-2xl shadow-lg active:scale-95 smooth-transition space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" /></svg>
                                    <span class="text-sm"><?= htmlspecialchars(__('landing.carnet_loyer'), ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                                <a href="pharmacie/index.php" rel="noopener noreferrer" title="<?= htmlspecialchars(__('landing.services_link_pharmacie_title'), ENT_QUOTES, 'UTF-8') ?>" class="flex-1 inline-flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-2xl shadow-lg active:scale-95 smooth-transition space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    <span class="text-sm"><?= htmlspecialchars(__('landing.pharmacie'), ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                            </div>
                        </div>

                    <?php elseif ($step === 'forgot'): ?>
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold text-dark tracking-tight"><?= htmlspecialchars(__('landing.forgot_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-gray-500 text-sm mt-1 font-medium"><?= htmlspecialchars(__('landing.forgot_sub'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <form action="journal/auth_forgot_8.php" method="POST" class="space-y-5">
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-focus-within:text-primary smooth-transition" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-11a1 1 0 112 0v3a1 1 0 11-2 0V7zm1 8a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="text" name="code" required value="<?php echo htmlspecialchars($reset_code, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= htmlspecialchars(__('landing.placeholder_user_code'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm uppercase" inputmode="text" autocomplete="off" maxlength="12" pattern="[A-Za-z]\d+" hx-get="journal/auth_forgot_8.php" hx-include="this" hx-push-url="false" hx-trigger="keyup changed delay:400ms" hx-target="#forgot-code-result" hx-swap="innerHTML">
                            </div>

                            <div id="forgot-code-result" class="min-h-[48px]">
                                <?php if ($masked_email !== ''): ?>
                                    <div class="bg-white/70 border border-white rounded-2xl px-4 py-3 text-sm text-gray-600 font-semibold">
                                        <?= htmlspecialchars(__('landing.email_linked'), ENT_QUOTES, 'UTF-8') ?> <span class="text-dark font-black"><?php echo htmlspecialchars($masked_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="w-full gntoma-primary-button text-white font-bold py-4 rounded-2xl active:scale-95 smooth-transition shadow-lg shadow-blue-500/30">
                                <?= htmlspecialchars(__('landing.send_otp'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            
                            <div class="text-center pt-4">
                                <a href="?step=login" class="text-sm font-bold text-gray-400 hover:text-dark smooth-transition"><?= htmlspecialchars(__('landing.back_login'), ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        </form>

                    <?php elseif ($step === 'reset'): ?>
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold text-dark tracking-tight"><?= htmlspecialchars(__('landing.reset_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-gray-500 text-sm mt-1 font-medium"><?= htmlspecialchars(__('landing.reset_sub'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <form action="journal/auth_reset_9.php" method="POST" class="space-y-5">
                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($reset_code, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="masked_email" value="<?php echo htmlspecialchars($masked_email, ENT_QUOTES, 'UTF-8'); ?>">

                            <?php if ($masked_email !== ''): ?>
                                <div class="bg-white/70 border border-white rounded-2xl px-4 py-3 text-sm text-gray-600 font-semibold text-center">
                                    <?= htmlspecialchars(__('landing.code_sent_to'), ENT_QUOTES, 'UTF-8') ?> <span class="text-dark font-black"><?php echo htmlspecialchars($masked_email, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-focus-within:text-primary smooth-transition" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="text" name="otp" required maxlength="6" pattern="\d{6}" placeholder="<?= htmlspecialchars(__('landing.placeholder_otp'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-2xl py-4 pl-12 pr-4 font-black tracking-widest text-center text-primary text-lg">
                            </div>

                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 group-focus-within:text-primary smooth-transition" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="password" name="new_password" required minlength="6" placeholder="<?= htmlspecialchars(__('landing.placeholder_new_password'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm">
                            </div>

                            <button type="submit" class="w-full gntoma-dark-button text-white font-bold py-4 rounded-2xl active:scale-95 smooth-transition shadow-lg">
                                <?= htmlspecialchars(__('landing.confirm_reset'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

            <div class="order-2 lg:order-1 space-y-8 animate__animated animate__fadeInLeft">
                <div class="inline-flex items-center space-x-2 gntoma-badge px-4 py-2 rounded-full text-sm font-bold text-primary backdrop-blur-md">
                    <span><?= htmlspecialchars(__('landing.badge'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                
                <h1 class="text-4xl sm:text-5xl md:text-7xl font-black tracking-tight leading-[1.02] text-dark gntoma-title-glow">
                    <?= htmlspecialchars(__('landing.hero_line1'), ENT_QUOTES, 'UTF-8') ?> <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-500"><?= htmlspecialchars(__('landing.hero_gradient'), ENT_QUOTES, 'UTF-8') ?></span>
                </h1>
                
                <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-xl leading-relaxed font-medium">
                    <?= htmlspecialchars(__('landing.hero_sub'), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <div class="pt-2 flex flex-col sm:flex-row gap-3 sm:items-center">
                    <a href="journal/auth_register_1.php" class="inline-flex items-center justify-center gntoma-primary-button text-white font-bold px-8 py-4 rounded-2xl shadow-xl active:scale-95 smooth-transition space-x-2 group">
                        <span><?= htmlspecialchars(__('landing.cta_register'), ENT_QUOTES, 'UTF-8') ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 smooth-transition" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </a>
                    <a href="?step=login" class="inline-flex items-center justify-center gntoma-chip text-dark font-bold px-6 py-4 rounded-2xl smooth-transition">
                        <?= htmlspecialchars(__('landing.cta_login'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="w-full mt-20 animate__animated animate__fadeInUp">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 items-center">
                <div class="glass-panel gntoma-section-shell p-3 rounded-[2.5rem] border-white hover:-translate-y-2 smooth-transition">
                    <img src="images/showcase_1.jpg" alt="<?= htmlspecialchars(__('landing.alt_reading'), ENT_QUOTES, 'UTF-8') ?>" class="w-full h-72 object-cover rounded-[2rem]" onerror="this.src='https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80';">
                </div>
                <div class="glass-panel gntoma-section-shell p-3 rounded-[2.5rem] border-white hover:-translate-y-2 smooth-transition">
                    <img src="images/showcase_2.jpg" alt="<?= htmlspecialchars(__('landing.alt_dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="w-full h-72 object-cover rounded-[2rem]" onerror="this.src='https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80';">
                </div>
            </div>
        </div>

    </main>

    <footer class="p-8 text-center text-gray-400 text-sm font-bold bg-white/20 backdrop-blur-md space-y-3">
        <p><?= htmlspecialchars(__('landing.footer', ['year' => (string) date('Y')]), ENT_QUOTES, 'UTF-8') ?></p>
        <p>
            <a href="conditions_utilisation.php" class="text-primary hover:underline font-semibold"><?= htmlspecialchars(__('landing.terms_link'), ENT_QUOTES, 'UTF-8') ?></a>
        </p>
    </footer>

</div>

</body>
</html>