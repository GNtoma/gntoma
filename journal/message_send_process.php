<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_send_process.php
 * DESCRIPTION : Traitement de l'envoi de message
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: messages_list.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$recipient_code = strtoupper(trim((string)($_POST['recipient_code'] ?? '')));
$content = trim((string)($_POST['content'] ?? ''));
$keywords = trim((string)($_POST['keywords'] ?? ''));
$context = trim((string)($_POST['context'] ?? ''));
$request_id = (int)($_POST['request_id'] ?? 0);
$journal_id = (int)($_POST['journal_id'] ?? 0);

// Validation
if (empty($recipient_code) || empty($content)) {
    header("Location: message_send.php?error=missing_data");
    exit;
}

// Empêcher l'envoi à soi-même
if ($recipient_code === $user_code) {
    header("Location: message_send.php?error=self_message");
    exit;
}

// Limiter la longueur
if (strlen($content) > 5000) {
    header("Location: message_send.php?error=content_too_long");
    exit;
}

if ($context === 'access_request' && $request_id > 0) {
    $contextKeyword = 'ACCESS_REQUEST_D' . $request_id;
    if ($journal_id > 0) {
        $contextKeyword .= '_J' . $journal_id;
    }
    $keywords = trim($keywords . ' ' . $contextKeyword);
}

