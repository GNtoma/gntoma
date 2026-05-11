<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_request_go.php
 * DESCRIPTION : Accès direct à une demande par son numéro D1, D2, D253, etc.
 * L'auteur peut saisir le numéro de demande pour y accéder directement
 */

session_start();
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$author_code = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_number_input = trim((string)($_POST['request_number'] ?? ''));
    
    // Formater le numéro (ajouter le D si manquant)
    $request_number = strtoupper($request_number_input);
    if (!str_starts_with($request_number, 'D')) {
        $request_number = 'D' . $request_number;
    }
    
    if (empty($request_number) || strlen($request_number) < 2) {
        $error = "Veuillez entrer un numéro de demande valide (ex: D1, D15, D253).";
    } else {
        try {
            // Chercher la demande avec ce numéro
            $stmt = $pdo->prepare("
                SELECT ar.id, ar.status, ar.journal_id,
                       j.title as journal_title,
                       u.first_name, u.last_name
                FROM access_requests ar
                JOIN journals j ON ar.journal_id = j.id
                JOIN users u ON ar.requester_user_code = u.user_code
                WHERE ar.request_number = ? AND ar.author_user_code = ?
                LIMIT 1
            ");
            $stmt->execute([$request_number, $author_code]);
            $request = $stmt->fetch();
            
            if ($request) {
                // Redirection vers la page d'approbation
                $action = $request['status'] === 'pending' ? 'approve' : 'view';
                header("Location: journal_request_approve.php?id=" . $request['id'] . "&action=" . $action);
                exit;
            } else {
                $error = "La demande {$request_number} n'existe pas ou ne vous appartient pas.";
            }
        } catch (PDOException $e) {
            error_log("Erreur accès direct demande : " . $e->getMessage());
            $error = "Erreur lors de la recherche de la demande.";
        }
    }
}

// Obtenir les stats pour info
$total_requests = 0;
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM access_requests WHERE author_user_code = ?");
    $count_stmt->execute([$author_code]);
    $total_requests = (int)$count_stmt->fetchColumn();
} catch (PDOException $e) {
    // Ignorer l'erreur
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNTOMA - Accès Direct à une Demande</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.05); }
        .input-lucide { background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        .input-lucide:focus { background: #fff; border-color: #007AFF; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1); outline: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <div class="glass-panel rounded-[2.5rem] p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-2xl font-black text-white">D</span>
                </div>
                <h1 class="text-2xl font-black text-dark mb-2">Accès Direct</h1>
                <p class="text-gray-500 text-sm">Allez directement à la demande D...</p>
                <p class="text-xs text-gray-400 mt-1">Vous avez <?= $total_requests ?> demande(s) au total</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-center text-sm font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2">Numéro de la demande</label>
                    <div class="flex items-center space-x-3">
                        <span class="text-2xl font-bold text-primary">D</span>
                        <input type="text" name="request_number" required 
                               placeholder="1, 15, 253..." 
                               class="flex-1 input-lucide rounded-2xl py-4 px-6 font-bold text-lg text-dark placeholder-gray-300">
                    </div>
                    <p class="text-xs text-gray-400 ml-2">Ex: Tapez "15" pour aller à la demande D15</p>
                </div>

                <button type="submit" class="w-full bg-dark text-white font-bold py-4 rounded-2xl shadow-xl hover:bg-black transition-all">
                    Aller à la demande
                </button>
            </form>

            <div class="mt-6 space-y-2">
                <a href="journal_requests_list.php" class="block text-center text-sm font-bold text-primary hover:underline">
                    📋 Voir toutes les demandes
                </a>
                <a href="dashboard_6.php" class="block text-center text-sm font-bold text-gray-500 hover:text-primary transition-colors">
                    ← Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

</body>
</html>
