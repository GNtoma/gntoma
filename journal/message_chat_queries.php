<?php
declare(strict_types=1);

/**
 * Requêtes partagées pour le fil de conversation (liste des messages d’un thread).
 */

function gntoma_fetch_chat_messages(PDO $pdo, string $user_code, int $thread_id): array
{
    $messages_stmt = $pdo->prepare("
        SELECT m.*,
            CASE WHEN m.sender_user_code = ? THEN 'me' ELSE 'other' END as sender_type
        FROM messages m
        WHERE m.thread_id = ?
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    $messages_stmt->execute([$user_code, $thread_id]);

    return $messages_stmt->fetchAll();
}
