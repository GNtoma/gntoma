<?php

declare(strict_types=1);

/**
 * Supprime les messages dont expires_at est depasse, les notifications liees,
 * et tente de retirer les fichiers joints sur disque.
 *
 * Usage:
 * - CLI: php cron_purge_expired_messages.php (depuis le dossier journal/)
 * - HTTP (tache planifiee cPanel, etc.): definir GNTOMA_CRON_SECRET sur le serveur,
 *   puis GET journal/cron_purge_expired_messages.php?key=VOTRE_SECRET
 */

if (PHP_SAPI !== 'cli') {
    $secret = (string) (getenv('GNTOMA_CRON_SECRET') ?: '');
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    if ($secret === '' || !hash_equals($secret, $key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
}

define('GNTOMA_CRON_LIGHT', true);
require_once __DIR__ . '/config.php';

$messagesRoot = realpath(__DIR__ . '/..');
if ($messagesRoot === false) {
    error_log('GNTOMA cron_purge_expired_messages: impossible de resoudre la racine projet.');
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Impossible de resoudre la racine projet.\n");
    } else {
        http_response_code(500);
        echo "Configuration invalide\n";
    }
    exit(1);
}

$batchLimit = 500;
$maxBatches = 50;
$totalDeleted = 0;

for ($batch = 0; $batch < $maxBatches; $batch++) {
    $pdo->beginTransaction();
    try {
        $sel = $pdo->prepare('
            SELECT id, attachment_path, has_attachment
            FROM messages
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
            LIMIT :lim
        ');
        $sel->bindValue(':lim', $batchLimit, PDO::PARAM_INT);
        $sel->execute();
        $rows = $sel->fetchAll();

        if ($rows === []) {
            $pdo->commit();
            break;
        }

        $ids = array_map('intval', array_column($rows, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pdo->prepare("DELETE FROM message_notifications WHERE message_id IN ($placeholders)")->execute($ids);

        foreach ($rows as $row) {
            if ((int) ($row['has_attachment'] ?? 0) !== 1 || empty($row['attachment_path'])) {
                continue;
            }
            $rel = str_replace(["\0", '\\'], ['', '/'], (string) $row['attachment_path']);
            if (strpos($rel, '..') !== false) {
                continue;
            }
            $abs = $messagesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)")->execute($ids);
        $pdo->commit();
        $totalDeleted += count($ids);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('GNTOMA cron_purge_expired_messages: ' . $e->getMessage());
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Erreur: ' . $e->getMessage() . "\n");
        } else {
            http_response_code(500);
            echo "Erreur serveur\n";
        }
        exit(1);
    }
}

echo 'GNTOMA purge messages: ' . $totalDeleted . " message(s) supprime(s).\n";
