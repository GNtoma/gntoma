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

require_once __DIR__ . '/lib/GntomaGeonamesService.php';
require_once __DIR__ . '/includes/gntoma_location_helpers.php';

if (!function_exists('gntoma_normalize_user_code')) {
    /**
     * Code utilisateur GNTOMA : comparaisons et requêtes en majuscules (aligné sur la messagerie).
     */
    function gntoma_normalize_user_code(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $t = strtoupper(trim($code));

        return $t === '' ? null : $t;
    }
}

if (!function_exists('gntoma_resolve_logged_in_user_code')) {
    /**
     * Code utilisateur connecté pour la messagerie et le journal.
     * La session GNTOMA enregistre le code métier dans `user_id` (historique) ; on accepte aussi `user_code`.
     * Retourne le `user_code` tel qu’en base (normalisé en majuscules) pour coller aux lignes `message_threads` / `message_credits`.
     */
    function gntoma_resolve_logged_in_user_code(PDO $pdo): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $raw = $_SESSION['user_id'] ?? $_SESSION['user_code'] ?? null;
        if ($raw === null) {
            return null;
        }
        $asString = trim((string) $raw);
        if ($asString === '') {
            return null;
        }
        $normalized = gntoma_normalize_user_code($asString);
        if ($normalized === null || $normalized === '') {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT user_code FROM users WHERE UPPER(TRIM(user_code)) = ? LIMIT 1');
            $stmt->execute([$normalized]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && isset($row['user_code'])) {
                $fromDb = gntoma_normalize_user_code((string) $row['user_code']);

                return $fromDb ?? $normalized;
            }
        } catch (Throwable $e) {
            error_log('gntoma_resolve_logged_in_user_code: ' . $e->getMessage());
        }

        return $normalized;
    }
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

