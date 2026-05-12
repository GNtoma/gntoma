<?php
declare(strict_types=1);

session_start();
require_once 'config.php';
require_once __DIR__ . '/gntoma_email_mask.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$code = strtoupper(trim((string) ($_POST['code'] ?? '')));
$maskedEmailFromPost = trim((string) ($_POST['masked_email'] ?? ''));
$otpInput = trim((string) ($_POST['otp'] ?? ''));
$newPassword = (string) ($_POST['new_password'] ?? '');

if ($code === '' || $otpInput === '' || $newPassword === '') {
    header('Location: ../index.php?step=reset&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmailFromPost) . '&error=' . urlencode('Tous les champs sont obligatoires.'));
    exit;
}

if (!preg_match('/^[A-Z]\d+$/', $code)) {
    header('Location: ../index.php?step=forgot&error=' . urlencode('Code utilisateur invalide.'));
    exit;
}

if (!preg_match('/^\d{6}$/', $otpInput)) {
    header('Location: ../index.php?step=reset&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmailFromPost) . '&error=' . urlencode('Le code OTP doit contenir 6 chiffres.'));
    exit;
}

if (strlen($newPassword) < 6) {
    header('Location: ../index.php?step=reset&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmailFromPost) . '&error=' . urlencode('Le nouveau mot de passe doit faire au moins 6 caractères.'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, email, otp_code, otp_expires_at FROM users WHERE UPPER(user_code) = ? LIMIT 1');
    $stmt->execute([$code]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../index.php?step=forgot&error=' . urlencode('Utilisateur introuvable.'));
        exit;
    }

    $maskedEmail = gntoma_mask_email((string) $user['email']);

    if ((string) $user['otp_code'] !== $otpInput) {
        header('Location: ../index.php?step=reset&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmail) . '&error=' . urlencode('Code OTP incorrect.'));
        exit;
    }

    if (empty($user['otp_expires_at'])) {
        header('Location: ../index.php?step=forgot&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmail) . '&error=' . urlencode('Aucun code OTP actif pour ce compte.'));
        exit;
    }

    $now = new DateTime();
    $expiry = new DateTime((string) $user['otp_expires_at']);

    if ($now > $expiry) {
        header('Location: ../index.php?step=forgot&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmail) . '&error=' . urlencode('Ce code OTP a expiré.'));
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateStmt = $pdo->prepare('UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?');
    $updateStmt->execute([$hashedPassword, $user['id']]);

    header('Location: ../index.php?step=login&success=password_reset');
    exit;
} catch (Throwable $e) {
    error_log('Erreur reset GNTOMA : ' . $e->getMessage());
    header('Location: ../index.php?step=reset&code=' . urlencode($code) . '&masked_email=' . urlencode($maskedEmailFromPost) . '&error=' . urlencode('Une erreur système est survenue.'));
    exit;
}
