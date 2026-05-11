<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Si déjà connecté au journal, rediriger vers l'index pharmacie
if (isset($_SESSION['user_id'])) {
    ph_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        ph_set_flash('error', 'Email et mot de passe sont requis.');
        ph_redirect('auth_login.php');
    }

    $user = ph_find_user_by_email($pdo, $email);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        ph_set_flash('error', 'Identifiants invalides.');
        ph_redirect('auth_login.php');
    }

    ph_login_user($user);
    ph_set_flash('success', 'Connexion réussie.');
    ph_redirect('index.php');
}

ph_render_shell_start('Connexion');
?>
<div class="mx-auto max-w-xl animate__animated animate__fadeInUp rounded-[2rem] bg-white p-8 shadow-soft">
    <form method="post" class="space-y-5">
        <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Email</label>
            <input type="email" name="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Mot de passe</label>
            <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
        </div>
        <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600">Se connecter</button>
    </form>
    <p class="mt-6 text-center text-sm font-medium text-slate-500">Pas encore de compte ? <a class="font-black text-primary" href="auth_register.php">Créer un compte</a></p>
</div>
<?php ph_render_shell_end();
