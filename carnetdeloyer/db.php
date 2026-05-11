<?php
declare(strict_types=1);

$host = getenv('CARNETLOYER_DB_HOST') ?: 'localhost';
$dbname = getenv('CARNETLOYER_DB_NAME') ?: 'sc3mwse0880_carnet_de_loyer';
$username = getenv('CARNETLOYER_DB_USER') ?: 'sc3mwse0880_carnet_de_loyer';
$password = getenv('CARNETLOYER_DB_PASSWORD') ?: 'sc3mwse0880_carnet_de_loyer';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    error_log('CarnetLoyer DB error : ' . $e->getMessage() . ' | Host=' . $host . ' DB=' . $dbname);
    echo 'Une erreur de connexion à la base de données est survenue. (' . htmlspecialchars($e->getMessage()) . ')';
    exit;
}