if (!function_exists('gntoma_journal_reader_has_access')) {
    /**
     * Le lecteur peut lire un journal payant s'il figure dans journal_readers
     * ou si au moins une demande d'accès a le statut « approved » (pas seule la dernière demande).
     */
    function gntoma_journal_reader_has_access(PDO $pdo, int $journalId, string $userCode): bool
    {
        $code = gntoma_normalize_user_code($userCode);
        if ($code === null || $journalId < 1) {
            return false;
        }

        try {
            $jr = $pdo->prepare('
                SELECT 1 FROM journal_readers
                WHERE journal_id = ? AND UPPER(TRIM(user_code)) = ?
                LIMIT 1
            ');
            $jr->execute([$journalId, $code]);
            if ($jr->fetch()) {
                return true;
            }

            $ar = $pdo->prepare("
                SELECT 1 FROM access_requests
                WHERE journal_id = ? AND UPPER(TRIM(requester_user_code)) = ?
                  AND LOWER(TRIM(status)) = 'approved'
                LIMIT 1
            ");
            $ar->execute([$journalId, $code]);

            return (bool) $ar->fetch();
        } catch (Throwable $e) {
            error_log('gntoma_journal_reader_has_access: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('gntoma_journal_ensure_reader_row')) {
    /**
     * Répare journal_readers lorsqu'une approbation existe mais la ligne lecteur manque (anciennes données).
     */
    function gntoma_journal_ensure_reader_row(PDO $pdo, int $journalId, string $userCode): void
    {
        $code = gntoma_normalize_user_code($userCode);
        if ($code === null || $journalId < 1) {
            return;
        }

        try {
            $exists = $pdo->prepare('
                SELECT 1 FROM journal_readers
                WHERE journal_id = ? AND UPPER(TRIM(user_code)) = ?
                LIMIT 1
            ');
            $exists->execute([$journalId, $code]);
            if ($exists->fetch()) {
                return;
            }

            $approved = $pdo->prepare("
                SELECT 1 FROM access_requests
                WHERE journal_id = ? AND UPPER(TRIM(requester_user_code)) = ?
                  AND LOWER(TRIM(status)) = 'approved'
                LIMIT 1
            ");
            $approved->execute([$journalId, $code]);
            if (!$approved->fetch()) {
                return;
            }

            $pdo->prepare('
                INSERT INTO journal_readers (journal_id, user_code, access_count)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE last_access_at = CURRENT_TIMESTAMP
            ')->execute([$journalId, $code]);

            $pdo->prepare('
                UPDATE journals
                SET reader_count = (SELECT COUNT(*) FROM journal_readers WHERE journal_id = ?)
                WHERE id = ?
            ')->execute([$journalId, $journalId]);
        } catch (Throwable $e) {
            error_log('gntoma_journal_ensure_reader_row: ' . $e->getMessage());
        }
    }
}

if (!function_exists('gntoma_journal_access_request_summary')) {
    /**
     * @return array{has_access: bool, latest: ?array<string, mixed>}
     */
    function gntoma_journal_access_request_summary(PDO $pdo, int $journalId, string $userCode): array
    {
        $code = gntoma_normalize_user_code($userCode);
        $has_access = gntoma_journal_reader_has_access($pdo, $journalId, $userCode);
        $latest = null;

        if ($code !== null && $journalId > 0) {
            try {
                $stmt = $pdo->prepare("
                    SELECT request_number, status, created_at, response_message, approved_at
                    FROM access_requests
                    WHERE journal_id = ? AND UPPER(TRIM(requester_user_code)) = ?
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$journalId, $code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $latest = is_array($row) ? $row : null;
            } catch (Throwable $e) {
                error_log('gntoma_journal_access_request_summary: ' . $e->getMessage());
            }
        }

        return ['has_access' => $has_access, 'latest' => $latest];
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

if (!function_exists('gntoma_payment_journal_base_url')) {
    /**
     * URL de base du dossier journal/ pour les callbacks FlexPay (sans slash final).
     * GNTOMA_PUBLIC_JOURNAL_URL en priorité, sinon déduction depuis la requête HTTP.
     */
    function gntoma_payment_journal_base_url(): string
    {
        $env = trim((string) getenv('GNTOMA_PUBLIC_JOURNAL_URL'));
        if ($env !== '') {
            return rtrim($env, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'www.gntoma.com');

        return $scheme . '://' . $host . '/journal';
    }
}

if (!function_exists('gntoma_payment_csrf_token')) {
    /** Jeton CSRF formulaires paiement abonnement (session). */
    function gntoma_payment_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['gntoma_payment_csrf']) || !is_string($_SESSION['gntoma_payment_csrf'])) {
            $_SESSION['gntoma_payment_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['gntoma_payment_csrf'];
    }
}

if (!function_exists('gntoma_payment_validate_csrf')) {
    function gntoma_payment_validate_csrf(string $posted): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = $_SESSION['gntoma_payment_csrf'] ?? '';

        return is_string($expected) && $expected !== '' && hash_equals($expected, $posted);
    }
}

if (!function_exists('gntoma_payment_consume_csrf')) {
    function gntoma_payment_consume_csrf(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['gntoma_payment_csrf']);
        }
    }
}

if (!function_exists('gntoma_profile_csrf_token')) {
    /** Jeton CSRF formulaires édition profil / upload photo (session). */
    function gntoma_profile_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['gntoma_profile_csrf']) || !is_string($_SESSION['gntoma_profile_csrf'])) {
            $_SESSION['gntoma_profile_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['gntoma_profile_csrf'];
    }
}

if (!function_exists('gntoma_profile_validate_csrf')) {
    function gntoma_profile_validate_csrf(string $posted): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = $_SESSION['gntoma_profile_csrf'] ?? '';

        return is_string($expected) && $expected !== '' && hash_equals($expected, $posted);
    }
}

if (!function_exists('gntoma_access_request_csrf_token')) {
    /** Jeton CSRF pour les actions « suivre » / demande de suivi sur la page demande d'accès. */
    function gntoma_access_request_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['gntoma_access_request_csrf']) || !is_string($_SESSION['gntoma_access_request_csrf'])) {
            $_SESSION['gntoma_access_request_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['gntoma_access_request_csrf'];
    }
}

if (!function_exists('gntoma_access_request_validate_csrf')) {
    function gntoma_access_request_validate_csrf(string $posted): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = $_SESSION['gntoma_access_request_csrf'] ?? '';

        return is_string($expected) && $expected !== '' && hash_equals($expected, $posted);
    }
}

if (!function_exists('gntoma_unread_messages_in_inbox_count')) {
    /**
     * Messages non lus rattachés à une conversation de l'utilisateur uniquement
     * (exclut les anciennes lignes sans thread_id, jamais marquables comme lues dans le chat).
     */
    function gntoma_unread_messages_in_inbox_count(PDO $pdo, string $userCode): int
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS c
                FROM messages m
                INNER JOIN message_threads t ON t.id = m.thread_id
                WHERE UPPER(TRIM(m.recipient_user_code)) = ?
                  AND m.is_read = 0
                  AND (UPPER(TRIM(t.participant_1)) = ? OR UPPER(TRIM(t.participant_2)) = ?)
            ");
            $stmt->execute([$userCode, $userCode, $userCode]);

            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('gntoma_unread_messages_in_inbox_count: ' . $e->getMessage());

            return 0;
        }
    }
}
?>