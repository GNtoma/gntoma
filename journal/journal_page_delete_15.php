<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_page_delete_15.php
 * VERSION : 15
 * DESCRIPTION : Supprimer une page d'un journal
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
$page_id = (int)($_POST['page_id'] ?? 0);
$journal_id = (int)($_POST['journal_id'] ?? 0);

if ($page_id === 0 || $journal_id === 0) {
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

// Vérifier qu'il reste au moins une page
$stmt = $pdo->prepare("SELECT COUNT(*) FROM journal_pages WHERE journal_id = ?");
$stmt->execute([$journal_id]);
$page_count = (int)$stmt->fetchColumn();

if ($page_count <= 1) {
    header("Location: journal_edit_13.php?id=$journal_id&error=cannot_delete_last_page");
    exit;
}

// Supprimer la page
$stmt = $pdo->prepare("DELETE FROM journal_pages WHERE id = ? AND journal_id = ?");
$stmt->execute([$page_id, $journal_id]);

// Réordonner les pages restantes
$stmt = $pdo->prepare("SELECT id FROM journal_pages WHERE journal_id = ? ORDER BY page_order ASC, id ASC");
$stmt->execute([$journal_id]);
$pages = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($pages as $index => $pid) {
    $new_order = $index + 1;
    $stmt = $pdo->prepare("UPDATE journal_pages SET page_order = ? WHERE id = ?");
    $stmt->execute([$new_order, $pid]);
}

header("Location: journal_edit_13.php?id=$journal_id&success=page_deleted");
exit();
