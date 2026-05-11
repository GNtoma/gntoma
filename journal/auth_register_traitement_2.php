<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/auth_register_traitement_2.php
 * VERSION : 2
 * DESCRIPTION : Traitement de l'inscription. Mail natif, création DB, redirection dashboard_6.php.
 */

session_start();
require_once 'config.php'; 

// Sécurisation des sorties pour l'email
function h(string $s): string { 
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); 
}

// Envoi d'email via la fonction native mail() pour o2switch
function sendWelcomeEmail(string $to, string $name, string $userCode): bool {
    try {
        $subject = '=?UTF-8?B?' . base64_encode('✅ Bienvenue sur GNTOMA - Votre compte est prêt !') . '?=';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: GNTOMA <hello@gntoma.com>\r\n";
        $headers .= "Reply-To: hello@gntoma.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: sans-serif; line-height: 1.6; color: #1D1D1F; background: #F5F5F7; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #eaeaea;">
            <div style="background: linear-gradient(135deg, #007AFF, #5AC8FA); color: white; padding: 40px 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">🎉 Bienvenue sur GNTOMA</h1>
            </div>
            <div style="padding: 40px 30px;">
                <p>Bonjour <strong>' . h($name) . '</strong>,</p>
                <p>Votre compte auteur a été créé. Votre essai gratuit de 48 heures est actif.</p>
                <div style="background: #F5F5F7; border-left: 4px solid #007AFF; padding: 24px; margin: 30px 0; border-radius: 8px;">
                    <p style="margin:0; color:#86868b; font-size:12px; font-weight:bold; text-transform:uppercase;">Votre Code Auteur unique :</p>
                    <p style="margin:5px 0 0 0; color:#007AFF; font-size:24px; font-family:monospace; font-weight:bold;">' . h($userCode) . '</p>
                </div>
                <p style="text-align: center; margin-top: 40px;">
                    <a href="https://gntoma.com" style="display: inline-block; background: #007AFF; color: white; padding: 16px 32px; text-decoration: none; border-radius: 12px; font-weight: bold;">Commencer à publier →</a>
                </p>
            </div>
        </div></body></html>';

        return mail($to, $subject, $body, $headers);
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password_raw = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($password_raw)) {
        header("Location: auth_register_1.php?error=empty_fields"); exit;
    }
    if ($password_raw !== $password_confirm) {
        header("Location: auth_register_1.php?error=password_mismatch"); exit;
    }
    if (strlen($password_raw) < 6) {
        header("Location: auth_register_1.php?error=password_too_short"); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: auth_register_1.php?error=invalid_email"); exit;
    }

    try {
        $pdo->beginTransaction();

        // Vérifier si l'email existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            header("Location: auth_register_1.php?error=email_exists"); exit;
        }

        $user_code = gntoma_generate_next_user_code($pdo);
        
        $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $sql = "INSERT INTO users (user_code, name, email, password, sub_status, sub_expires_at) VALUES (?, ?, ?, ?, 'trial', ?)";
        $pdo->prepare($sql)->execute([$user_code, $name, $email, $hashed_password, $expires_at]);
        $pdo->commit();

        try {
            gntoma_ensure_message_credits($pdo, $user_code, 100);
        } catch (Throwable $e) {
            error_log('Erreur crédits inscription GNTOMA : ' . $e->getMessage());
        }

        // Envoi email et session
        sendWelcomeEmail($email, $name, $user_code);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_code;
        $_SESSION['user_name'] = $name;
        $_SESSION['sub_status'] = 'trial';
        $_SESSION['just_registered'] = true;

        // Redirection vers le Fichier 6 (Dashboard)
        header("Location: dashboard_6.php");
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur inscription GNTOMA : " . $e->getMessage());
        header("Location: auth_register_1.php?error=system_error"); exit;
    }
}