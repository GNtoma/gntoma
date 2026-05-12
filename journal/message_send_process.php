<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_send_process.php
 * DESCRIPTION : Traitement de l'envoi de message (navigateur classique ou HTMX depuis le chat)
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/message_chat_queries.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: messages_list.php');
    exit;
}

function gntoma_is_htmx_request(): bool
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

/** Réponse fragment HTML uniquement pour le fil de chat (sinon redirection classique). */
function gntoma_is_chat_htmx_response(bool $isHtmx, int $chatThreadId): bool
{
    return $isHtmx && $chatThreadId > 0;
}

function gntoma_chat_htmx_emit_thread(PDO $pdo, string $user_code, int $thread_id): void
{
    $messages = gntoma_fetch_chat_messages($pdo, $user_code, $thread_id);
    require __DIR__ . '/message_chat_bubbles.php';
}

function gntoma_chat_htmx_emit_feedback_error(string $htmlMessage): void
{
    echo '<div id="chat-send-feedback" hx-swap-oob="true" class="mb-3 rounded-xl bg-red-50 border border-red-100 px-4 py-2.5 text-center text-sm font-bold text-red-700">';
    echo $htmlMessage;
    echo '</div>';
}

function gntoma_chat_htmx_emit_feedback_clear(): void
{
    echo '<div id="chat-send-feedback" hx-swap-oob="true"></div>';
}

function gntoma_chat_htmx_emit_success(PDO $pdo, string $user_code, int $thread_id): void
{
    gntoma_chat_htmx_emit_thread($pdo, $user_code, $thread_id);
    gntoma_chat_htmx_emit_feedback_clear();
}

$user_code = strtoupper(trim((string) $_SESSION['user_id']));

try {
    gntoma_ensure_message_credits($pdo, $user_code, 100);
} catch (Throwable $e) {
    error_log('GNTOMA message_send_process ensure credits : ' . $e->getMessage());
}

$recipient_code = strtoupper(trim((string) ($_POST['recipient_code'] ?? '')));
$content = trim((string) ($_POST['content'] ?? ''));
$keywords = trim((string) ($_POST['keywords'] ?? ''));
$context = trim((string) ($_POST['context'] ?? ''));
$request_id = (int) ($_POST['request_id'] ?? 0);
$journal_id = (int) ($_POST['journal_id'] ?? 0);

$isHtmx = gntoma_is_htmx_request();
$chatThreadId = (int) ($_POST['thread_id'] ?? 0);
$isChatHtmx = gntoma_is_chat_htmx_response($isHtmx, $chatThreadId);

// Validation
if ($recipient_code === '' || $content === '') {
    if ($isChatHtmx) {
        gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
        gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.err_missing_content'), ENT_QUOTES, 'UTF-8'));
        exit;
    }
    header('Location: message_send.php?error=missing_data');
    exit;
}

if ($recipient_code === $user_code) {
    if ($isChatHtmx) {
        gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
        gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.err_self_message'), ENT_QUOTES, 'UTF-8'));
        exit;
    }
    header('Location: message_send.php?error=self_message');
    exit;
}

if (strlen($content) > 5000) {
    if ($isChatHtmx) {
        gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
        gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.err_content_too_long'), ENT_QUOTES, 'UTF-8'));
        exit;
    }
    header('Location: message_send.php?error=content_too_long');
    exit;
}

if ($context === 'access_request' && $request_id > 0) {
    $contextKeyword = 'ACCESS_REQUEST_D' . $request_id;
    if ($journal_id > 0) {
        $contextKeyword .= '_J' . $journal_id;
    }
    $keywords = trim($keywords . ' ' . $contextKeyword);
}

