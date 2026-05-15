<?php
declare(strict_types=1);

/**
 * Endpoint HTMX : suggestions de lieux via GeoNames (proxy serveur).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

$query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
$type = (string) ($_GET['type'] ?? 'city');

if (strlen($query) < GntomaGeonamesService::minQueryLength()) {
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$service = gntoma_geonames_service();
if ($service === null) {
    echo '<div class="p-2 text-red-500 text-sm">' . htmlspecialchars(__('geo_autocomplete.error'), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

$result = $service->search($query);
if (!$result['ok']) {
    echo '<div class="p-2 text-red-500 text-sm">' . htmlspecialchars(__('geo_autocomplete.error'), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

$results = $result['results'] ?? [];
if ($results === []) {
    echo '<div class="p-2 text-gray-500 text-sm">' . htmlspecialchars(__('geo_autocomplete.no_results'), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

foreach ($results as $place) {
    if (!is_array($place)) {
        continue;
    }
    $name = (string) ($place['name'] ?? '');
    if ($name === '') {
        continue;
    }
    $label = (string) ($place['label'] ?? $name);
    $safe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $jsName = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);
    $jsLabel = str_replace(["\\", "'"], ["\\\\", "\\'"], $label);
    echo '<div class="p-2 hover:bg-gray-100 cursor-pointer text-sm" role="option" '
        . 'onclick="selectGeo(\'' . $jsName . '\', \'' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '\', \'' . $jsLabel . '\')">'
        . $safe
        . '</div>';
}
