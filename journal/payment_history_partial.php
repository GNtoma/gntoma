<?php
declare(strict_types=1);

require_once 'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    echo '<p class="text-gray-400 text-xs">' . htmlspecialchars(__('payment_history_partial.login'), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

$me = $_SESSION['user_id'];

// Récupérer les 5 dernières prolongations des 30 derniers jours
$stmt = $pdo->prepare("
    SELECT 
        ph.recipient_name,
        ph.recipient_code,
        ph.days_added,
        ph.amount_paid,
        ph.created_at,
        CASE 
            WHEN ph.sender_code = ph.recipient_code THEN 'vous-même'
            ELSE ph.recipient_name
        END as display_name
    FROM payment_history ph
    WHERE ph.sender_code = ?
    AND ph.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY ph.created_at DESC
    LIMIT 5
");
$stmt->execute([$me]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($history)) {
    echo '<p class="text-gray-400 text-xs">' . htmlspecialchars(__('payment_history_partial.empty'), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

echo '<div class="space-y-2">';
foreach ($history as $item) {
    $date = new DateTime($item['created_at']);
    $date_fr = $date->format('d/m');

    echo '<div class="flex items-center justify-between text-xs bg-gray-50 rounded-lg px-3 py-2">';
    echo '<div class="flex items-center space-x-2">';
    echo '<span class="text-gray-500">' . htmlspecialchars($date_fr, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="text-gray-700">';
    if ($item['sender_code'] === $item['recipient_code']) {
        echo htmlspecialchars(__('payment_history_partial.you_extended'), ENT_QUOTES, 'UTF-8');
    } else {
        echo __('payment_history_partial.extended_for', ['name' => htmlspecialchars((string) $item['display_name'], ENT_QUOTES, 'UTF-8')]);
    }
    echo '</span>';
    echo '</div>';
    echo '<span class="text-green-600 font-bold">' . htmlspecialchars(__('payment_history_partial.days_suffix', ['days' => (string) $item['days_added']]), ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</div>';
}
echo '</div>';
