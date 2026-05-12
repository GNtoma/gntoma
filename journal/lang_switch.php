<?php

declare(strict_types=1);

/**
 * Change la langue (cookie + session) puis redirige vers une URL absolue depuis la racine du site.
 * Les liens UI préfèrent `?lang=` sur la page courante (voir gntoma_lang_switch_markup).
 * Usage legacy : journal/lang_switch.php?lang=en&return=index.php ou return=dashboard_6.php
 */

session_start();

require_once __DIR__ . '/i18n.php';

$lang = strtolower(trim((string) ($_GET['lang'] ?? '')));
gntoma_set_locale($lang);

$return = (string) ($_GET['return'] ?? 'dashboard_6.php');
$return = str_replace(["\0", '\\'], '', $return);

if ($return === '' || str_contains($return, '..')) {
    header('Location: /journal/dashboard_6.php');
    exit;
}

$pathPart = $return;
$queryPart = '';
if (str_contains($return, '?')) {
    [$pathPart, $queryPart] = explode('?', $return, 2);
}

$script = basename($pathPart);
if ($script === '' || str_contains($script, '/') || str_contains($script, ':')) {
    header('Location: /journal/dashboard_6.php');
    exit;
}

/** Pages servies à la racine du site (pas dans /journal/) */
$rootScripts = ['index.php', 'offline.html', 'conditions_utilisation.php'];

if (in_array($script, $rootScripts, true)) {
    $location = '/' . $script;
} else {
    $location = '/journal/' . $script;
}

if ($queryPart !== '') {
    $location .= '?' . $queryPart;
}

header('Location: ' . $location);
exit;
