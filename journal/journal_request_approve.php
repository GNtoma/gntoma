<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_request_approve.php
 * DESCRIPTION : Approuver ou refuser une demande d'accès
 * L'auteur peut accepter ou refuser une demande avec un message optionnel
 */

session_start();
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$author_code = $_SESSION['user_id'];
$request_id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? ''; // approve, reject, reactivate ou view

if (!$request_id || !in_array($action, ['approve', 'reject', 'reactivate', 'view'], true)) {
    header("Location: journal_requests_list.php?error=invalid_params");
    exit;
}

$error = '';
$success = '';
$request = null;
$isViewMode = $action === 'view';

// Récupérer les détails de la demande
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $pdo->prepare("
            SELECT ar.*, j.title as journal_title, j.cover_image, j.price, j.price_currency,
                   u.first_name, u.last_name,
                   (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM access_requests ar
            JOIN journals j ON ar.journal_id = j.id
            JOIN users u ON ar.requester_user_code = u.user_code
            WHERE ar.id = ? AND ar.author_user_code = ?
            LIMIT 1
        ");
        $stmt->execute([$request_id, $author_code]);
        $request = $stmt->fetch();
        
        if (!$request) {
            header("Location: journal_requests_list.php?error=request_not_found");
            exit;
        }

        if (!$isViewMode && $request['status'] !== 'pending' && $action !== 'reactivate') {
            header("Location: journal_request_approve.php?id=" . $request_id . "&action=view");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération demande : " . $e->getMessage());
        header("Location: journal_requests_list.php?error=system_error");
        exit;
    }
} else {
    // Traitement du formulaire
    $response_message = trim((string)($_POST['response_message'] ?? ''));
    
    // Réactivation : remettre en attente
    if ($action === 'reactivate') {
        try {
            $pdo->beginTransaction();
            
            $request_stmt = $pdo->prepare("
                SELECT ar.*, j.title as journal_title,
                       (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
                FROM access_requests ar
                JOIN journals j ON ar.journal_id = j.id
                WHERE ar.id = ? AND ar.author_user_code = ?
                LIMIT 1
            ");
            $request_stmt->execute([$request_id, $author_code]);
            $request = $request_stmt->fetch();
            
            if (!$request) {
                throw new RuntimeException("Cette demande n'existe pas.");
            }
            
            if ($request['status'] === 'pending') {
                throw new RuntimeException("Cette demande est déjà en attente.");
            }
            
            $update_stmt = $pdo->prepare("
                UPDATE access_requests 
                SET status = 'pending', response_message = NULL, approved_at = NULL 
                WHERE id = ? AND author_user_code = ?
            ");
            $update_stmt->execute([$request_id, $author_code]);
            
            if ($update_stmt->rowCount() > 0) {
                // Notifier le demandeur que sa demande a été réactivée
                try {
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO message_notifications (user_code, message_id, type)
                        VALUES (?, ?, 'access_request')
                    ");
                    $notif_stmt->execute([(string) $request['requester_user_code'], $request_id]);
                } catch (Throwable $e) {
                    error_log("Erreur notification réactivation : " . $e->getMessage());
                }
                
                $pdo->commit();
                $success = "Demande réactivée avec succès ! Elle est maintenant en attente. Le demandeur a été notifié.";
            } else {
                throw new RuntimeException("Impossible de réactiver cette demande.");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur réactivation demande : " . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur lors de la réactivation de la demande.";
            
            $fallback_stmt = $pdo->prepare("
                SELECT ar.*, j.title as journal_title, j.cover_image, j.price, j.price_currency,
                       u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
                FROM access_requests ar
                JOIN journals j ON ar.journal_id = j.id
                JOIN users u ON ar.requester_user_code = u.user_code
                WHERE ar.id = ? AND ar.author_user_code = ?
                LIMIT 1
            ");
            $fallback_stmt->execute([$request_id, $author_code]);
            $request = $fallback_stmt->fetch() ?: null;
        }
    } else {
        // Approve/Reject normal
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        
        try {
            $pdo->beginTransaction();

            $request_stmt = $pdo->prepare("
                SELECT ar.*, j.title as journal_title,
                       (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
                FROM access_requests ar
                JOIN journals j ON ar.journal_id = j.id
                WHERE ar.id = ? AND ar.author_user_code = ? AND ar.status = 'pending'
                LIMIT 1
            ");
            $request_stmt->execute([$request_id, $author_code]);
            $request = $request_stmt->fetch();

            if (!$request) {
                throw new RuntimeException("Cette demande a déjà été traitée ou n'existe pas.");
            }

            $update_stmt = $pdo->prepare("
                UPDATE access_requests 
                SET status = ?, response_message = ?, approved_at = ? 
                WHERE id = ? AND author_user_code = ? AND status = 'pending'
            ");
            $approvedAt = $new_status === 'approved' ? date('Y-m-d H:i:s') : null;
            $update_stmt->execute([$new_status, $response_message, $approvedAt, $request_id, $author_code]);

            if ($update_stmt->rowCount() > 0) {
                if ($new_status === 'approved') {
                    $reader_stmt = $pdo->prepare("
                        INSERT INTO journal_readers (journal_id, user_code, access_count)
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE last_access_at = CURRENT_TIMESTAMP, access_count = access_count + 1
                    ");
                    $reader_stmt->execute([(int) $request['journal_id'], (string) $request['requester_user_code']]);

                    $count_stmt = $pdo->prepare("UPDATE journals SET reader_count = (SELECT COUNT(*) FROM journal_readers WHERE journal_id = ?) WHERE id = ?");
                    $count_stmt->execute([(int) $request['journal_id'], (int) $request['journal_id']]);
                }

                // Créer une notification pour le demandeur (l'informer du résultat)
                try {
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO message_notifications (user_code, message_id, type)
                        VALUES (?, ?, 'access_request')
                    ");
                    $notif_stmt->execute([(string) $request['requester_user_code'], $request_id]);
                } catch (Throwable $e) {
                    // Ne pas bloquer en cas d'erreur de notification
                    error_log("Erreur notification demandeur : " . $e->getMessage());
                }

                $pdo->commit();
                $success = $action === 'approve'
                    ? "Demande approuvée avec succès ! Le demandeur a été notifié et dispose maintenant d'un accès permanent à votre journal."
                    : "Demande refusée. Le demandeur a été notifié.";
            } else {
                throw new RuntimeException("Cette demande a déjà été traitée ou n'existe pas.");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur mise à jour demande : " . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur lors du traitement de la demande.";

            $fallback_stmt = $pdo->prepare("
                SELECT ar.*, j.title as journal_title, j.cover_image, j.price, j.price_currency,
                       u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
                FROM access_requests ar
                JOIN journals j ON ar.journal_id = j.id
                JOIN users u ON ar.requester_user_code = u.user_code
                WHERE ar.id = ? AND ar.author_user_code = ?
                LIMIT 1
            ");
            $fallback_stmt->execute([$request_id, $author_code]);
            $request = $fallback_stmt->fetch() ?: null;
        }
    }
}

if ($request) {
    $journal_code = $author_code . 'J' . $request['journal_num'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNTOMA - <?= $isViewMode ? 'Voir' : ($action === 'approve' ? 'Approuver' : 'Refuser') ?> la Demande</title>
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
        
        /* Mobile-first improvements */
        @media (max-width: 640px) {
            .glass-panel { border-radius: 1.5rem; padding: 1.25rem; }
            .text-2xl { font-size: 1.25rem; }
            .p-8 { padding: 1.25rem; }
            .py-8 { padding-top: 1rem; padding-bottom: 1rem; }
            .py-4 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4">

    <div class="max-w-lg mx-auto">
        <div class="glass-panel rounded-[2.5rem] p-8">
            
            <div class="text-center mb-8">
                <div class="w-16 h-16 <?= $isViewMode ? 'bg-blue-100' : ($action === 'approve' ? 'bg-green-100' : 'bg-red-100') ?> rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 <?= $isViewMode ? 'text-blue-600' : ($action === 'approve' ? 'text-green-600' : 'text-red-600') ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <?php if ($isViewMode): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <?php elseif ($action === 'approve'): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        <?php else: ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        <?php endif; ?>
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-dark mb-2">
                    <?= $isViewMode ? 'Détail de la Demande' : ($action === 'approve' ? 'Approuver la Demande' : 'Refuser la Demande') ?>
                </h1>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-center text-sm font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 text-green-700 p-6 rounded-2xl mb-6 text-center">
                    <div class="text-4xl mb-2"><?= $action === 'approve' ? '✓' : '✗' ?></div>
                    <p class="font-bold"><?= $success ?></p>
                </div>
                <div class="text-center">
                    <a href="journal_requests_list.php" class="inline-block bg-dark text-white font-bold py-3 px-8 rounded-2xl hover:bg-black transition-all">
                        Voir toutes les demandes
                    </a>
                </div>
            <?php elseif ($request): ?>

                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-12 h-12 bg-primary rounded-xl flex items-center justify-center text-white">
                            <span class="text-lg font-black"><?= preg_replace('/[^0-9]/', '', $request['request_number']) ?></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Demande de</p>
                            <p class="font-bold text-dark"><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></p>
                            <p class="text-sm text-gray-600">pour <span class="font-medium"><?= htmlspecialchars($request['journal_title']) ?></span></p>
                            <p class="text-xs text-primary font-bold"><?= $journal_code ?></p>
                            <p class="mt-2 text-xs font-bold uppercase tracking-wider <?= ($request['status'] ?? '') === 'approved' ? 'text-green-600' : (($request['status'] ?? '') === 'rejected' ? 'text-red-600' : 'text-orange-600') ?>">
                                Statut : <?= htmlspecialchars((string) $request['status']) ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($request['message'])): ?>
                        <div class="mt-3 p-3 bg-white rounded-xl border border-gray-200">
                            <p class="text-xs text-gray-500 uppercase font-bold mb-1">Message du demandeur</p>
                            <p class="text-sm italic">"<?= htmlspecialchars($request['message']) ?>"</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($request['response_message'])): ?>
                        <div class="mt-3 p-3 bg-blue-50 rounded-xl border border-blue-200">
                            <p class="text-xs text-blue-500 uppercase font-bold mb-1">Votre réponse</p>
                            <p class="text-sm italic">"<?= htmlspecialchars($request['response_message']) ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bouton Discuter - toujours visible pour contacter le demandeur -->
                <a href="message_send.php?to=<?= urlencode($request['requester_user_code']) ?>&context=access_request&request_id=<?= (int)$request['id'] ?>&journal_id=<?= (int)$request['journal_id'] ?>"
                   class="w-full bg-blue-100 text-blue-700 font-bold py-3 rounded-2xl hover:bg-blue-200 transition-all text-center flex items-center justify-center gap-2 mb-5">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Discuter avec <?= htmlspecialchars($request['first_name']) ?>
                </a>

                <?php if (!$isViewMode): ?>
                <?php if ($action === 'approve'): ?>
                <!-- Rappel important pour l'auteur avant approbation -->
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-5">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-amber-900 mb-1">Vérifiez avant d'approuver</p>
                            <p class="text-xs text-amber-800">Sur GNTOMA, l'abonnement et les crédits messages se paient déjà en ligne (FlexPay). Pour l'accès à ce journal payant, le montant affiché en USD se règle en général entre vous et le lecteur (messagerie, Mobile Money, etc.) : vérifiez que vous êtes d'accord avant d'approuver. Une fois approuvée, le demandeur aura un accès permanent au contenu.</p>
                        </div>
                    </div>
                </div>

                <!-- Bouton pour discuter avec le demandeur -->
                <a href="message_send.php?to=<?= urlencode($request['requester_user_code']) ?>&context=access_request&request_id=<?= (int)$request['id'] ?>&journal_id=<?= (int)$request['journal_id'] ?>" 
                   class="w-full bg-blue-100 text-blue-700 font-bold py-3 rounded-2xl hover:bg-blue-200 transition-all text-center text-sm flex items-center justify-center space-x-2 mb-5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span>Discuter avec le demandeur d'abord</span>
                </a>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2">
                            Message de réponse (optionnel)
                        </label>
                        <textarea name="response_message" rows="3" 
                                  placeholder="<?= $action === 'approve' ? 'Merci pour votre paiement, profitez bien du journal !' : 'Désolé, je n\'ai pas reçu le paiement attendu...' ?>" 
                                  class="w-full input-lucide rounded-2xl py-4 px-6 font-medium text-dark placeholder-gray-300 resize-none"></textarea>
                    </div>

                    <div class="flex space-x-3">
                        <a href="journal_requests_list.php" class="flex-1 bg-gray-200 text-dark font-bold py-4 rounded-2xl hover:bg-gray-300 transition-all text-center">
                            Annuler
                        </a>
                        <button type="submit" 
                                class="flex-1 <?= $action === 'approve' ? 'bg-green-500 hover:bg-green-600 shadow-lg shadow-green-500/30' : 'bg-red-500 hover:bg-red-600' ?> text-white font-bold py-4 rounded-2xl transition-all">
                            <?= $action === 'approve' ? '✓ Confirmer l\'accès' : '✗ Refuser' ?>
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <!-- Mode Vue : options selon statut -->
                <?php if ($request['status'] === 'rejected'): ?>
                    <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-5">
                        <p class="text-sm font-bold text-orange-900 mb-2">Cette demande a été refusée</p>
                        <p class="text-xs text-orange-800">Vous pouvez la réactiver pour la remettre en attente et permettre au demandeur de faire une nouvelle tentative.</p>
                    </div>
                    <div class="flex space-x-3 mb-5">
                        <a href="journal_request_approve.php?id=<?= $request['id'] ?>&action=reactivate" 
                           class="flex-1 bg-orange-500 text-white font-bold py-4 rounded-2xl hover:bg-orange-600 transition-all text-center shadow-md shadow-orange-500/30">
                            ↻ Réactiver la demande
                        </a>
                    </div>
                <?php endif; ?>
                <div class="flex space-x-3">
                    <a href="journal_requests_list.php" class="flex-1 bg-dark text-white font-bold py-4 rounded-2xl transition-all text-center">
                        Retour aux demandes
                    </a>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>
