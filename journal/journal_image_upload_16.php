<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_image_upload_16.php
 * VERSION : 16
 * DESCRIPTION : Upload d'image pour une page de journal
 */

session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$user_code = $_SESSION['user_id'];
$journal_id = (int)($_POST['journal_id'] ?? 0);
$page_id = (int)($_POST['page_id'] ?? 0);

if ($journal_id === 0 || $page_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
    exit;
}

// Vérifier que le journal appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id FROM journals WHERE id = ? AND user_code = ?");
$stmt->execute([$journal_id, $user_code]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Journal non trouvé']);
    exit;
}

// Vérifier l'upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Erreur upload']);
    exit;
}

$file = $_FILES['image'];
$max_size = 5 * 1024 * 1024; // 5MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'Image trop grande (max 5MB)']);
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Type non autorisé (JPG, PNG, GIF, WebP)']);
    exit;
}

// Créer le dossier si nécessaire
$upload_dir = "../uploads/journals/$journal_id/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Générer un nom avec format: user_code_journalID_pageID.jpg
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $user_code . '_j' . $journal_id . '_p' . $page_id . '.' . $extension;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Mettre à jour la page avec le chemin de l'image
    $relative_path = "uploads/journals/$journal_id/$filename";
    
    $stmt = $pdo->prepare("UPDATE journal_pages SET image_path = ? WHERE id = ? AND journal_id = ?");
    $stmt->execute([$relative_path, $page_id, $journal_id]);
    
    echo json_encode(['success' => true, 'path' => '../' . $relative_path]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde']);
}
