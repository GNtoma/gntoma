<?php
require_once 'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    echo '<p class="text-gray-400 text-xs">Connectez-vous pour voir l\'historique</p>';
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
    echo '<p class="text-gray-400 text-xs">Aucune prolongation récente</p>';
    exit;
}

echo '<div class="space-y-2">';
foreach ($history as $item) {
    $date = new DateTime($item['created_at']);
    $date_fr = $date->format('d/m');
    
    echo '<div class="flex items-center justify-between text-xs bg-gray-50 rounded-lg px-3 py-2">';
    echo '<div class="flex items-center space-x-2">';
    echo '<span class="text-gray-500">' . $date_fr . '</span>';
    echo '<span class="text-gray-700">';
    if ($item['sender_code'] === $item['recipient_code']) {
        echo 'Vous avez prolongé';
    } else {
        echo 'Prolongé pour <strong>' . htmlspecialchars($item['display_name']) . '</strong>';
    }
    echo '</span>';
    echo '</div>';
    echo '<span class="text-green-600 font-bold">+' . $item['days_added'] . 'j</span>';
    echo '</div>';
}
echo '</div>';
