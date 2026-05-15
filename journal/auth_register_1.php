<?php
/**
 * PROJET : GNTOMA
 * FICHIER : journal/auth_register_1.php
 * VERSION : 1
 * DESCRIPTION : Page d'inscription (Design Lucide, Validation Vue.js, 6 caractÃ¨res minimum).
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty_fields': $error_message = __('auth_register.err_empty_fields'); break;
        case 'password_mismatch': $error_message = __('auth_register.err_password_mismatch'); break;
        case 'invalid_email': $error_message = __('auth_register.err_invalid_email'); break;
        case 'password_too_short': $error_message = __('auth_register.err_password_too_short'); break;
        case 'email_exists': $error_message = __('auth_register.err_email_exists'); break;
        case 'system_error': $error_message = __('auth_register.err_system'); break;
        default: $error_message = __('auth_register.err_unknown'); break;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(__('auth_register.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <?php require_once dirname(__DIR__) . '/ui_head.php'; ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: { 
                    fontFamily: { sans: ['Outfit', 'sans-serif'] }, 
                    colors: { 
                        primary: '#007AFF', 
                        dark: '#1D1D1F',
                        surface: '#F5F5F7'
                    } 
                }
            }
        }
    </script>
    <style>
        body {
            color: #1D1D1F;
            -webkit-font-smoothing: antialiased;
            margin: 0;
            overflow-x: hidden;
        }

        /* --- GLASSMORPHISM --- */
        .glass-panel-light {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 1);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
        }

        .glass-input-light {
            background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); color: #1D1D1F; transition: all 0.3s ease;
        }
        .glass-input-light:focus {
            background: #ffffff; border-color: #007AFF; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1); outline: none;
        }
        .input-group:focus-within svg.icon-input { color: #007AFF; }
        .smooth-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <?php require_once dirname(__DIR__) . '/ui_background.php'; ?>\r\n        <div class="snow-layer"></div>
        <div class="snow-layer"></div>
    </div>

    <div id="register-app" class="w-full max-w-md animate__animated animate__zoomIn animate__faster z-10 mt-12 md:mt-0 gntoma-page-enter">
        <div class="glass-panel-light gntoma-section-shell rounded-[2.5rem] p-8 md:p-10 relative overflow-hidden">
            <div class="flex justify-end mb-2"><?= gntoma_lang_switch_markup() ?></div>
            <div class="text-center mb-8">
                <img src="../images/logo.png" alt="GNTOMA Logo" class="h-16 w-auto mx-auto mb-4 drop-shadow-sm hover:scale-105 smooth-transition" onerror="this.style.display='none';">
                <h2 class="text-2xl font-bold text-dark tracking-tight"><?= htmlspecialchars(__('auth_register.heading'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?= htmlspecialchars(__('auth_register.sub'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-100 text-red-600 text-sm p-4 rounded-2xl mb-6 font-medium flex items-center animate__animated animate__shakeX">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="auth_register_traitement_2.php" method="POST" class="space-y-5" @submit="validateForm">
                
                <div class="relative input-group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 icon-input transition-colors duration-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="text" name="name" v-model="form.name" required placeholder="<?= htmlspecialchars(__('auth_register.placeholder_name'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input-light rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm placeholder-gray-400">
                </div>

                <div class="relative input-group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 icon-input transition-colors duration-300" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                        </svg>
                    </div>
                    <input type="email" name="email" v-model="form.email" required placeholder="<?= htmlspecialchars(__('auth_register.placeholder_email'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input-light rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm placeholder-gray-400">
                </div>
                
                <div class="relative input-group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 icon-input transition-colors duration-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="password" name="password" v-model="form.password" required placeholder="<?= htmlspecialchars(__('auth_register.placeholder_password'), ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input-light rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm placeholder-gray-400">
                </div>

                <div class="relative input-group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg v-if="passwordMatch && form.passwordConfirm.length > 0" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 animate__animated animate__bounceIn" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 icon-input transition-colors duration-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="password" name="password_confirm" v-model="form.passwordConfirm" required placeholder="<?= htmlspecialchars(__('auth_register.placeholder_confirm'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full glass-input-light rounded-2xl py-4 pl-12 pr-4 font-semibold text-sm placeholder-gray-400 smooth-transition"
                           :class="{'border-red-400 bg-red-50': passwordError, 'border-green-400 bg-green-50': passwordMatch && form.passwordConfirm.length > 0}">
                </div>

                <div class="pt-4">
                    <button type="submit" :disabled="!isFormValid"
                            class="w-full gntoma-dark-button text-white font-bold py-4 rounded-2xl shadow-xl active:scale-95 smooth-transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2 group">
                        <span><?= htmlspecialchars(__('auth_register.submit'), ENT_QUOTES, 'UTF-8') ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 smooth-transition" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center mt-6">
            <p class="text-dark text-sm font-medium">
                <?= htmlspecialchars(__('auth_register.already'), ENT_QUOTES, 'UTF-8') ?> 
                <a href="../index.php" class="text-primary font-bold hover:underline smooth-transition gntoma-chip px-4 py-2 rounded-full ml-1"><?= htmlspecialchars(__('auth_register.login_link'), ENT_QUOTES, 'UTF-8') ?></a>
            </p>
        </div>
    </div>

    <script>
        const { createApp, ref, computed } = Vue;
        createApp({
            setup() {
                const form = ref({ name: '', email: '', password: '', passwordConfirm: '' });
                const passwordMatch = computed(() => form.value.password === form.value.passwordConfirm && form.value.password.length >= 6);
                const passwordError = computed(() => form.value.passwordConfirm.length > 0 && form.value.password !== form.value.passwordConfirm);
                const isFormValid = computed(() => form.value.name.length > 0 && form.value.email.length > 0 && form.value.password.length >= 6 && passwordMatch.value);
                const validateForm = (e) => { if (!isFormValid.value) e.preventDefault(); };
                return { form, passwordMatch, passwordError, isFormValid, validateForm }
            }
        }).mount('#register-app');
    </script>
</body>
</html>
