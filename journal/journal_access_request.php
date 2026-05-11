<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_access_request.php
 * DESCRIPTION : Soumettre une demande d'accès à un journal payant (système type "Facebook friend request")
 * Chaque demande reçoit un numéro unique D1, D2, D253, etc.
 */

session_start();
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$requester_code = $_SESSION['user_id'];
$journal_id = (int)($_GET['journal_id'] ?? $_GET['id'] ?? $_GET['journal'] ?? 0);
$error = '';
$success = '';
$follow_success = $_GET['follow_success'] ?? null;
$journal = null;

// Récupérer les infos du journal et les crédits du demandeur
$user_credits = 100;
$is_own_journal = false;
$is_paid_journal = false;
if ($journal_id > 0) {
    try {
        $authorProfilePicSelect = gntoma_users_profile_pic_expr($pdo, 'u', 'author_profile_pic');
        // Récupérer le journal sans filtrer par statut ou propriétaire
        // pour permettre d'écrire à l'auteur dans tous les cas
        $stmt = $pdo->prepare("
            SELECT j.*, u.name, u.first_name, u.last_name, {$authorProfilePicSelect},
                   (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM journals j
            JOIN users u ON j.user_code = u.user_code
            WHERE j.id = ?
            LIMIT 1
        ");
        $stmt->execute([$journal_id]);
        $journal = $stmt->fetch();
        
        if (!$journal) {
            header("Location: dashboard_6.php?error=journal_not_found");
            exit;
        }
        
        $is_own_journal = ($journal['user_code'] === $requester_code);
        $is_paid_journal = ($journal['status'] === 'paid');
        $journal_code = $journal['user_code'] . 'J' . $journal['journal_num'];
        
        // Récupérer les crédits du DEMANDEUR (pas de l'auteur du journal)
        $cred_stmt = $pdo->prepare("SELECT access_request_credits FROM users WHERE user_code = ? LIMIT 1");
        $cred_stmt->execute([$requester_code]);
        $user_credits = (int) ($cred_stmt->fetchColumn() ?? 100);
        
        // Si c'est son propre journal, rediriger vers la vue du journal
        if ($is_own_journal) {
            header("Location: journal_view.php?id=$journal_id");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération journal : " . $e->getMessage());
        $error = "Erreur lors de la récupération des informations du journal.";
    }
}

// Vérifier si l'utilisateur a déjà une demande d'accès pour ce journal
$existing_access_request = null;
if ($journal && $journal_id > 0) {
    try {
        $access_stmt = $pdo->prepare("
            SELECT request_number, status, created_at, response_message, approved_at
            FROM access_requests 
            WHERE journal_id = ? AND requester_user_code = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $access_stmt->execute([$journal_id, $requester_code]);
        $existing_access_request = $access_stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Erreur vérification demande existante : " . $e->getMessage());
    }
}

// Vérifier si l'utilisateur suit déjà cet auteur
$is_following = false;
$follow_request_pending = false;
$follow_request_number = '';
try {
    // Vérifier si déjà suivi
    $follow_stmt = $pdo->prepare("
        SELECT id FROM author_follows 
        WHERE follower_user_code = ? AND followed_user_code = ?
        LIMIT 1
    ");
    $follow_stmt->execute([$requester_code, $journal['user_code']]);
    $is_following = $follow_stmt->fetch() !== false;
    
    // Vérifier si une demande de suivi est en attente
    $request_stmt = $pdo->prepare("
        SELECT request_number, status FROM follow_requests 
        WHERE requester_user_code = ? AND followed_user_code = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $request_stmt->execute([$requester_code, $journal['user_code']]);
    $request_data = $request_stmt->fetch();
    if ($request_data) {
        $follow_request_number = $request_data['request_number'];
        $follow_request_pending = $request_data['status'] === 'pending';
    }
} catch (PDOException $e) {
    error_log("Erreur vérification follow : " . $e->getMessage());
}

// Traitement du follow/unfollow et demande de suivi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'toggle_follow') {
            if ($is_following) {
                // Unfollow
                $unfollow_stmt = $pdo->prepare("
                    DELETE FROM author_follows 
                    WHERE follower_user_code = ? AND followed_user_code = ?
                ");
                $unfollow_stmt->execute([$requester_code, $journal['user_code']]);
                $is_following = false;
            } else {
                // Follow direct (sans demande)
                $follow_stmt = $pdo->prepare("
                    INSERT INTO author_follows (follower_user_code, followed_user_code) 
                    VALUES (?, ?)
                ");
                $follow_stmt->execute([$requester_code, $journal['user_code']]);
                $is_following = true;
            }
            header("Location: journal_access_request.php?journal_id=$journal_id");
            exit;
        } elseif ($_POST['action'] === 'send_follow_request') {
            $message = trim((string)($_POST['message'] ?? ''));
            
            $pdo->beginTransaction();
            
            // Générer le prochain numéro de demande de suivi pour cet auteur
            $counter_stmt = $pdo->prepare("
                INSERT INTO follow_request_counters (followed_user_code, last_request_number) 
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE last_request_number = last_request_number + 1
            ");
            $counter_stmt->execute([$journal['user_code']]);
            
            // Récupérer le numéro généré
            $get_num_stmt = $pdo->prepare("
                SELECT last_request_number FROM follow_request_counters WHERE followed_user_code = ?
            ");
            $get_num_stmt->execute([$journal['user_code']]);
            $request_num = $get_num_stmt->fetchColumn();
            
            $request_number = 'F' . $request_num;
            
            // Insérer la demande de suivi
            $insert_stmt = $pdo->prepare("
                INSERT INTO follow_requests 
                (request_number, requester_user_code, followed_user_code, status, message) 
                VALUES (?, ?, ?, 'pending', ?)
            ");
            $insert_stmt->execute([
                $request_number,
                $requester_code,
                $journal['user_code'],
                $message
            ]);
            
            // Créer une notification pour l'auteur
            $request_id = (int) $pdo->lastInsertId();
            $notif_stmt = $pdo->prepare("
                INSERT INTO message_notifications (user_code, message_id, type)
                VALUES (?, ?, 'follow_request')
            ");
            $notif_stmt->execute([$journal['user_code'], $request_id]);
            
            $pdo->commit();
            
            header("Location: journal_access_request.php?journal_id=$journal_id&follow_success=$request_number");
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur follow action : " . $e->getMessage());
    }
}

// Traitement du formulaire de demande (uniquement pour journaux payants)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $journal && $is_paid_journal && !isset($_POST['action'])) {
    $message = trim((string)($_POST['message'] ?? ''));
    
    try {
        $pdo->beginTransaction();

        // Vérifier les crédits de demandes
        if ($user_credits <= 0) {
            $error = "Vous n'avez plus de crédits de demandes. Prolongez votre abonnement pour obtenir plus de crédits.";
            $pdo->rollBack();
        } else {

            $reader_stmt = $pdo->prepare("SELECT id FROM journal_readers WHERE journal_id = ? AND user_code = ? LIMIT 1");
            $reader_stmt->execute([$journal_id, $requester_code]);
            if ($reader_stmt->fetch()) {
                $error = "Vous avez déjà accès à ce journal.";
                $pdo->rollBack();
            } else {
        
                // Vérifier si une demande en attente existe déjà
                $check_stmt = $pdo->prepare("
                    SELECT id FROM access_requests 
                    WHERE journal_id = ? AND requester_user_code = ? AND status = 'pending'
                    LIMIT 1
                ");
                $check_stmt->execute([$journal_id, $requester_code]);
                if ($check_stmt->fetch()) {
                    $error = "Vous avez déjà une demande en attente pour ce journal.";
                    $pdo->rollBack();
                } else {
                // Générer le prochain numéro de demande pour ce journal
                $counter_stmt = $pdo->prepare("
                    INSERT INTO access_request_counters (journal_id, last_request_number) 
                    VALUES (?, 1)
                    ON DUPLICATE KEY UPDATE last_request_number = last_request_number + 1
                ");
                $counter_stmt->execute([$journal_id]);
                
                // Récupérer le numéro généré
                $get_num_stmt = $pdo->prepare("
                    SELECT last_request_number FROM access_request_counters WHERE journal_id = ?
                ");
                $get_num_stmt->execute([$journal_id]);
                $request_num = $get_num_stmt->fetchColumn();
                
                $request_number = 'D' . $request_num;
                
                // Insérer la demande
                $insert_stmt = $pdo->prepare("
                    INSERT INTO access_requests 
                    (request_number, journal_id, requester_user_code, author_user_code, status, message) 
                    VALUES (?, ?, ?, ?, 'pending', ?)
                ");
                $insert_stmt->execute([
                    $request_number,
                    $journal_id,
                    $requester_code,
                    $journal['user_code'],
                    $message
                ]);

                // Créer une notification pour l'auteur du journal (sans bloquer si erreur)
                $request_id = (int) $pdo->lastInsertId();
                try {
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO message_notifications (user_code, message_id, type)
                        VALUES (?, ?, 'access_request')
                    ");
                    $notif_stmt->execute([$journal['user_code'], $request_id]);
                } catch (Throwable $e) {
                    // Ne pas bloquer la création de la demande si la notification échoue
                    error_log("Erreur création notification auteur : " . $e->getMessage());
                }

                // Décrémenter les crédits de demandes du demandeur
                $credit_stmt = $pdo->prepare("
                    UPDATE users 
                    SET access_request_credits = access_request_credits - 1 
                    WHERE user_code = ?
                ");
                $credit_stmt->execute([$requester_code]);

                // Message inbox auteur : lier au fil SYSTEM ↔ auteur (sinon is_read reste bloqué et le badge ment).
                try {
                    $author_code = (string) $journal['user_code'];
                    $notif_content = "Nouvelle demande d'accès au journal {$journal_code} ({$request_number}). Connectez-vous pour l'approuver ou la refuser.";
                    $preview = substr($notif_content, 0, 100);

                    $threadSel = $pdo->prepare("
                        SELECT id FROM message_threads
                        WHERE participant_1 = 'SYSTEM' AND participant_2 = ?
                        LIMIT 1
                    ");
                    $threadSel->execute([$author_code]);
                    $tid = $threadSel->fetchColumn();
                    if ($tid) {
                        $tid = (int) $tid;
                        $pdo->prepare("
                            UPDATE message_threads
                            SET last_message_at = NOW(), last_message_preview = ?
                            WHERE id = ?
                        ")->execute([$preview, $tid]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO message_threads (participant_1, participant_2, last_message_at, last_message_preview)
                            VALUES ('SYSTEM', ?, NOW(), ?)
                        ")->execute([$author_code, $preview]);
                        $tid = (int) $pdo->lastInsertId();
                    }

                    $pdo->prepare("
                        INSERT INTO messages (thread_id, sender_user_code, recipient_user_code, content, is_read, expires_at)
                        VALUES (?, 'SYSTEM', ?, ?, 0, DATE_ADD(NOW(), INTERVAL 21 DAY))
                    ")->execute([$tid, $author_code, $notif_content]);
                } catch (Throwable $e) {
                    error_log("Erreur notification messagerie auteur : " . $e->getMessage());
                }

                $pdo->commit();

                $success = "Votre demande d'accès a été envoyée ! L'auteur a été notifié automatiquement.<br>
                          Il pourra approuver votre demande depuis son tableau de bord.<br>
                          Votre numéro de suivi : <strong>{$request_number}</strong>";
            }
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur création demande accès : " . $e->getMessage());
        // Message spécifique si les tables n'existent pas encore
        if (strpos($e->getMessage(), 'access_requests') !== false || 
            strpos($e->getMessage(), 'access_request_counters') !== false ||
            strpos($e->getMessage(), "doesn't exist") !== false ||
            strpos($e->getMessage(), 'Unknown table') !== false) {
            $error = "Le système de demandes d'accès n'est pas encore activé. Veuillez contacter l'administrateur.";
        } else {
            $error = "Erreur lors de l'envoi de la demande. Veuillez réessayer.";
        }
    }
}

if (!$journal) {
    header("Location: dashboard_6.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNTOMA - Demande d'Accès</title>
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
            .text-lg { font-size: 1rem; }
            .p-6 { padding: 1rem; }
            .p-8 { padding: 1.25rem; }
            .py-4 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
            .px-6 { padding-left: 0.75rem; padding-right: 0.75rem; }
        }
    </style>
</head>
<body class="min-h-screen py-4 px-3 sm:py-8 sm:px-4">

    <div class="max-w-lg mx-auto">
        <div class="mb-3">
            <button type="button" onclick="if (history.length > 1) { history.back(); } else { window.location.href='dashboard_6.php'; }"
                    class="inline-flex items-center gap-2 bg-white/80 border border-white text-gray-700 font-bold py-2 px-4 rounded-2xl hover:bg-white transition-all text-xs shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Retour</span>
            </button>
        </div>

        <div class="glass-panel rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-8">
            
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-dark mb-2">Demande d'Accès</h1>
                <p class="text-gray-500 text-sm">Ce journal est payant. Envoyez une demande à l'auteur.</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-center text-sm font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($follow_success): ?>
                <div class="bg-purple-50 text-purple-700 p-6 rounded-2xl mb-6 text-center">
                    <div class="text-4xl font-black text-purple-600 mb-2">✓</div>
                    <p class="font-bold mb-2">Demande de suivi envoyée !</p>
                    <p class="text-sm">Votre demande de suivi a été envoyée à l'auteur.<br>Votre numéro de demande est : <strong><?= htmlspecialchars($follow_success) ?></strong></p>
                    <div class="mt-4 p-4 bg-white rounded-xl border-2 border-purple-200">
                        <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Numéro de demande</p>
                        <p class="text-2xl font-black text-purple-600"><?= htmlspecialchars($follow_success) ?></p>
                    </div>
                </div>
                <div class="text-center mb-6">
                    <a href="dashboard_6.php" class="inline-flex items-center gap-2 bg-dark text-white font-bold py-3 px-8 rounded-2xl hover:bg-black transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Retour
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 text-green-700 p-6 rounded-2xl mb-6 text-center">
                    <div class="text-4xl font-black text-green-600 mb-2">✓</div>
                    <p class="font-bold mb-2">Demande envoyée !</p>
                    <p class="text-sm"><?= $success ?></p>
                    <div class="mt-4 p-4 bg-white rounded-xl border-2 border-green-200">
                        <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-1">Votre numéro de demande</p>
                        <p class="text-2xl font-black text-green-600"><?= preg_replace('/[^0-9]/', '', $request_number) ?></p>
                    </div>
                </div>
                <div class="text-center">
                    <a href="dashboard_6.php" class="inline-flex items-center gap-2 bg-dark text-white font-bold py-3 px-8 rounded-2xl hover:bg-black transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Retour
                    </a>
                </div>
            <?php else: ?>

                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-6">
                    <?php
                        $author_display_name = trim((string)($journal['first_name'] ?? '') . ' ' . (string)($journal['last_name'] ?? ''));
                        if ($author_display_name === '') {
                            $author_display_name = (string)($journal['name'] ?? $journal['user_code']);
                        }
                        $author_profile_pic = !empty($journal['author_profile_pic']) ? '../' . $journal['author_profile_pic'] : '../images/user_default.png';
                    ?>
                    <div class="flex items-start space-x-3">
                        <?php if (!empty($journal['cover_image'])): ?>
                            <img src="../<?= htmlspecialchars($journal['cover_image']) ?>" alt="" class="w-16 h-16 rounded-xl object-cover">
                        <?php else: ?>
                            <div class="w-16 h-16 bg-orange-200 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <p class="text-xs text-orange-600 font-bold uppercase tracking-wider"><?= $journal_code ?></p>
                            <h3 class="font-bold text-dark"><?= htmlspecialchars($journal['title']) ?></h3>
                            <div class="mt-2 flex items-center gap-2">
                                <img src="<?= htmlspecialchars($author_profile_pic) ?>" alt="" class="w-7 h-7 rounded-lg object-cover border border-orange-100">
                                <p class="text-sm text-gray-600">
                                    par <span class="font-bold text-dark"><?= htmlspecialchars($author_display_name) ?></span>
                                    <span class="text-[10px] font-black text-primary ml-1"><?= htmlspecialchars($journal['user_code']) ?></span>
                                </p>
                            </div>
                            <p class="text-lg font-black text-orange-600 mt-1"><?= number_format($journal['price'], 2) ?> <?= $journal['price_currency'] ?></p>
                            <a href="message_send.php?to=<?= urlencode($journal['user_code']) ?>&context=access_request&journal_id=<?= (int)$journal_id ?>"
                               class="inline-flex items-center gap-1.5 mt-2 bg-white border border-orange-200 text-orange-700 text-xs font-black py-2 px-3 rounded-xl hover:bg-orange-100 transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <span>Écrire à l'auteur</span>
                            </a>
                        </div>
                        <div class="flex flex-col gap-2">
                            <?php if ($follow_request_pending): ?>
                                <div class="bg-purple-100 text-purple-700 text-xs font-bold py-2 px-3 rounded-xl text-center">
                                    Demande en attente (<?= htmlspecialchars($follow_request_number) ?>)
                                </div>
                            <?php elseif ($is_following): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle_follow">
                                    <button type="submit" class="bg-purple-600 text-white font-bold py-2 px-4 rounded-xl text-sm hover:opacity-90 transition-all flex items-center space-x-1">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>Suivi</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button onclick="document.getElementById('follow-request-form').classList.toggle('hidden')" 
                                        class="bg-purple-100 text-purple-600 font-bold py-2 px-4 rounded-xl text-sm hover:bg-purple-200 transition-all flex items-center space-x-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span>Suivre</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de demande de suivi -->
                <div id="follow-request-form" class="hidden bg-purple-50 border border-purple-200 rounded-2xl p-5 mb-6">
                    <h3 class="font-bold text-purple-800 mb-3">Demander à suivre cet auteur</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_follow_request">
                        <div class="space-y-3">
                            <textarea name="message" rows="3" placeholder="Bonjour, je souhaite suivre votre travail..." 
                                      class="w-full bg-white border border-purple-200 rounded-xl py-3 px-4 text-sm focus:ring-2 focus:ring-purple-500 outline-none resize-none"></textarea>
                            <button type="submit" class="w-full bg-purple-600 text-white font-bold py-3 rounded-xl hover:bg-purple-700 transition-all text-sm">
                                Envoyer la demande de suivi
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($is_paid_journal): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-blue-600 font-bold uppercase tracking-wider">Vos crédits de demandes</p>
                            <p class="text-2xl font-black text-blue-700"><?= $user_credits ?> <span class="text-sm font-normal text-blue-600">/ 100</span></p>
                        </div>
                        <?php if ($user_credits <= 10): ?>
                            <div class="text-right">
                                <p class="text-xs text-orange-600 font-bold">Crédits faibles !</p>
                                <a href="payment_11.php" class="text-xs font-bold text-blue-700 hover:underline">Prolonger →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($is_paid_journal): ?>
                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-orange-800">Besoin d'un forfait actif</p>
                            <p class="text-xs text-orange-700 mt-1">Pour envoyer des demandes d'accès aux journaux payants, votre abonnement doit être actif. Prolongez votre forfait pour obtenir 100 crédits de demandes.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($existing_access_request): ?>
                <!-- Statut de la demande existante -->
                <?php
                    $req_status = $existing_access_request['status'];
                    $req_number = $existing_access_request['request_number'];
                    $req_date = date('d/m/Y H:i', strtotime($existing_access_request['created_at']));
                ?>
                <?php if ($req_status === 'approved'): ?>
                <div class="bg-green-50 border-2 border-green-300 rounded-2xl p-5 mb-5 shadow-lg shadow-green-500/10">
                    <div class="flex items-start space-x-3">
                        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-base font-black text-green-800 mb-1">✓ Demande approuvée !</p>
                            <p class="text-xs text-green-700">Votre demande <strong><?= htmlspecialchars($req_number) ?></strong> du <?= $req_date ?> a été acceptée par l'auteur.</p>
                            <?php if (!empty($existing_access_request['response_message'])): ?>
                            <p class="text-xs italic text-gray-600 mt-2 bg-white/50 p-2 rounded-lg">"<?= htmlspecialchars($existing_access_request['response_message']) ?>"</p>
                            <?php endif; ?>
                            <a href="journal_view.php?id=<?= $journal_id ?>" class="inline-block mt-3 bg-green-600 text-white font-bold py-2 px-5 rounded-xl hover:bg-green-700 transition-all text-sm">
                                Lire le journal →
                            </a>
                        </div>
                    </div>
                </div>
                <?php elseif ($req_status === 'rejected'): ?>
                <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-5 mb-5">
                    <div class="flex items-start space-x-3">
                        <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-base font-black text-red-800 mb-1">✗ Demande refusée</p>
                            <p class="text-xs text-red-700">Votre demande <strong><?= htmlspecialchars($req_number) ?></strong> du <?= $req_date ?> a été refusée par l'auteur.</p>
                            <?php if (!empty($existing_access_request['response_message'])): ?>
                            <p class="text-xs italic text-gray-600 mt-2 bg-white/50 p-2 rounded-lg">"<?= htmlspecialchars($existing_access_request['response_message']) ?>"</p>
                            <?php endif; ?>
                            <p class="text-xs text-red-600 mt-2">Vous pouvez contacter l'auteur pour discuter ou faire une nouvelle demande.</p>
                        </div>
                    </div>
                </div>
                <?php elseif ($req_status === 'pending'): ?>
                <div class="bg-orange-50 border-2 border-orange-200 rounded-2xl p-5 mb-5">
                    <div class="flex items-start space-x-3">
                        <div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center flex-shrink-0 animate-pulse">
                            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-base font-black text-orange-800 mb-1">⏳ Demande en attente</p>
                            <p class="text-xs text-orange-700">Votre demande <strong><?= htmlspecialchars($req_number) ?></strong> du <?= $req_date ?> attend la validation de l'auteur.</p>
                            <p class="text-xs text-orange-600 mt-2">L'auteur sera notifié et vous recevrez une réponse une fois la demande traitée.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($is_paid_journal): ?>
                <!-- Explication du processus pour journaux payants -->
                <div class="bg-gradient-to-br from-blue-50 to-purple-50 border border-blue-200 rounded-2xl p-4 mb-5">
                    <div class="flex items-start space-x-3 mb-3">
                        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-blue-900">Comment accéder à ce journal payant ?</p>
                    </div>
                    <ol class="space-y-2 text-xs text-gray-700 ml-2">
                        <li class="flex gap-2"><span class="font-black text-blue-600">1.</span> <span><strong>Discutez</strong> avec l'auteur via la messagerie pour convenir du paiement</span></li>
                        <li class="flex gap-2"><span class="font-black text-blue-600">2.</span> <span><strong>Effectuez le paiement</strong> en dehors du site (Mobile Money, virement, espèces, etc.)</span></li>
                        <li class="flex gap-2"><span class="font-black text-blue-600">3.</span> <span><strong>Envoyez la demande</strong> d'accès officielle (ci-dessous) pour recevoir un numéro D1, D2...</span></li>
                        <li class="flex gap-2"><span class="font-black text-blue-600">4.</span> <span>L'auteur <strong>approuve</strong> votre demande dès qu'il a reçu le paiement</span></li>
                        <li class="flex gap-2"><span class="font-black text-blue-600">5.</span> <span>Vous obtenez un accès permanent au journal ✨</span></li>
                    </ol>
                </div>
                <?php endif; ?>

                <!-- Bouton principal "Écrire à l'auteur" - toujours visible -->
                <a href="message_send.php?to=<?= urlencode($journal['user_code']) ?>&context=access_request&journal_id=<?= (int)$journal_id ?>" 
                   class="w-full bg-primary text-white font-bold py-4 rounded-2xl shadow-xl hover:bg-blue-600 transition-all text-sm flex items-center justify-center space-x-2 mb-4">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span><?= $is_paid_journal ? 'Écrire à l\'auteur pour négocier' : 'Écrire à l\'auteur' ?></span>
                </a>

                <?php if ($is_paid_journal): ?>
                <!-- Séparateur -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-white px-3 text-gray-400 font-bold tracking-wider">Après paiement convenu</span>
                    </div>
                </div>

                <form method="POST" class="space-y-5">
                    <div class="space-y-2">
                        <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2">Message à joindre à votre demande</label>
                        <textarea name="message" rows="3" placeholder="Bonjour, j'ai effectué le paiement convenu via..." 
                                  class="w-full input-lucide rounded-2xl py-4 px-6 font-medium text-dark placeholder-gray-300 resize-none"></textarea>
                        <p class="text-xs text-gray-400 ml-2">Mentionnez le mode de paiement et la référence pour faciliter l'approbation</p>
                    </div>

                    <button type="submit" class="w-full bg-orange-500 text-white font-bold py-3.5 rounded-2xl shadow-xl shadow-orange-500/30 hover:bg-orange-600 transition-all text-sm flex items-center justify-center space-x-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Envoyer la demande d'accès officielle</span>
                    </button>
                </form>
                <?php else: ?>
                <!-- Pour les journaux non-payants, afficher un message informatif -->
                <div class="bg-green-50 border border-green-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-green-800">Journal en accès libre</p>
                            <p class="text-xs text-green-700 mt-1">Ce journal est public, vous pouvez le consulter directement. Aucune demande d'accès nécessaire.</p>
                        </div>
                    </div>
                </div>
                <a href="journal_view.php?id=<?= $journal_id ?>" class="w-full bg-dark text-white font-bold py-3.5 rounded-2xl shadow-xl hover:bg-black transition-all text-sm flex items-center justify-center space-x-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Lire le journal</span>
                </a>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <a href="dashboard_6.php" class="text-sm font-bold text-gray-500 hover:text-primary transition-colors">
                        ← Annuler et retourner
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>
