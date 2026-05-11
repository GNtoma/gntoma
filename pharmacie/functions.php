<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

session_start();

function ph_current_user(): ?array
{
    // Utilise la session du journal
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_code = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('Erreur ph_current_user : ' . $e->getMessage());
        return null;
    }
}

function ph_set_flash(string $type, string $message): void
{
    $_SESSION['ph_flash'] = ['type' => $type, 'message' => $message];
}

function ph_get_flash(): ?array
{
    $flash = $_SESSION['ph_flash'] ?? null;
    unset($_SESSION['ph_flash']);
    return $flash;
}

function ph_redirect(string $path): void
{
    header("Location: $path");
    exit;
}

function ph_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function ph_find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function ph_find_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function ph_generate_user_code(PDO $pdo): string
{
    $prefix = 'A';
    $stmt = $pdo->prepare('SELECT MAX(user_code) as max_code FROM users WHERE user_code LIKE ?');
    $stmt->execute(["{$prefix}%"]);
    $result = $stmt->fetch();
    
    if ($result && $result['max_code']) {
        $num = (int) substr($result['max_code'], 1) + 1;
    } else {
        $num = 1;
    }
    
    return $prefix . $num;
}

function ph_login_user(array $user): void
{
    // Utilise la session du journal
    $_SESSION['user_id'] = $user['user_code'];
}

function ph_logout_user(): void
{
    unset($_SESSION['user_id']);
}

function ph_render_shell_start(string $title, ?array $user = null): void
{
    $flash = ph_get_flash();
    $currentUser = $user ?? ph_current_user();
    
    echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - GNTOMA Pharmacie</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ["Outfit", "sans-serif"] },
                    colors: { primary: "#10B981", dark: "#1D1D1F", surface: "#F5F5F7" }
                }
            }
        }
    </script>
    <style>
        body { font-family: "Outfit", sans-serif; background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 50%, #ffffff 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.05); }
        .input-lucide { background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        .input-lucide:focus { background: #fff; border-color: #10B981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); outline: none; }
    </style>
</head>
<body class="min-h-screen">
    <header class="bg-white/80 backdrop-blur-md border-b border-white sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <span class="text-lg font-black text-dark">Pharmacie</span>
                </div>';
    
    if ($currentUser) {
        echo '<nav class="flex items-center gap-3">
            <a href="index.php" class="text-sm font-bold text-gray-600 hover:text-primary transition-colors">Tableau de bord</a>
            <a href="../journal/dashboard_6.php" class="text-sm font-bold text-gray-600 hover:text-primary transition-colors">Journal</a>
            <a href="auth_logout.php" class="text-sm font-bold text-red-500 hover:text-red-600 transition-colors">Déconnexion</a>
        </nav>';
    }
    
    echo '</div></div></header>';
    
    if ($flash) {
        $bgClass = $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-600 border-red-200';
        echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="' . $bgClass . ' border p-4 rounded-2xl text-center font-bold animate__animated animate__fadeInDown">
                ' . htmlspecialchars($flash['message']) . '
            </div>
        </div>';
    }
    
    echo '<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">';
}

function ph_render_shell_end(): void
{
    echo '</main>
    <footer class="bg-white/50 backdrop-blur-md border-t border-white mt-12 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 text-sm font-bold">
            GNTOMA Pharmacie © 2026
        </div>
    </footer>
</body>
</html>';
}
