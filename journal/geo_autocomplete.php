<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/geo_autocomplete.php
 * DESCRIPTION : Endpoint HTMX pour autocomplete des villes et communes
 */

require_once 'config.php';

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'city'; // city ou commune

if (strlen($query) < 2) {
    exit;
}

try {
    if ($type === 'city') {
        $stmt = $pdo->prepare("
            SELECT name FROM geo_cities 
            WHERE name LIKE CONCAT('%', ?, '%') 
            ORDER BY population DESC, name ASC 
            LIMIT 8
        ");
        $stmt->execute([$query]);
    } elseif ($type === 'commune') {
        $stmt = $pdo->prepare("
            SELECT name FROM geo_communes 
            WHERE name LIKE CONCAT('%', ?, '%') 
            ORDER BY name ASC 
            LIMIT 8
        ");
        $stmt->execute([$query]);
    } else {
        exit;
    }

    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($results)) {
        echo '<div class="p-2 text-gray-500 text-sm">Aucun résultat</div>';
        exit;
    }
    
    foreach ($results as $name) {
        ?>
        <div class="p-2 hover:bg-gray-100 cursor-pointer text-sm" 
             onclick="selectGeo('<?= htmlspecialchars($name) ?>', '<?= $type ?>')">
            <?= htmlspecialchars($name) ?>
        </div>
        <?php
    }
} catch (PDOException $e) {
    error_log('Erreur geo_autocomplete : ' . $e->getMessage());
    echo '<div class="p-2 text-red-500 text-sm">Erreur de recherche</div>';
}
