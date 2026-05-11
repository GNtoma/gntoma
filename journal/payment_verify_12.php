<?php
require_once 'config.php';
require_once 'payment_session_handler.php';

// === LOG SIMPLE ===
function payLog(string $msg): void {
    $line = date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL;
    file_put_contents(__DIR__ . '/payment_log.txt', $line, FILE_APPEND | LOCK_EX);
}

payLog('--- START --- GET=' . json_encode($_GET));

$session_handler = new PaymentSessionHandler();

// 1. Récupérer la session : d'abord par sid, sinon par ref
$payment_session_id = $_GET['sid'] ?? '';
$reference          = $_GET['ref']  ?? '';

$payment_data = null;

if (!empty($payment_session_id)) {
    $payment_data = $session_handler->getSessionData($payment_session_id);
    payLog('Lookup by sid=' . $payment_session_id . ' => ' . ($payment_data ? 'FOUND' : 'NOT FOUND'));
}

if (!$payment_data && !empty($reference)) {
    $payment_data = $session_handler->getSessionByReference($reference);
    payLog('Fallback by ref=' . $reference . ' => ' . ($payment_data ? 'FOUND' : 'NOT FOUND'));
}

if (!$payment_data) {
    payLog('ERROR: No session found. Redirect payment_failed');
    header("Location: dashboard_6.php?error=payment_failed");
    exit;
}

// 2. Si déjà traité, rediriger directement
if ($payment_data['status'] === 'success') {
    payLog('Already processed. Redirect success.');
    header("Location: dashboard_6.php?success=payment_success");
    exit;
}

// 3. Données
$sender      = $payment_data['user_code'];
$target_user = $_GET['user'] ?? $sender;
$days        = (int)$payment_data['days_to_add'];
$amount      = (float)$payment_data['amount'];
$ref         = $payment_data['reference'] ?? $reference;

payLog("Data: sender=$sender target=$target_user days=$days amount=$amount ref=$ref");

// 4. Prolonger l'abonnement (étape critique - toujours essayer)
try {
    $stmt = $pdo->prepare("SELECT sub_expires_at FROM users WHERE user_code = ?");
    $stmt->execute([$target_user]);
    $current = $stmt->fetchColumn();

    if ($current) {
        $base = new DateTime(max(date('Y-m-d H:i:s'), $current));
        $base->modify("+$days days");
        $new_expiry = $base->format('Y-m-d H:i:s');

        $upd = $pdo->prepare("UPDATE users SET sub_status = 'active', sub_expires_at = ?, access_request_credits = access_request_credits + 100 WHERE user_code = ?");
        $upd->execute([$new_expiry, $target_user]);
        payLog("UPDATED user=$target_user expiry=$new_expiry credits_increased_by_100");
    } else {
        payLog("WARNING: user=$target_user not found or no sub_expires_at");
    }
} catch (Throwable $e) {
    payLog('ERROR update user: ' . $e->getMessage());
}

// 5. Historique (non critique)
try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE user_code = ?");
    $stmt->execute([$target_user]);
    $name = $stmt->fetchColumn() ?: 'Inconnu';

    $ins = $pdo->prepare("
        INSERT INTO payment_history (sender_code, recipient_code, recipient_name, days_added, amount_paid, reference, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([$sender, $target_user, $name, $days, $amount, $ref]);
    payLog('History inserted');
} catch (Throwable $e) {
    payLog('ERROR history: ' . $e->getMessage());
}

// 6. Confirmer la session (non critique)
try {
    $session_handler->confirmPayment($payment_session_id);
    payLog('Session confirmed');
} catch (Throwable $e) {
    payLog('ERROR confirm: ' . $e->getMessage());
}

// 7. Nettoyer session PHP si présente
unset($_SESSION['payment_session_id'], $_SESSION['payment_days'], $_SESSION['payment_target']);

payLog('--- END: redirect success ---');
header("Location: dashboard_6.php?success=payment_success");
exit();