<?php
session_start();
require_once 'config.php';
require_once 'payment_session_handler.php';

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Récupération des données
$me = $_SESSION['user_id'];
$target = trim(strtoupper((string)($_POST['target_user'] ?? '')));
$final_target = empty($target) ? $me : $target;

// Validation du code auteur cible (si cadeau)
if (!empty($target)) {
    $stmt = $pdo->prepare("SELECT user_code FROM users WHERE user_code = ? LIMIT 1");
    $stmt->execute([$target]);
    if (!$stmt->fetch()) {
        header("Location: dashboard_6.php?error=invalid_target");
        exit;
    }
}

// Prix et durée
$forfait = (int)($_POST['forfait'] ?? 2);
$amount = ($forfait === 3) ? 3.0 : 2.0;
$days = ($forfait === 3) ? 90 : 60;

// Référence unique pour FlexPay (générée une seule fois)
$reference = "GNT-" . $final_target . "-" . time() . "-" . uniqid();

// Sauvegarder la session en base de données
$session_handler = new PaymentSessionHandler();
$session_id = session_id() . '_' . time();

// Données de session à sauvegarder
$session_data = [
    'user_id' => $me,
    'user_name' => $_SESSION['user_name'] ?? '',
    'target_user' => $final_target,
    'amount' => $amount,
    'days' => $days
];

$session_handler->savePaymentSession($session_id, $me, $amount, $days, $reference, $session_data);

// Stocker l'ID de session pour récupération ultérieure
$_SESSION['payment_session_id'] = $session_id;
$_SESSION['payment_days'] = $days;
$_SESSION['payment_target'] = $final_target;

// FlexPay : journal/secrets.local.php ($GNTOMA_FLEXPAY_*), sinon variables d'environnement.
$GNTOMA_FLEXPAY_AUTHORIZATION = '';
$GNTOMA_FLEXPAY_MERCHANT = 'DGB';
$secretsLocal = __DIR__ . '/secrets.local.php';
if (is_readable($secretsLocal)) {
    require $secretsLocal;
}
$authorization = (string) (getenv('GNTOMA_FLEXPAY_AUTHORIZATION') ?: ($GNTOMA_FLEXPAY_AUTHORIZATION ?? ''));
$merchant = (string) (getenv('GNTOMA_FLEXPAY_MERCHANT') ?: ($GNTOMA_FLEXPAY_MERCHANT ?? 'DGB'));
if ($authorization === '') {
    error_log('GNTOMA payment_init: FlexPay authorization manquant (secrets.local.php ou GNTOMA_FLEXPAY_AUTHORIZATION).');
    header('Location: dashboard_6.php?error=payment_failed');
    exit;
}

// Préparation de la requête FlexPay
$body = json_encode([
    'authorization' => $authorization,
    'merchant' => $merchant,
    'reference' => $reference,
    'amount' => $amount,
    'currency' => 'USD',
    'description' => "Accès GNTOMA ($days j) pour $final_target",
    'callback_url' => "https://gntoma.com/journal/dashboard_6.php?error=payment_failed",
    'approve_url' => "https://gntoma.com/journal/payment_verify_12.php?ref=$reference&days=$days&user=$final_target&sid=$session_id",
    'cancel_url' => "https://gntoma.com/journal/dashboard_6.php",
    'decline_url' => "https://gntoma.com/journal/dashboard_6.php",
    'home_url' => "https://gntoma.com/journal/dashboard_6.php"
]);

// Appel API FlexPay
$curl = curl_init('https://cardpayment.flexpay.cd/api/rest/v1/vpos/ask');
curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = json_decode(curl_exec($curl), true);
curl_close($curl);

// Redirection vers FlexPay ou erreur
if (isset($response['code']) && $response['code'] == '0' && !empty($response['url'])) {
    header("Location: " . $response['url']);
} else {
    header("Location: dashboard_6.php?error=payment_failed");
}
exit();