try {
    // Vérifier si le destinataire existe
    $recipient_stmt = $pdo->prepare('SELECT user_code FROM users WHERE user_code = ? LIMIT 1');
    $recipient_stmt->execute([$recipient_code]);
    if (!$recipient_stmt->fetch()) {
        if ($isChatHtmx) {
            gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
            gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.err_user_not_found'), ENT_QUOTES, 'UTF-8'));
            exit;
        }
        header('Location: message_send.php?error=user_not_found');
        exit;
    }

    // Vérifier si on est bloqué par le destinataire
    $blocked_stmt = $pdo->prepare('
        SELECT 1 FROM user_blocks 
        WHERE blocker_user_code = ? AND blocked_user_code = ?
        LIMIT 1
    ');
    $blocked_stmt->execute([$recipient_code, $user_code]);
    if ($blocked_stmt->fetch()) {
        if ($isChatHtmx) {
            gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
            gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.blocked_by_other'), ENT_QUOTES, 'UTF-8'));
            exit;
        }
        header('Location: message_send.php?to=' . $recipient_code . '&error=blocked');
        exit;
    }

    // Vérifier si on a bloqué le destinataire
    $block_stmt = $pdo->prepare('
        SELECT 1 FROM user_blocks
        WHERE blocker_user_code = ? AND blocked_user_code = ?
        LIMIT 1
    ');
    $block_stmt->execute([$user_code, $recipient_code]);
    if ($block_stmt->fetch()) {
        if ($isChatHtmx) {
            gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
            gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.you_blocked'), ENT_QUOTES, 'UTF-8'));
            exit;
        }
        header('Location: message_send.php?error=you_blocked');
        exit;
    }

    // Gratuit si négociation d'accès journal payant entre les deux
    $is_free = false;
    try {
        $free_check = $pdo->prepare("
            SELECT 1 FROM access_requests ar
            JOIN journals j ON ar.journal_id = j.id
            WHERE j.status = 'paid'
              AND ar.status IN ('pending','approved')
              AND (
                  (ar.requester_user_code = ? AND ar.author_user_code = ?)
               OR (ar.requester_user_code = ? AND ar.author_user_code = ?)
              )
            LIMIT 1
        ");
        $free_check->execute([$user_code, $recipient_code, $recipient_code, $user_code]);
        $is_free = (bool) $free_check->fetch();
    } catch (PDOException $e) {
        error_log('free_check ignoré (table access_requests peut-être absente) : ' . $e->getMessage());
        $is_free = false;
    }

    // Pièce jointe (hors transaction DB pour limiter la durée des verrous)
    $attachment_path = null;
    $has_attachment = false;
    $attachment_type = null;

    if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['attachment']['tmp_name']);

        if (in_array($file_type, $allowed_types, true)) {
            $upload_dir = '../uploads/messages/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $counter_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages WHERE sender_user_code = ? AND has_attachment = 1');
            $counter_stmt->execute([$user_code]);
            $count = $counter_stmt->fetch()['count'] + 1;
            $file_name = $user_code . '_image' . $count . '.' . $ext;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                $attachment_path = 'uploads/messages/' . $file_name;
                $has_attachment = true;
                $attachment_type = 'image';
            }
        }
    }

    $pdo->beginTransaction();

    try {
        // Crédits (verrouillage cohérent avec la suite dans la même transaction)
        if (!$is_free) {
            $credits_stmt = $pdo->prepare('SELECT remaining_credits FROM message_credits WHERE user_code = ? FOR UPDATE');
            $credits_stmt->execute([$user_code]);
            $credits = $credits_stmt->fetch();

            if (!$credits || (int) $credits['remaining_credits'] < 1) {
                $pdo->rollBack();
                if ($isChatHtmx) {
                    gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
                    $msg = htmlspecialchars(__('message_chat.err_insufficient_credits'), ENT_QUOTES, 'UTF-8')
                        . ' <a href="messages_buy.php" class="underline font-black">' . htmlspecialchars(__('message_chat.recharge'), ENT_QUOTES, 'UTF-8') . '</a>';
                    gntoma_chat_htmx_emit_feedback_error($msg);
                    exit;
                }
                header('Location: messages_buy.php?error=insufficient_credits');
                exit;
            }
        }

        // Thread ID fourni (depuis le chat) ou créer/trouver
        $thread_id = 0;
        $provided_thread_id = (int) ($_POST['thread_id'] ?? 0);

        if ($provided_thread_id > 0) {
            $verify_stmt = $pdo->prepare('
                SELECT id FROM message_threads 
                WHERE id = ? AND (participant_1 = ? OR participant_2 = ?)
                LIMIT 1
            ');
            $verify_stmt->execute([$provided_thread_id, $user_code, $user_code]);
            $thread = $verify_stmt->fetch();

            if ($thread) {
                $thread_id = (int) $thread['id'];
                $pdo->prepare('
                    UPDATE message_threads 
                    SET last_message_at = NOW(), last_message_preview = ?
                    WHERE id = ?
                ')->execute([substr($content, 0, 255), $thread_id]);
            } else {
                $thread_id = 0;
            }
        }

        if ($thread_id === 0) {
            $thread_stmt = $pdo->prepare('
                SELECT id FROM message_threads 
                WHERE (participant_1 = ? AND participant_2 = ?) 
                   OR (participant_1 = ? AND participant_2 = ?)
                LIMIT 1
            ');
            $thread_stmt->execute([$user_code, $recipient_code, $recipient_code, $user_code]);
            $thread = $thread_stmt->fetch();

            if ($thread) {
                $thread_id = (int) $thread['id'];
                $pdo->prepare('
                    UPDATE message_threads 
                    SET last_message_at = NOW(), last_message_preview = ?
                    WHERE id = ?
                ')->execute([substr($content, 0, 255), $thread_id]);
            } else {
                $new_thread_stmt = $pdo->prepare('
                    INSERT INTO message_threads (participant_1, participant_2, last_message_at, last_message_preview)
                    VALUES (?, ?, NOW(), ?)
                ');
                $new_thread_stmt->execute([$user_code, $recipient_code, substr($content, 0, 255)]);
                $thread_id = (int) $pdo->lastInsertId();
            }
        }

        $credits_to_consume = $is_free ? 0 : 1;
        $insert_stmt = $pdo->prepare('
            INSERT INTO messages (
                thread_id, sender_user_code, recipient_user_code,
                content, has_attachment, attachment_path, attachment_type, keywords, expires_at, credits_consumed
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 21 DAY), ?)
        ');
        $insert_stmt->execute([
            $thread_id, $user_code, $recipient_code,
            $content, $has_attachment ? 1 : 0, $attachment_path, $attachment_type, $keywords, $credits_to_consume,
        ]);
        $message_id = $pdo->lastInsertId();

        $notif_stmt = $pdo->prepare("
            INSERT INTO message_notifications (user_code, message_id, type)
            VALUES (?, ?, 'new_message')
        ");
        $notif_stmt->execute([$recipient_code, $message_id]);

        if (!$is_free) {
            $pdo->prepare('
                UPDATE message_credits
                SET used_credits = used_credits + 1, remaining_credits = remaining_credits - 1
                WHERE user_code = ?
            ')->execute([$user_code]);
        }

        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }

    if ($isChatHtmx) {
        gntoma_chat_htmx_emit_success($pdo, $user_code, $thread_id);
        exit;
    }

    header('Location: message_chat.php?thread=' . $thread_id);
    exit;
} catch (Throwable $e) {
    error_log('Erreur envoi message : ' . $e->getMessage());
    if ($isChatHtmx && $chatThreadId > 0) {
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $ignored) {
        }
        try {
            gntoma_chat_htmx_emit_thread($pdo, $user_code, $chatThreadId);
        } catch (Throwable $ignored) {
            http_response_code(500);
            echo '<p class="text-red-600 text-sm p-4">' . htmlspecialchars(__('message_chat.err_system'), ENT_QUOTES, 'UTF-8') . '</p>';
            exit;
        }
        gntoma_chat_htmx_emit_feedback_error(htmlspecialchars(__('message_chat.err_system'), ENT_QUOTES, 'UTF-8'));
        exit;
    }
    header('Location: message_send.php?error=system');
    exit;
}
