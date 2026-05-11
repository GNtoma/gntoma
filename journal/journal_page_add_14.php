<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_page_add_14.php
 * VERSION : 14
 * DESCRIPTION : Ajouter une nouvelle page à un journal
 */

session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard_6.php");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = $_SESSION['user_id'];
$journal_id = (int)($_POST['journal_id'] ?? 0);

if ($journal_id === 0) {
    header("Location: dashboard_6.php");
    exit;
}

// Vérifier que le journal appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id FROM journals WHERE id = ? AND user_code = ?");
$stmt->execute([$journal_id, $user_code]);
if (!$stmt->fetch()) {
    header("Location: dashboard_6.php?error=journal_not_found");
    exit;
}

// Trouver le prochain numéro de page
$stmt = $pdo->prepare("SELECT MAX(page_order) FROM journal_pages WHERE journal_id = ?");
$stmt->execute([$journal_id]);
$max_order = (int)$stmt->fetchColumn();
$new_order = $max_order + 1;

// Créer la nouvelle page
$stmt = $pdo->prepare("INSERT INTO journal_pages (journal_id, title, page_order) VALUES (?, ?, ?)");
$stmt->execute([$journal_id, "Page $new_order", $new_order]);
$new_page_id = $pdo->lastInsertId();

// Rediriger vers la nouvelle page
header("Location: journal_edit_13.php?id=$journal_id&page=$new_order&success=page_added");
exit();
