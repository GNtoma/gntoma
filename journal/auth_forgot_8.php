<?php
declare(strict_types=1);

session_start();
require_once 'config.php';
require_once __DIR__ . '/gntoma_email_mask.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

function sendOtpEmail(string $to, string $name, string $otp): bool
{
    try {
        $subject = '=?UTF-8?B?' . base64_encode('🔒 GNTOMA - Votre code de réinitialisation') . '?=';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: GNTOMA Sécurité <hello@gntoma.com>\r\n";
        $headers .= "Reply-To: hello@gntoma.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1D1D1F; background: #F5F5F7; padding: 20px;">'
            . '<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #eaeaea;">'
            . '<div style="background: linear-gradient(135deg, #1D1D1F, #434343); color: white; padding: 40px 30px; text-align: center;">'
            . '<h1 style="margin: 0; font-size: 24px;">Demande de réinitialisation</h1>'
            . '</div>'
            . '<div style="padding: 40px 30px;">'
            . '<p>Bonjour <strong>' . $safeName . '</strong>,</p>'
            . '<p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte GNTOMA.</p>'
            . '<p>Voici votre code de vérification à 6 chiffres. <strong>Il expirera dans 15 minutes.</strong></p>'
            . '<div style="background: #F5F5F7; padding: 30px; margin: 30px 0; border-radius: 12px; text-align: center; border: 1px dashed #d1d1d6;">'
            . '<p style="margin:0; color:#86868b; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing: 1px;">CODE OTP :</p>'
            . '<p style="margin:10px 0 0 0; color:#1D1D1F; font-size:36px; font-family:monospace; font-weight:900; letter-spacing: 5px;">' . $safeOtp . '</p>'
            . '</div>'
            . '<p style="font-size: 13px; color: #86868b;">Si vous n\'avez pas fait cette demande, veuillez ignorer cet email en toute sécurité. Votre compte reste protégé.</p>'
            . '</div>'
            . '</div></body></html>';

        return mail($to, $subject, $body, $headers);
    } catch (Throwable $e) {
        error_log('Erreur email OTP GNTOMA : ' . $e->getMessage());
        return false;
    }
}

function renderLookupMessage(string $message, string $tone = 'neutral'): void
{
    $classes = 'bg-white/70 border border-white rounded-2xl px-4 py-3 text-sm font-semibold';

    if ($tone === 'error') {
        $classes = 'bg-red-50 border border-red-100 rounded-2xl px-4 py-3 text-sm text-red-600 font-semibold';
    }

    echo '<div class="' . $classes . '">' . $message . '</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $code = strtoupper(trim((string) ($_GET['code'] ?? '')));

    if ($code === '') {
        exit;
    }

    if (!preg_match('/^[A-Z]\d+$/', $code)) {
        renderLookupMessage(htmlspecialchars(__('landing.forgot_lookup_format_err', ['example' => 'A56']), ENT_QUOTES, 'UTF-8'), 'error');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE UPPER(user_code) = ? LIMIT 1');
        $stmt->execute([$code]);
        $user = $stmt->fetch();

        if (!$user || empty($user['email'])) {
            renderLookupMessage(htmlspecialchars(__('landing.forgot_lookup_no_email'), ENT_QUOTES, 'UTF-8'), 'error');
            exit;
        }

        $maskedEmail = htmlspecialchars(gntoma_mask_email((string) $user['email']), ENT_QUOTES, 'UTF-8');
        renderLookupMessage(htmlspecialchars(__('landing.email_linked'), ENT_QUOTES, 'UTF-8') . ' <span class="text-dark font-black">' . $maskedEmail . '</span>');
        exit;
    } catch (Throwable $e) {
        error_log('Erreur lookup forgot GNTOMA : ' . $e->getMessage());
        renderLookupMessage(htmlspecialchars(__('landing.forgot_lookup_search_err'), ENT_QUOTES, 'UTF-8'), 'error');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$code = strtoupper(trim((string) ($_POST['code'] ?? '')));

if ($code === '' || !preg_match('/^[A-Z]\d+$/', $code)) {
    header('Location: ../index.php?step=forgot&error=' . urlencode(__('landing.forgot_err_invalid_code')) . '&code=' . urlencode($code));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, email, user_code FROM users WHERE UPPER(user_code) = ? LIMIT 1');
    $stmt->execute([$code]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        header('Location: ../index.php?step=forgot&error=' . urlencode(__('landing.forgot_err_not_found')) . '&code=' . urlencode($code));
        exit;
    }

    $otp = sprintf('%06d', random_int(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $updateStmt = $pdo->prepare('UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?');
    $updateStmt->execute([$otp, $expiresAt, $user['id']]);

    if (!sendOtpEmail((string) $user['email'], (string) $user['name'], $otp)) {
        header('Location: ../index.php?step=forgot&error=' . urlencode(__('landing.forgot_err_mail_send')) . '&code=' . urlencode($code) . '&masked_email=' . urlencode(gntoma_mask_email((string) $user['email'])));
        exit;
    }

    header('Location: ../index.php?step=reset&code=' . urlencode((string) $user['user_code']) . '&masked_email=' . urlencode(gntoma_mask_email((string) $user['email'])) . '&success=otp_sent');
    exit;
} catch (Throwable $e) {
    error_log('Erreur forgot GNTOMA : ' . $e->getMessage());
    header('Location: ../index.php?step=forgot&error=' . urlencode(__('landing.forgot_err_system')) . '&code=' . urlencode($code));
    exit;
}
