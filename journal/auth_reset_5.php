<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/auth_reset_5.php
 * VERSION : 5
 * DESCRIPTION : Traitement de la réinitialisation du mot de passe (Validation OTP & Sécurité).
 */

session_start();
require_once 'config.php';

// Sécurité : Uniquement du POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp_input = trim($_POST['otp'] ?? '');
$new_password = $_POST['new_password'] ?? '';

// 1. Validations de base
if (empty($email) || empty($otp_input) || empty($new_password)) {
    header("Location: ../index.php?step=reset&email=" . urlencode($email) . "&error=" . urlencode("Tous les champs sont obligatoires."));
    exit;
}

if (strlen($new_password) < 6) {
    header("Location: ../index.php?step=reset&email=" . urlencode($email) . "&error=" . urlencode("Le nouveau mot de passe doit faire au moins 6 caractères."));
    exit;
}

try {
    // 2. Récupération de l'utilisateur et de ses infos OTP
    $stmt = $pdo->prepare("SELECT id, otp_code, otp_expires_at FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: ../index.php?step=forgot&error=" . urlencode("Utilisateur introuvable."));
        exit;
    }

    // 3. VÉRIFICATION DU CODE OTP
    // Comparaison stricte du code
    if ($user['otp_code'] !== $otp_input) {
        header("Location: ../index.php?step=reset&email=" . urlencode($email) . "&error=" . urlencode("Code OTP incorrect."));
        exit;
    }

    // 4. VÉRIFICATION DE L'EXPIRATION
    $now = new DateTime();
    $expiry = new DateTime($user['otp_expires_at']);

    if ($now > $expiry) {
        header("Location: ../index.php?step=forgot&error=" . urlencode("Ce code OTP a expiré (limite 15 min)."));
        exit;
    }

    // 5. MISE À JOUR DU MOT DE PASSE (Sécurité Maximale)
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // On change le mot de passe ET on vide les colonnes OTP pour qu'elles ne soient plus réutilisables
    $update_stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, 
            otp_code = NULL, 
            otp_expires_at = NULL 
        WHERE id = ?
    ");
    $update_stmt->execute([$hashed_password, $user['id']]);

    // 6. Succès Lucide
    header("Location: ../index.php?step=login&success=password_reset");
    exit;

} catch (Throwable $e) {
    error_log("Erreur Reset GNTOMA : " . $e->getMessage());
    header("Location: ../index.php?step=reset&email=" . urlencode($email) . "&error=" . urlencode("Une erreur système est survenue."));
    exit;
}