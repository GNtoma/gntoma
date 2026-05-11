<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Si déjà connecté au journal, rediriger vers l'index pharmacie
if (isset($_SESSION['user_id'])) {
    ph_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        ph_set_flash('error', 'Tous les champs sont requis.');
        ph_redirect('auth_register.php');
    }

    if ($password !== $passwordConfirm) {
        ph_set_flash('error', 'Les mots de passe ne correspondent pas.');
        ph_redirect('auth_register.php');
    }

    try {
        if (ph_find_user_by_email($pdo, $email)) {
            ph_set_flash('error', 'Cet email est déjà utilisé.');
            ph_redirect('auth_register.php');
        }

        $userCode = ph_generate_user_code($pdo);
        $stmt = $pdo->prepare('INSERT INTO users (user_code, full_name, email, password_hash) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userCode, $fullName, $email, ph_hash_password($password)]);
        $user = ph_find_user_by_id($pdo, (int) $pdo->lastInsertId());
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable après création.');
        }
        ph_login_user($user);
        ph_set_flash('success', 'Bienvenue dans votre espace Pharmacie.');
        ph_redirect('index.php');
    } catch (Throwable $e) {
        ph_set_flash('error', 'La création du compte a échoué.');
        ph_redirect('auth_register.php');
    }
}

ph_render_shell_start('Créer un compte');
?>
<div class="mx-auto max-w-xl animate__animated animate__fadeInUp rounded-[2rem] bg-white p-8 shadow-soft">
    <form method="post" class="space-y-5">
        <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Nom complet</label>
            <input type="text" name="full_name" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Email</label>
            <input type="email" name="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Mot de passe</label>
            <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
        </div>
        <div>
            <label class="mb-2 block text-sm font-bold text-slate-700">Confirmer le mot de passe</label>
            <input type="password" name="password_confirm" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
        </div>
        <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600">Créer un compte</button>
    </form>
    <p class="mt-6 text-center text-sm font-medium text-slate-500">Déjà un compte ? <a class="font-black text-primary" href="auth_login.php">Se connecter</a></p>
</div>
<?php ph_render_shell_end();
