<?php
declare(strict_types=1);

/**
 * Proxy GeoNames searchJSON — JSON uniquement, username côté serveur.
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../i18n.php';
gntoma_init_locale_from_request();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$country = strtoupper(trim((string) ($_GET['country'] ?? '')));
$countryFilter = preg_match('/^[A-Z]{2}$/', $country) ? $country : null;

$service = gntoma_geonames_service();
if ($service === null) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'unavailable',
        'message' => __('geonames.error_unavailable'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $service->search($q, $countryFilter);

if (!$result['ok']) {
    $err = (string) ($result['error'] ?? 'api');
    $http = $err === 'rate_limit' ? 429 : 502;
    http_response_code($http);
    $msgKey = match ($err) {
        'rate_limit' => 'geonames.error_rate_limit',
        'network' => 'geonames.error_network',
        default => 'geonames.error_api',
    };
    echo json_encode([
        'ok' => false,
        'error' => $err,
        'message' => __($msgKey),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'results' => $result['results'] ?? [],
], JSON_UNESCAPED_UNICODE);
