<?php
declare(strict_types=1);

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=sc3mwse0880_pharmacie;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Erreur de connexion à la base de données pharmacie : ' . $e->getMessage());
    die('Une erreur de connexion à la base de données est survenue. Veuillez contacter l\'administrateur.');
}
