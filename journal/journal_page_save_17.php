<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_page_save_17.php
 * VERSION : 17
 * DESCRIPTION : Sauvegarder le contenu d'une page de journal
 */

session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$user_code = $_SESSION['user_id'];
$page_id = (int)($_POST['page_id'] ?? 0);
$journal_id = (int)($_POST['journal_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$content = (string)($_POST['content'] ?? '');

if ($page_id === 0 || $journal_id === 0) {
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

// Nettoyer le contenu HTML (autoriser certains tags)
$allowed_tags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><img><div><span><a>';
$clean_content = strip_tags($content, $allowed_tags);

// Mettre à jour la page
$stmt = $pdo->prepare("
    UPDATE journal_pages 
    SET title = ?, content = ?, updated_at = NOW()
    WHERE id = ? AND journal_id = ?
");
$stmt->execute([$title, $clean_content, $page_id, $journal_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    // Vérifier si la page existe
    $stmt = $pdo->prepare("SELECT id FROM journal_pages WHERE id = ? AND journal_id = ?");
    $stmt->execute([$page_id, $journal_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true]); // Pas de changement mais page existe
    } else {
        echo json_encode(['success' => false, 'error' => 'Page non trouvée']);
    }
}
