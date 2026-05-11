<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_create_traitement_10.php
 * VERSION : 10
 * DESCRIPTION : Traitement de création de journal (Sécurité, Validation, Gestion d'erreurs).
 */

session_start();
require_once 'config.php';

// Sécurité : Uniquement le POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: journal_create_9.php");
    exit;
}

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Récupération et validation des données
$title = trim((string)($_POST['title'] ?? ''));
$status = $_POST['status'] ?? 'private';
$keywords = trim((string)($_POST['keywords'] ?? ''));
$price = null;
if ($status === 'paid' && isset($_POST['price']) && is_numeric($_POST['price'])) {
    $price = (float)$_POST['price'];
    if ($price < 0) $price = 0;
}
$user_code = $_SESSION['user_id'];

// Validation des entrées
$errors = [];

if (empty($title)) {
    $errors[] = 'empty_fields';
}

if (strlen($title) > 255) {
    $errors[] = 'title_too_long';
}

if (!in_array($status, ['private', 'public', 'paid'])) {
    $errors[] = 'invalid_status';
}

// Vérification de l'abonnement actif
try {
    $stmt = $pdo->prepare("SELECT sub_status, sub_expires_at FROM users WHERE user_code = ?");
    $stmt->execute([$user_code]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: ../index.php");
        exit;
    }

    // Vérifier si l'abonnement est valide
    $now = new DateTime();
    $current_status = (string) ($user['sub_status'] ?? 'trial');

    try {
        $expiry = new DateTime(!empty($user['sub_expires_at']) ? (string) $user['sub_expires_at'] : '+48 hours');
    } catch (Throwable $e) {
        error_log("Erreur date abonnement GNTOMA création journal : " . $e->getMessage());
        $expiry = (new DateTime())->modify('+48 hours');
    }
    
    if ($now > $expiry) {
        if ($current_status !== 'expired') {
            $upd_stmt = $pdo->prepare("UPDATE users SET sub_status = 'expired' WHERE user_code = ?");
            $upd_stmt->execute([$user_code]);
        }
        header("Location: dashboard_6.php?error=subscription_expired");
        exit;
    }

} catch (PDOException $e) {
    error_log("Erreur vérification abonnement GNTOMA : " . $e->getMessage());
    header("Location: journal_create_9.php?error=system_error");
    exit;
}

// Si erreurs de validation, rediriger avec le premier code d'erreur
if (!empty($errors)) {
    header("Location: journal_create_9.php?error=" . urlencode($errors[0]));
    exit;
}

// Traitement de la création du journal
try {
    $pdo->beginTransaction();
    
    // Génération de l'identifiant du journal (format: A1J1, A1J2...)
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM journals WHERE user_code = ?");
    $stmt->execute([$user_code]);
    $journal_count = (int)$stmt->fetchColumn();
    $journal_id = $user_code . 'J' . ($journal_count + 1);
    
    // Traitement de l'image de couverture
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if ($file['size'] <= $max_size && in_array($file['type'], $allowed_types)) {
            $upload_dir = "../uploads/journals/covers/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            // Format: user_code_cover1.jpg, user_code_cover2.jpg, etc.
            $filename = $user_code . '_cover' . ($journal_count + 1) . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $cover_image = "uploads/journals/covers/" . $filename;
            }
        }
    }
    
    // Calculer la date d'expiration (10 ans)
    $expires_at = date('Y-m-d', strtotime('+10 years'));
    
    // Insertion du journal (avec fallback si expires_at n'existe pas encore)
    try {
        $insert_stmt = $pdo->prepare("
            INSERT INTO journals (user_code, title, status, cover_image, keywords, price, price_currency, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $currency = 'USD'; // Prix journal affiché en dollars US
        $insert_stmt->execute([$user_code, $title, $status, $cover_image, $keywords, $price, $currency, $expires_at]);
    } catch (PDOException $e) {
        // Fallback si la colonne expires_at n'existe pas encore (migration 012 pas exécutée)
        if (strpos($e->getMessage(), 'expires_at') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO journals (user_code, title, status, cover_image, keywords, price, price_currency) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $currency = 'USD';
            $insert_stmt->execute([$user_code, $title, $status, $cover_image, $keywords, $price, $currency]);
        } else {
            throw $e; // Relancer si c'est une autre erreur
        }
    }
    
    $journal_db_id = $pdo->lastInsertId();
    
    // Enregistrement de l'action pour le futur classement
    // On pourrait aussi tracker cette action dans une table d'activités
    
    $pdo->commit();
    
    // Redirection vers le dashboard avec message de succès
    header("Location: dashboard_6.php?success=journal_created&journal_id=" . $journal_db_id);
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Erreur création journal GNTOMA : " . $e->getMessage());
    
    // Déterminer le type d'erreur pour un message approprié
    $error_code = 'system_error';
    
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $error_code = 'duplicate_journal';
    }
    
    header("Location: journal_create_9.php?error=" . urlencode($error_code));
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erreur inattendue création journal GNTOMA : " . $e->getMessage());
    header("Location: journal_create_9.php?error=system_error");
    exit;
}
