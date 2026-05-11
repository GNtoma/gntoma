<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/auth_forgot_4.php
 * VERSION : 4
 * DESCRIPTION : Endpoint de génération et d'envoi du code OTP (Beauté & Sécurité).
 */

session_start();
require_once 'config.php';

// Sécurité : On n'accepte que le POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$email = trim($_POST['email'] ?? '');

// Validation de l'email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../index.php?step=forgot&error=" . urlencode("Adresse email invalide."));
    exit;
}

// Fonction d'envoi du mail OTP (Design iOS Premium)
function sendOTPEmail(string $to, string $name, string $otp): bool {
    try {
        $subject = '=?UTF-8?B?' . base64_encode('🔒 GNTOMA - Votre code de réinitialisation') . '?=';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: GNTOMA Sécurité <hello@gntoma.com>\r\n";
        $headers .= "Reply-To: hello@gntoma.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1D1D1F; background: #F5F5F7; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #eaeaea;">
            <div style="background: linear-gradient(135deg, #1D1D1F, #434343); color: white; padding: 40px 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px;">Demande de réinitialisation</h1>
            </div>
            <div style="padding: 40px 30px;">
                <p>Bonjour <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
                <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte GNTOMA.</p>
                <p>Voici votre code de vérification à 6 chiffres. <strong>Il expirera dans 15 minutes.</strong></p>
                
                <div style="background: #F5F5F7; padding: 30px; margin: 30px 0; border-radius: 12px; text-align: center; border: 1px dashed #d1d1d6;">
                    <p style="margin:0; color:#86868b; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing: 1px;">CODE OTP :</p>
                    <p style="margin:10px 0 0 0; color:#1D1D1F; font-size:36px; font-family:monospace; font-weight:900; letter-spacing: 5px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>
                </div>
                
                <p style="font-size: 13px; color: #86868b;">Si vous n\'avez pas fait cette demande, veuillez ignorer cet email en toute sécurité. Votre compte reste protégé.</p>
            </div>
        </div></body></html>';

        return mail($to, $subject, $body, $headers);
    } catch (Throwable $e) {
        return false;
    }
}

try {
    // 1. Chercher si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Générer un code OTP sécurisé (6 chiffres)
        $otp = sprintf("%06d", random_int(0, 999999));
        
        // 3. Définir l'expiration (15 minutes)
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // 4. Mettre à jour la base de données
        $update_stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
        $update_stmt->execute([$otp, $expires_at, $user['id']]);

        // 5. Envoyer l'email
        sendOTPEmail($email, $user['name'], $otp);
    }

    // Sécurité anti-énumération (on redirige avec le message de succès même si l'email n'existe pas, 
    // pour que les hackers ne puissent pas deviner quels emails sont inscrits).
    header("Location: ../index.php?step=reset&email=" . urlencode($email) . "&success=otp_sent");
    exit;

} catch (Throwable $e) {
    error_log("Erreur OTP GNTOMA : " . $e->getMessage());
    header("Location: ../index.php?step=forgot&error=" . urlencode("Une erreur système est survenue."));
    exit;
}