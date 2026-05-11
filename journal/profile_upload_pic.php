<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/profile_upload_pic.php
 * DESCRIPTION : Upload de la photo de profil avec convention A5_image1.jpg
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile_edit.php");
    exit;
}

$user_code = $_SESSION['user_id'];

if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    header("Location: profile_edit.php?error=upload_failed");
    exit;
}

$file = $_FILES['profile_pic'];
$max_size = 5 * 1024 * 1024; // 5MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['size'] > $max_size) {
    header("Location: profile_edit.php?error=file_too_large");
    exit;
}

$file_type = mime_content_type($file['tmp_name']);
if (!in_array($file_type, $allowed_types)) {
    header("Location: profile_edit.php?error=invalid_type");
    exit;
}

try {
    if (!gntoma_users_has_profile_pic_column($pdo)) {
        header("Location: profile_edit.php?error=upload_failed");
        exit;
    }

    $upload_dir = '../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Convention de nommage: A5_image1.jpg, A5_image2.jpg, etc.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($ext)) $ext = 'jpg';

    // Compter les images existantes de cet utilisateur
    $existing = glob($upload_dir . strtolower($user_code) . '_image*');
    $count = is_array($existing) ? count($existing) + 1 : 1;
    $filename = strtolower($user_code) . '_image' . $count . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative_path = 'uploads/profiles/' . $filename;

        // Mettre à jour la base
        $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE user_code = ?");
        $stmt->execute([$relative_path, $user_code]);

        header("Location: profile_edit.php?success=photo_updated");
        exit;
    } else {
        header("Location: profile_edit.php?error=upload_failed");
        exit;
    }

} catch (PDOException $e) {
    error_log("Erreur upload profil : " . $e->getMessage());
    header("Location: profile_edit.php?error=system");
    exit;
}