try {
    // Vérifier si le destinataire existe
    $recipient_stmt = $pdo->prepare("SELECT user_code FROM users WHERE user_code = ? LIMIT 1");
    $recipient_stmt->execute([$recipient_code]);
    if (!$recipient_stmt->fetch()) {
        header("Location: message_send.php?error=user_not_found");
        exit;
    }
    
    // Vérifier si on est bloqué par le destinataire
    $blocked_stmt = $pdo->prepare("
        SELECT 1 FROM user_blocks 
        WHERE blocker_user_code = ? AND blocked_user_code = ?
        LIMIT 1
    ");
    $blocked_stmt->execute([$recipient_code, $user_code]);
    if ($blocked_stmt->fetch()) {
        header("Location: message_send.php?to=" . $recipient_code . "&error=blocked");
        exit;
    }
    
    // Vérifier si on a bloqué le destinataire
    $block_stmt = $pdo->prepare("
        SELECT 1 FROM user_blocks
        WHERE blocker_user_code = ? AND blocked_user_code = ?
        LIMIT 1
    ");
    $block_stmt->execute([$user_code, $recipient_code]);
    if ($block_stmt->fetch()) {
        header("Location: message_send.php?error=you_blocked");
        exit;
    }

    // Gratuit si négociation d'accès journal payant entre les deux
    $is_free = false;
    try {
        $free_check = $pdo->prepare("
            SELECT 1 FROM access_requests ar
            JOIN journals j ON ar.journal_id = j.id
            WHERE j.status = 'paid'
              AND ar.status IN ('pending','approved')
              AND (
                  (ar.requester_user_code = ? AND ar.author_user_code = ?)
               OR (ar.requester_user_code = ? AND ar.author_user_code = ?)
              )
            LIMIT 1
        ");
        $free_check->execute([$user_code, $recipient_code, $recipient_code, $user_code]);
        $is_free = (bool) $free_check->fetch();
    } catch (PDOException $e) {
        error_log("free_check ignoré (table access_requests peut-être absente) : " . $e->getMessage());
        $is_free = false;
    }

    // Vérifier les crédits (sauf messages gratuits)
    if (!$is_free) {
        $credits_stmt = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ? FOR UPDATE");
        $credits_stmt->execute([$user_code]);
        $credits = $credits_stmt->fetch();

        if (!$credits || $credits['remaining_credits'] < 1) {
            header("Location: messages_buy.php?error=insufficient_credits");
            exit;
        }
    }
    
    // Gestion de la pièce jointe
    $attachment_path = null;
    $has_attachment = false;
    $attachment_type = null;
    
    if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['attachment']['tmp_name']);

        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../uploads/messages/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Format: user_code_image1.jpg, user_code_image2.jpg, etc.
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $counter_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE sender_user_code = ? AND has_attachment = 1");
            $counter_stmt->execute([$user_code]);
            $count = $counter_stmt->fetch()['count'] + 1;
            $file_name = $user_code . '_image' . $count . '.' . $ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                $attachment_path = 'uploads/messages/' . $file_name;
                $has_attachment = true;
                $attachment_type = 'image';
            }
        }
    }
    
    // Thread ID fourni (depuis le chat) ou créer/trouver
    $provided_thread_id = (int)($_POST['thread_id'] ?? 0);
    
    if ($provided_thread_id > 0) {
        // Vérifier que le thread existe et appartient à l'utilisateur
        $verify_stmt = $pdo->prepare("
            SELECT id FROM message_threads 
            WHERE id = ? AND (participant_1 = ? OR participant_2 = ?)
            LIMIT 1
        ");
        $verify_stmt->execute([$provided_thread_id, $user_code, $user_code]);
        $thread = $verify_stmt->fetch();
        
        if ($thread) {
            $thread_id = $thread['id'];
            // Mettre à jour le thread
            $pdo->prepare("
                UPDATE message_threads 
                SET last_message_at = NOW(), last_message_preview = ?
                WHERE id = ?
            ")->execute([substr($content, 0, 255), $thread_id]);
        } else {
            $thread_id = 0; // Sécurité: créer un nouveau thread
        }
    }
    
    // Si pas de thread_id valide, chercher ou créer
    if (empty($thread_id)) {
        $thread_stmt = $pdo->prepare("
            SELECT id FROM message_threads 
            WHERE (participant_1 = ? AND participant_2 = ?) 
               OR (participant_1 = ? AND participant_2 = ?)
            LIMIT 1
        ");
        $thread_stmt->execute([$user_code, $recipient_code, $recipient_code, $user_code]);
        $thread = $thread_stmt->fetch();
        
        if ($thread) {
            $thread_id = $thread['id'];
            $pdo->prepare("
                UPDATE message_threads 
                SET last_message_at = NOW(), last_message_preview = ?
                WHERE id = ?
            ")->execute([substr($content, 0, 255), $thread_id]);
        } else {
            $new_thread_stmt = $pdo->prepare("
                INSERT INTO message_threads (participant_1, participant_2, last_message_at, last_message_preview)
                VALUES (?, ?, NOW(), ?)
            ");
            $new_thread_stmt->execute([$user_code, $recipient_code, substr($content, 0, 255)]);
            $thread_id = $pdo->lastInsertId();
        }
    }
    
    // Insérer le message
    $credits_to_consume = $is_free ? 0 : 1;
    $insert_stmt = $pdo->prepare("
        INSERT INTO messages (
            thread_id, sender_user_code, recipient_user_code,
            content, has_attachment, attachment_path, attachment_type, keywords, expires_at, credits_consumed
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 21 DAY), ?)
    ");
    $insert_stmt->execute([
        $thread_id, $user_code, $recipient_code,
        $content, $has_attachment ? 1 : 0, $attachment_path, $attachment_type, $keywords, $credits_to_consume
    ]);
    $message_id = $pdo->lastInsertId();

    // Créer une notification
    $notif_stmt = $pdo->prepare("
        INSERT INTO message_notifications (user_code, message_id, type)
        VALUES (?, ?, 'new_message')
    ");
    $notif_stmt->execute([$recipient_code, $message_id]);

    // Déduire les crédits (sauf messages gratuits)
    if (!$is_free) {
        $pdo->prepare("
            UPDATE message_credits
            SET used_credits = used_credits + 1, remaining_credits = remaining_credits - 1
            WHERE user_code = ?
        ")->execute([$user_code]);
    }
    
    // Rediriger vers la conversation
    header("Location: message_chat.php?thread=" . $thread_id);
    exit;
    
} catch (PDOException $e) {
    error_log("Erreur envoi message : " . $e->getMessage());
    header("Location: message_send.php?error=system");
    exit;
}
