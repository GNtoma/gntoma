<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_bulk_process.php
 * DESCRIPTION : Traitement de l'envoi de messages groupés
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: messages_list.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$BULK_COST = 100;

// Récupérer les données
$filter_gender = $_POST['filter_gender'] ?? 'all';
$filter_city = trim((string)($_POST['filter_city'] ?? ''));
$filter_commune = trim((string)($_POST['filter_commune'] ?? ''));
$filter_age_min = !empty($_POST['filter_age_min']) ? (int)$_POST['filter_age_min'] : null;
$filter_age_max = !empty($_POST['filter_age_max']) ? (int)$_POST['filter_age_max'] : null;
$content = trim((string)($_POST['content'] ?? ''));
$keywords = trim((string)($_POST['keywords'] ?? ''));

// Validation
if (empty($content)) {
    header("Location: message_bulk.php?error=missing_content");
    exit;
}

if (strlen($content) > 5000) {
    header("Location: message_bulk.php?error=content_too_long");
    exit;
}

try {
    // Vérifier les crédits avec verrouillage
    $credits_stmt = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ? FOR UPDATE");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    
    if (!$credits || $credits['remaining_credits'] < $BULK_COST) {
        header("Location: messages_buy.php?error=insufficient_credits&need=$BULK_COST");
        exit;
    }
    
    // Construire la requête pour trouver les destinataires
    $where_clauses = ["u.user_code != ?", "u.user_code NOT IN (SELECT blocked_user_code FROM user_blocks WHERE blocker_user_code = ?)"];
    $params = [$user_code, $user_code];
    
    if ($filter_gender !== 'all') {
        $where_clauses[] = "u.gender = ?";
        $params[] = $filter_gender;
    }
    
    if (!empty($filter_city)) {
        $where_clauses[] = "LOWER(u.city) LIKE LOWER(?)";
        $params[] = '%' . $filter_city . '%';
    }
    
    if (!empty($filter_commune)) {
        $where_clauses[] = "LOWER(u.commune) LIKE LOWER(?)";
        $params[] = '%' . $filter_commune . '%';
    }
    
    if ($filter_age_min !== null) {
        $where_clauses[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= ?";
        $params[] = $filter_age_min;
    }
    
    if ($filter_age_max !== null) {
        $where_clauses[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) <= ?";
        $params[] = $filter_age_max;
    }
    
    // Récupérer les destinataires
    $recipients_sql = "SELECT u.user_code FROM users u WHERE " . implode(" AND ", $where_clauses) . " LIMIT 1000";
    $recipients_stmt = $pdo->prepare($recipients_sql);
    $recipients_stmt->execute($params);
    $recipients = $recipients_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($recipients)) {
        header("Location: message_bulk.php?error=no_recipients");
        exit;
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
    
    // Critères du filtre pour stockage
    $filter_criteria = json_encode([
        'gender' => $filter_gender,
        'city' => $filter_city,
        'commune' => $filter_commune,
        'age_min' => $filter_age_min,
        'age_max' => $filter_age_max
    ]);
    
    $pdo->beginTransaction();
    
    // Envoyer à chaque destinataire
    $recipient_count = 0;
    foreach ($recipients as $recipient_code) {
        // Vérifier si bloqué par le destinataire
        $blocked_check = $pdo->prepare("
            SELECT 1 FROM user_blocks 
            WHERE blocker_user_code = ? AND blocked_user_code = ?
            LIMIT 1
        ");
        $blocked_check->execute([$recipient_code, $user_code]);
        if ($blocked_check->fetch()) {
            continue; // Sauter si bloqué
        }
        
        // Créer ou mettre à jour le thread
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
            $new_thread = $pdo->prepare("
                INSERT INTO message_threads (participant_1, participant_2, last_message_at, last_message_preview)
                VALUES (?, ?, NOW(), ?)
            ");
            $new_thread->execute([$user_code, $recipient_code, substr($content, 0, 255)]);
            $thread_id = $pdo->lastInsertId();
        }
        
        // Insérer le message
        $message_stmt = $pdo->prepare("
            INSERT INTO messages (thread_id, sender_user_code, recipient_user_code, content,
                                  is_bulk, bulk_filter_criteria, has_attachment, attachment_path, 
                                  attachment_type, keywords, credits_consumed)
            VALUES (?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?, 0)
        ");
        $message_stmt->execute([
            $thread_id, $user_code, $recipient_code, $content,
            $filter_criteria, $has_attachment, $attachment_path,
            $attachment_type, $keywords
        ]);
        $message_id = $pdo->lastInsertId();
        
        // Créer notification
        $notif_stmt = $pdo->prepare("
            INSERT INTO message_notifications (user_code, message_id, type)
            VALUES (?, ?, 'bulk_message')
        ");
        $notif_stmt->execute([$recipient_code, $message_id]);
        
        $recipient_count++;
    }
    
    // Déduire les crédits (100 crédits pour tout le groupe)
    $pdo->prepare("
        UPDATE message_credits 
        SET used_credits = used_credits + ?, remaining_credits = remaining_credits - ?
        WHERE user_code = ?
    ")->execute([$BULK_COST, $BULK_COST, $user_code]);
    
    $pdo->commit();
    
    header("Location: messages_list.php?success=bulk_sent&count=" . $recipient_count);
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur envoi groupé : " . $e->getMessage());
    header("Location: message_bulk.php?error=system");
    exit;
}
