<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_go_to.php
 * DESCRIPTION : Accès direct à un journal par son numéro (A1J10, A3J25, etc.)
 * L'auteur peut saisir son numéro de journal (10, 20, etc.) pour y accéder directement
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $journal_num = (int)($_POST['journal_number'] ?? 0);
    
    if ($journal_num <= 0) {
        $error = __('journal_go.err_invalid');
    } else {
        // Construire le code journal
        $target_journal_code = $user_code . 'J' . $journal_num;
        
        try {
            // Chercher le journal avec ce numéro
            $stmt = $pdo->prepare("
                SELECT j.id, 
                       (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
                FROM journals j 
                WHERE j.user_code = ? 
                HAVING journal_num = ?
                LIMIT 1
            ");
            $stmt->execute([$user_code, $journal_num]);
            $journal = $stmt->fetch();
            
            if ($journal) {
                // Redirection vers la page d'édition du journal
                header("Location: journal_edit_13.php?id=" . $journal['id']);
                exit;
            } else {
                $error = __('journal_go.err_not_found', [
                    'num' => (string) $journal_num,
                    'total' => (string) getJournalCount($pdo, $user_code),
                ]);
            }
        } catch (PDOException $e) {
            error_log("Erreur accès direct journal : " . $e->getMessage());
            $error = __('journal_go.err_search');
        }
    }
}

function getJournalCount($pdo, $user_code): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM journals WHERE user_code = ?");
        $stmt->execute([$user_code]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

$total_journals = getJournalCount($pdo, $user_code);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('journal_go.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
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
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #e6eff9 0%, #f4f7fb 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.05); }
        .input-lucide { background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        .input-lucide:focus { background: #fff; border-color: #007AFF; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1); outline: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <div class="glass-panel rounded-[2.5rem] p-8">
            <div class="flex justify-end mb-2"><?= gntoma_lang_switch_markup() ?></div>
            <div class="text-center mb-8">
                <h1 class="text-2xl font-black text-dark mb-2"><?= htmlspecialchars(__('journal_go.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-gray-500 text-sm"><?= htmlspecialchars(__('journal_go.sub'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars(__('journal_go.you_have', ['count' => (string) $total_journals]), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-center text-sm font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('journal_go.label_number'), ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="flex items-center space-x-3">
                        <span class="text-lg font-bold text-gray-500"><?= $user_code ?>J</span>
                        <input type="number" name="journal_number" min="1" required 
                               placeholder="<?= htmlspecialchars(__('journal_go.placeholder_num'), ENT_QUOTES, 'UTF-8') ?>" 
                               class="flex-1 input-lucide rounded-2xl py-4 px-6 font-bold text-lg text-dark placeholder-gray-300">
                    </div>
                    <p class="text-xs text-gray-400 ml-2"><?= htmlspecialchars(__('journal_go.hint_example', ['code' => (string) $user_code]), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <button type="submit" class="w-full bg-dark text-white font-bold py-4 rounded-2xl shadow-xl hover:bg-black transition-all">
                    <?= htmlspecialchars(__('journal_go.submit'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="dashboard_6.php" class="text-sm font-bold text-primary hover:underline">
                    <?= htmlspecialchars(__('journal_go.back_dashboard'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
    </div>

</body>
</html>
