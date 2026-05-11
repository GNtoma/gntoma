<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_comment_add.php
 * DESCRIPTION : Traitement de l'ajout d'un commentaire/question sur un journal
 */

session_start();
require_once 'config.php';

// Vérification de session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=session_required");
    exit;
}

$user_code = $_SESSION['user_id'];

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard_6.php");
    exit;
}

// Récupérer les données
$journal_id = (int)($_POST['journal_id'] ?? 0);
$author_user_code = trim((string)($_POST['author_user_code'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));

// Validation
if (!$journal_id || empty($content) || empty($author_user_code)) {
    header("Location: journal_view.php?id=" . $journal_id . "&error=missing_data");
    exit;
}

// Limiter la longueur du commentaire
if (strlen($content) > 2000) {
    header("Location: journal_view.php?id=" . $journal_id . "&error=content_too_long");
    exit;
}

try {
    // Vérifier que le journal existe et est accessible
    $journal_stmt = $pdo->prepare("
        SELECT id, status, user_code FROM journals 
        WHERE id = ? AND status IN ('public', 'paid')
        LIMIT 1
    ");
    $journal_stmt->execute([$journal_id]);
    $journal = $journal_stmt->fetch();
    
    if (!$journal) {
        header("Location: dashboard_6.php?error=journal_not_found");
        exit;
    }
    
    // Vérifier que l'auteur correspond bien au journal
    if ($journal['user_code'] !== $author_user_code) {
        header("Location: journal_view.php?id=" . $journal_id . "&error=invalid_author");
        exit;
    }
    
    // La vérification empêchant l'auteur de commenter a été supprimée
    // pour lui permettre de répondre aux questions des lecteurs.
    
    // Récupérer le parent_id si c'est une réponse
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Insérer le commentaire
    $insert_stmt = $pdo->prepare("
        INSERT INTO journal_comments (journal_id, user_code, author_user_code, content, status, parent_id) 
        VALUES (?, ?, ?, ?, 'approved', ?)
    ");
    $insert_stmt->execute([$journal_id, $user_code, $author_user_code, $content, $parent_id]);
    
    // Mettre à jour le compteur de commentaires
    $update_stmt = $pdo->prepare("
        UPDATE journals 
        SET comments_count = (SELECT COUNT(*) FROM journal_comments WHERE journal_id = ? AND status = 'approved')
        WHERE id = ?
    ");
    $update_stmt->execute([$journal_id, $journal_id]);
    
    // Redirection avec message de succès
    header("Location: journal_view.php?id=" . $journal_id . "&success=comment_added");
    exit;
    
} catch (PDOException $e) {
    error_log("Erreur ajout commentaire : " . $e->getMessage());
    
    // Si la table n'existe pas encore (migration 013 pas exécutée)
    if (strpos($e->getMessage(), 'journal_comments') !== false ||
        strpos($e->getMessage(), "doesn't exist") !== false) {
        header("Location: journal_view.php?id=" . $journal_id . "&error=comments_not_available");
    } else {
        header("Location: journal_view.php?id=" . $journal_id . "&error=system_error");
    }
    exit;
}
