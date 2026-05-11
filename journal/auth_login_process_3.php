<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/auth_login_process_3.php
 * VERSION : 3.1 (Correction PDO Emulate Prepares)
 * DESCRIPTION : Endpoint de traitement de la connexion.
 */

session_start();
require_once 'config.php';

// Sécurité : on n'accepte que le POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$login_input = trim($_POST['login'] ?? '');
$password_input = $_POST['password'] ?? '';

// 1. Vérification des champs vides
if (empty($login_input) || empty($password_input)) {
    header("Location: ../index.php?step=login&error=" . urlencode("Veuillez remplir tous les champs."));
    exit;
}

try {
    // 2. CORRECTION ICI : Utilisation de marqueurs positionnels (?) 
    // pour être compatible avec PDO::ATTR_EMULATE_PREPARES => false
    $sql = "SELECT * FROM users WHERE email = ? OR user_code = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch();

    // 3. Vérification du mot de passe
    if ($user && password_verify($password_input, $user['password'])) {
        
        // 4. Vérification de la validité de l'abonnement/essai
        $now = new DateTime();
        $current_status = $user['sub_status'];

        try {
            $expiry = new DateTime(!empty($user['sub_expires_at']) ? (string) $user['sub_expires_at'] : '+48 hours');
        } catch (Throwable $e) {
            error_log("Erreur date abonnement GNTOMA login : " . $e->getMessage());
            $expiry = (new DateTime())->modify('+48 hours');
        }

        // Si la date est dépassée et que le statut n'est pas déjà 'expired', on le met à jour
        if ($now > $expiry && $current_status !== 'expired') {
            $current_status = 'expired';
            $upd_stmt = $pdo->prepare("UPDATE users SET sub_status = 'expired' WHERE id = ?");
            $upd_stmt->execute([$user['id']]);
        }

        // Si l'abonnement est expiré, rediriger vers la page d'achat
        if ($now > $expiry) {
            header("Location: subscription_expired.php");
            exit;
        }

        // 5. Régénération de l'ID de session (Prévention contre le vol de session)
        session_regenerate_id(true);

        // 6. Enregistrement des variables de session
        $_SESSION['user_id'] = $user['user_code'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['sub_status'] = $current_status;

        // 7. Redirection vers le dashboard
        header("Location: dashboard_6.php");
        exit;

    } else {
        // Échec de l'authentification (Mauvais mot de passe ou compte introuvable)
        header("Location: ../index.php?step=login&error=" . urlencode("Identifiants incorrects."));
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur de connexion GNTOMA : " . $e->getMessage());
    header("Location: ../index.php?step=login&error=" . urlencode("Une erreur système est survenue."));
    exit;
}