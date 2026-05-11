<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Si déjà connecté au journal, rediriger vers l'index du carnet de loyer
if (isset($_SESSION['user_id'])) {
    cl_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cl_post_string('full_name');
    $email = strtolower(cl_post_string('email'));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($fullName === '' || $email === '' || $password === '' || $passwordConfirm === '') {
        cl_set_flash('error', 'Tous les champs sont obligatoires.');
        cl_redirect('auth_register.php');
    }

    if (!cl_validate_email($email)) {
        cl_set_flash('error', 'Adresse email invalide.');
        cl_redirect('auth_register.php');
    }

    if (mb_strlen($password) < 6) {
        cl_set_flash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
        cl_redirect('auth_register.php');
    }

    if ($password !== $passwordConfirm) {
        cl_set_flash('error', 'Les mots de passe ne correspondent pas.');
        cl_redirect('auth_register.php');
    }

    try {
        if (cl_find_user_by_email($pdo, $email)) {
            cl_set_flash('error', 'Cet email est déjà utilisé.');
            cl_redirect('auth_register.php');
        }

        $userCode = cl_generate_user_code($pdo);
        $stmt = $pdo->prepare('INSERT INTO users (user_code, full_name, email, password_hash) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userCode, $fullName, $email, cl_hash_password($password)]);
        $user = cl_find_user_by_id($pdo, (int) $pdo->lastInsertId());
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable après création.');
        }
        cl_login_user($user);
        cl_set_flash('success', 'Bienvenue dans votre espace Carnet de loyer.');
        cl_redirect('index.php');
    } catch (Throwable $e) {
        cl_set_flash('error', 'La création du compte a échoué.');
        cl_redirect('auth_register.php');
    }
}

cl_render_shell_start('Créer un compte');
?>
<div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-2">
    <section class="glass animate__animated animate__fadeInLeft rounded-[2rem] border border-white/70 p-8 shadow-soft">
        <span class="inline-flex rounded-full bg-primary/10 px-4 py-2 text-xs font-black uppercase tracking-[0.3em] text-primary">Inscription</span>
        <h2 class="mt-4 text-3xl font-black">Gérez vos loyers en mode bailleur et locataire</h2>
        <p class="mt-4 text-sm leading-7 text-slate-600">Créez vos maisons, louez celles des autres utilisateurs, validez les contrats et suivez les paiements mensuels depuis un seul module responsive.</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl bg-white/80 p-5">
                <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Bailleur</p>
                <p class="mt-2 text-sm font-medium text-slate-600">Ajout de maisons, statut d’occupation, suivi locatif et reçus.</p>
            </div>
            <div class="rounded-3xl bg-white/80 p-5">
                <p class="text-xs font-black uppercase tracking-[0.28em] text-accent">Locataire</p>
                <p class="mt-2 text-sm font-medium text-slate-600">Sélection de maisons libres, validation du contrat et tableau des mensualités.</p>
            </div>
        </div>
    </section>
    <section class="animate__animated animate__fadeInRight rounded-[2rem] bg-white p-8 shadow-soft">
        <form method="post" class="space-y-5">
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Nom complet</label>
                <input type="text" name="full_name" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Email</label>
                <input type="email" name="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Mot de passe</label>
                    <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Confirmation</label>
                    <input type="password" name="password_confirm" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
            </div>
            <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Créer mon compte</button>
        </form>
        <p class="mt-6 text-center text-sm font-medium text-slate-500">Déjà inscrit ? <a class="font-black text-primary" href="auth_login.php">Connectez-vous</a></p>
    </section>
</div>
<?php cl_render_shell_end();
