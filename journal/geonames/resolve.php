<?php
declare(strict_types=1);

/**
 * Valide un geonameId (getJSON) — utilisé avant enregistrement profil.
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../i18n.php';
gntoma_init_locale_from_request();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$geonameId = (int) ($_GET['geoname_id'] ?? 0);
$validation = gntoma_location_from_request(['location_geoname_id' => $geonameId]);

if (!$validation['ok']) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $validation['error'] ?? 'invalid',
        'message' => __('geonames.error_invalid_selection'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'place' => $validation['place'],
], JSON_UNESCAPED_UNICODE);
