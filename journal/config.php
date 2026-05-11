<?php
/**
 * PROJET : GNTOMA
 * FICHIER : journal/config.php
 * DESCRIPTION : Connexion centralisée à la base de données PDO.
 */

// Accès BDD : priorité à journal/config.local.php (non versionné). Sinon variables d'environnement, sinon valeurs d'exemple.
$localDb = __DIR__ . '/config.local.php';
if (is_readable($localDb)) {
    require $localDb;
} elseif (getenv('GNTOMA_DB_HOST')) {
    $host = getenv('GNTOMA_DB_HOST') ?: 'localhost';
    $dbname = getenv('GNTOMA_DB_NAME') ?: '';
    $username = getenv('GNTOMA_DB_USER') ?: '';
    $password_db = getenv('GNTOMA_DB_PASSWORD') ?: '';
} else {
    $host = 'localhost';
    $dbname = '';
    $username = '';
    $password_db = '';
}

if (!isset($host, $dbname, $username, $password_db) || $dbname === '' || $username === '') {
    die('GNTOMA : configurez la base de données. Copiez journal/config.local.php.example vers journal/config.local.php ou définissez GNTOMA_DB_* .');
}

if (!function_exists('gntoma_pwa_markup')) {
    function gntoma_pwa_markup(): string
    {
        $filePath = dirname(__DIR__) . '/pwa_head.php';
        if (!is_file($filePath)) {
            return '';
        }

        ob_start();
        try {
            require $filePath;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Erreur rendu PWA GNTOMA : ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('gntoma_ui_head_markup')) {
    function gntoma_ui_head_markup(): string
    {
        $filePath = dirname(__DIR__) . '/ui_head.php';
        if (!is_file($filePath)) {
            return '';
        }

        ob_start();
        try {
            require $filePath;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Erreur rendu head UI GNTOMA : ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('gntoma_ui_background_markup')) {
    function gntoma_ui_background_markup(): string
    {
        $filePath = dirname(__DIR__) . '/ui_background.php';
        if (!is_file($filePath)) {
            return '';
        }

        ob_start();
        try {
            require $filePath;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Erreur rendu background UI GNTOMA : ' . $e->getMessage());
            return '';
        }
    }
}

// Scripts CLI / cron : definir GNTOMA_CRON_LIGHT a true avant require pour eviter ob_start PWA/UI.
if (!(defined('GNTOMA_CRON_LIGHT') && GNTOMA_CRON_LIGHT === true)) {
    if (!defined('GNTOMA_PWA_BUFFER_STARTED')) {
        define('GNTOMA_PWA_BUFFER_STARTED', true);

        // PRÉ-CALCUL : On génère le HTML avant de démarrer le buffer de sortie
        // car PHP interdit d'appeler ob_start() à l'intérieur d'un callback ob_start()
        $gntomaPreRenderedPwa = gntoma_pwa_markup();
        $gntomaPreRenderedUiHead = gntoma_ui_head_markup();
        $gntomaPreRenderedUiBg = gntoma_ui_background_markup();

        ob_start(static function (string $buffer) use ($gntomaPreRenderedPwa, $gntomaPreRenderedUiHead, $gntomaPreRenderedUiBg): string {
            try {
                if (stripos($buffer, '</head>') !== false) {
                    $headMarkup = '';

                    if (stripos($buffer, 'rel="manifest"') === false) {
                        $headMarkup .= $gntomaPreRenderedPwa . PHP_EOL;
                    }

                    if (stripos($buffer, 'gntoma-ui-ready') === false) {
                        $headMarkup .= $gntomaPreRenderedUiHead . PHP_EOL;
                    }

                    if ($headMarkup !== '') {
                        $buffer = (string) preg_replace('/<\/head>/i', $headMarkup . '</head>', $buffer, 1);
                    }
                }

                if (stripos($buffer, '<body') !== false && stripos($buffer, 'gntoma-ui-background') === false) {
                    $buffer = (string) preg_replace('/(<body[^>]*>)/i', '$1' . PHP_EOL . $gntomaPreRenderedUiBg, $buffer, 1);
                }
            } catch (Throwable $e) {
                error_log('Erreur callback ob_start GNTOMA : ' . $e->getMessage());
            }

            return $buffer;
        });
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false // Sécurité renforcée contre les injections SQL
    ]);
} catch (PDOException $e) {
    // En production, on ne montre pas l'erreur exacte pour des raisons de sécurité
    error_log("Erreur de connexion DB : " . $e->getMessage());
    die("Une erreur de connexion à la base de données est survenue.");
}

if (!function_exists('gntoma_generate_next_user_code')) {
    function gntoma_generate_next_user_code(PDO $pdo): string
    {
        $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(user_code, 2) AS UNSIGNED)), 0) AS max_code FROM users WHERE user_code REGEXP '^A[0-9]+$'");
        $maxCode = (int) ($stmt->fetchColumn() ?: 0);

        return 'A' . ($maxCode + 1);
    }
}

if (!function_exists('gntoma_ensure_message_credits')) {
    function gntoma_ensure_message_credits(PDO $pdo, string $userCode, int $defaultCredits = 100): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO message_credits (user_code, total_credits, used_credits, remaining_credits) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE user_code = user_code"
        );
        $stmt->execute([$userCode, $defaultCredits, $defaultCredits]);
    }
}

if (!function_exists('gntoma_users_has_profile_pic_column')) {
    function gntoma_users_has_profile_pic_column(PDO $pdo): bool
    {
        static $hasProfilePic = null;

        if ($hasProfilePic !== null) {
            return $hasProfilePic;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
            $hasProfilePic = (bool) $stmt->fetch();
        } catch (Throwable $e) {
            $hasProfilePic = false;
        }

        return $hasProfilePic;
    }
}

if (!function_exists('gntoma_users_profile_pic_expr')) {
    function gntoma_users_profile_pic_expr(PDO $pdo, string $tableAlias = 'users', string $asAlias = 'profile_pic'): string
    {
        if (gntoma_users_has_profile_pic_column($pdo)) {
            return $tableAlias . ".profile_pic AS " . $asAlias;
        }

        return "NULL AS " . $asAlias;
    }
}
?>