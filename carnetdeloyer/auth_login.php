<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Si déjà connecté au journal, rediriger vers l'index du carnet de loyer
if (isset($_SESSION['user_id'])) {
    cl_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(cl_post_string('email'));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        cl_set_flash('error', 'Email et mot de passe sont requis.');
        cl_redirect('auth_login.php');
    }

    $user = cl_find_user_by_email($pdo, $email);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        cl_set_flash('error', 'Identifiants invalides.');
        cl_redirect('auth_login.php');
    }

    cl_login_user($user);
    cl_set_flash('success', 'Connexion réussie.');
    cl_redirect('index.php');
}

cl_render_shell_start('Connexion');
?>
<div class="mx-auto max-w-lg animate__animated animate__fadeInUp">
    <div class="glass-panel rounded-[2.5rem] p-8 shadow-2xl">
        <!-- Logo et titre -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-emerald-600 rounded-[1.5rem] flex items-center justify-center mx-auto mb-4 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3" />
                </svg>
            </div>
            <h1 class="text-2xl font-black text-slate-900 mb-2">Carnet de Loyer</h1>
            <p class="text-slate-500 text-sm">Connectez-vous pour gérer vos locations</p>
        </div>

        <?php if (cl_get_flash('error')): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-6">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(cl_get_flash('error')) ?></p>
        </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <div>
                <label class="mb-2 block text-xs font-black uppercase tracking-widest text-slate-500">Email</label>
                <input type="email" name="email" placeholder="votre@email.com" class="w-full bg-white/80 border border-slate-200 rounded-2xl px-4 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" required>
            </div>
            <div>
                <label class="mb-2 block text-xs font-black uppercase tracking-widest text-slate-500">Mot de passe</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full bg-white/80 border border-slate-200 rounded-2xl px-4 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" required>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-green-500/20 active:scale-95 transition-all text-sm flex items-center justify-center space-x-2">
                <span>Se connecter</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </form>
        
        <div class="mt-6 pt-6 border-t border-slate-100">
            <p class="text-center text-sm font-medium text-slate-500">
                Pas encore de compte ? 
                <a class="font-black text-emerald-600 hover:text-emerald-700 transition-colors" href="auth_register.php">Créer un compte</a>
            </p>
        </div>
    </div>
</div>
<?php cl_render_shell_end();
