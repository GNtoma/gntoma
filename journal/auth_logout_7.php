<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/auth_logout_7.php
 * VERSION : 7
 * DESCRIPTION : Destruction totale et sécurisée de la session utilisateur.
 */

session_start();

// 1. Vider toutes les variables de session
$_SESSION = array();

// 2. Détruire le cookie de session du navigateur (Sécurité optimale)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Détruire la session côté serveur
session_destroy();

// 4. Redirection fluide vers la Landing Page (Accueil)
header("Location: ../index.php");
exit;