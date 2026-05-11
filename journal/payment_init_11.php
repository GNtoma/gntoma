<?php

declare(strict_types=1);

session_start();
require_once 'config.php';
require_once 'payment_session_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_6.php?error=payment_init_failed');
    exit;
}

$postedCsrf = (string) ($_POST['csrf'] ?? '');
if (!gntoma_payment_validate_csrf($postedCsrf)) {
    error_log('GNTOMA payment_init: CSRF invalide ou session expirée.');
    header('Location: dashboard_6.php?error=payment_init_failed');
    exit;
}

$me = (string) $_SESSION['user_id'];
$target = trim(strtoupper((string) ($_POST['target_user'] ?? '')));
$final_target = $target === '' ? $me : $target;

if ($target !== '') {
    $stmt = $pdo->prepare('SELECT user_code FROM users WHERE user_code = ? LIMIT 1');
    $stmt->execute([$target]);
    if (!$stmt->fetch()) {
        header('Location: dashboard_6.php?error=invalid_target');
        exit;
    }
}

$forfait = (int) ($_POST['forfait'] ?? 2);
if ($forfait !== 2 && $forfait !== 3) {
    $forfait = 2;
}
$amount = $forfait === 3 ? 3.0 : 2.0;
$days = $forfait === 3 ? 90 : 60;

$reference = 'GNT-' . $final_target . '-' . time() . '-' . uniqid('', true);

$session_handler = new PaymentSessionHandler();
$session_id = session_id() . '_' . time();

$session_data = [
    'user_id' => $me,
    'user_name' => $_SESSION['user_name'] ?? '',
    'target_user' => $final_target,
    'amount' => $amount,
    'days' => $days,
];

$session_handler->savePaymentSession($session_id, $me, $amount, $days, $reference, $session_data);

$_SESSION['payment_session_id'] = $session_id;
$_SESSION['payment_days'] = $days;
$_SESSION['payment_target'] = $final_target;

$GNTOMA_FLEXPAY_AUTHORIZATION = '';
$GNTOMA_FLEXPAY_MERCHANT = 'DGB';
$secretsLocal = __DIR__ . '/secrets.local.php';
if (is_readable($secretsLocal)) {
    require $secretsLocal;
}
$authorization = (string) (getenv('GNTOMA_FLEXPAY_AUTHORIZATION') ?: ($GNTOMA_FLEXPAY_AUTHORIZATION ?? ''));
$merchant = (string) (getenv('GNTOMA_FLEXPAY_MERCHANT') ?: ($GNTOMA_FLEXPAY_MERCHANT ?? 'DGB'));
if ($authorization === '') {
    $authorization = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJcL2xvZ2luIiwicm9sZXMiOlsiTUVSQ0hBTlQiXSwiZXhwIjoxODM1ODcyOTYzLCJzdWIiOiIxNTkwYWRjNDJkMDBlMTQxZDBjODQ5ZDA3MjQ2NDkxMiJ9.wkSz-mu9d7uMSSZSGFJWSZDV2PmC99ckK496gQCxYuk";
}
if ($merchant === '') {
    $merchant = 'DGB';
}

$base = gntoma_payment_journal_base_url();
$approveUrl = $base . '/payment_verify_12.php?ref=' . rawurlencode($reference)
    . '&days=' . $days
    . '&user=' . rawurlencode($final_target)
    . '&sid=' . rawurlencode($session_id);

$body = json_encode([
    'authorization' => $authorization,
    'merchant' => $merchant,
    'reference' => $reference,
    'amount' => $amount,
    'currency' => 'USD',
    'description' => "Accès GNTOMA ({$days} j) pour {$final_target}",
    'callback_url' => $base . '/dashboard_6.php?error=payment_failed',
    'approve_url' => $approveUrl,
    'cancel_url' => $base . '/dashboard_6.php',
    'decline_url' => $base . '/dashboard_6.php',
    'home_url' => $base . '/dashboard_6.php',
]);
if ($body === false) {
    error_log('GNTOMA payment_init: json_encode a échoué.');
    header('Location: dashboard_6.php?error=payment_init_failed');
    exit;
}

$curl = curl_init('https://cardpayment.flexpay.cd/api/rest/v1/vpos/ask');
curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($curl, CURLOPT_TIMEOUT, 45);

$raw = curl_exec($curl);
$curlErr = curl_error($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($raw === false || $curlErr !== '') {
    error_log('GNTOMA payment_init cURL: ' . $curlErr);
    header('Location: dashboard_6.php?error=curl_error');
    exit;
}

$response = json_decode((string) $raw, true);
if (!is_array($response)) {
    error_log('GNTOMA payment_init JSON invalide body=' . substr((string) $raw, 0, 500));
    header('Location: dashboard_6.php?error=flexpay_error');
    exit;
}

if ($httpCode >= 400) {
    error_log('GNTOMA payment_init HTTP ' . $httpCode . ' body=' . substr((string) $raw, 0, 800));
    header('Location: dashboard_6.php?error=http_error');
    exit;
}

if (isset($response['code'], $response['url']) && (string) $response['code'] === '0' && $response['url'] !== '') {
    $payUrl = (string) $response['url'];
    if (!preg_match('#^https://[^/]*flexpay\\.cd/#i', $payUrl)) {
        error_log('GNTOMA payment_init: URL FlexPay inattendue: ' . substr($payUrl, 0, 120));
        header('Location: dashboard_6.php?error=flexpay_error');
        exit;
    }
    gntoma_payment_consume_csrf();
    header('Location: ' . $payUrl);
    exit;
}

error_log('GNTOMA payment_init FlexPay refus: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
header('Location: dashboard_6.php?error=flexpay_error');
exit;
