<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/messages_buy.php
 * DESCRIPTION : Achat de crédits messages (1000 pour 2 USD)
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_code'])) {
    header("Location: ../index.php");
    exit;
}

$user_code = gntoma_resolve_logged_in_user_code($pdo);
if ($user_code === null || $user_code === '') {
    header('Location: ../index.php');
    exit;
}

// Récupérer les crédits actuels
try {
    $credits_stmt = $pdo->prepare("SELECT * FROM message_credits WHERE UPPER(TRIM(user_code)) = ?");
    $credits_stmt->execute([$user_code]);
    $credits = $credits_stmt->fetch();
    
    if (!$credits) {
        $pdo->prepare("INSERT INTO message_credits (user_code, total_credits, remaining_credits) VALUES (?, 0, 0)")->execute([$user_code]);
        $credits = ['total_credits' => 0, 'used_credits' => 0, 'remaining_credits' => 0];
    }
    
    // Historique des achats
    $history_stmt = $pdo->prepare("
        SELECT * FROM message_credit_purchases 
        WHERE UPPER(TRIM(user_code)) = ? OR UPPER(TRIM(COALESCE(recipient_user_code, ''))) = ?
        ORDER BY purchased_at DESC
        LIMIT 10
    ");
    $history_stmt->execute([$user_code, $user_code]);
    $history = $history_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur crédits : " . $e->getMessage());
    $credits = ['total_credits' => 0, 'used_credits' => 0, 'remaining_credits' => 0];
    $history = [];
}

$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;

// Calculer le prix: 1000 messages = 2 USD
$MESSAGE_PACK_SIZE = 1000;
$MESSAGE_PACK_PRICE = 2;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('messages_buy.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#007AFF', dark: '#1D1D1F', surface: '#F5F5F7' }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #F0F4F8 0%, #F5F5F7 100%); }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); }
        .pack-card { transition: all 0.3s ease; }
        .pack-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .pack-card.selected { ring: 4px; ring-color: #007AFF; }
    </style>
</head>
<body class="min-h-screen pb-20">
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 px-4 py-4">
        <div class="max-w-2xl mx-auto flex items-center justify-between gap-2">
            <a href="messages_list.php" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-all">
                <svg class="h-5 w-5 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-lg font-bold text-dark flex-1 text-center"><?= htmlspecialchars(__('messages_buy.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 space-y-6">

        <?php if ($error === 'insufficient_credits'): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-red-700 text-center"><?= htmlspecialchars(__('messages_buy.err_insufficient'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <?php if ($success === 'purchased'): ?>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-sm font-bold text-green-700 text-center"><?= htmlspecialchars(__('messages_buy.success_purchased'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <!-- Solde actuel -->
        <div class="glass-panel rounded-[2rem] p-6 text-center">
            <p class="text-xs text-gray-500 font-bold uppercase mb-2"><?= htmlspecialchars(__('messages_buy.balance_label'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-5xl font-black text-dark mb-1"><?= number_format($credits['remaining_credits'] ?? 0) ?></p>
            <p class="text-sm text-gray-400"><?= htmlspecialchars(__('messages_buy.available'), ENT_QUOTES, 'UTF-8') ?></p>
            
            <div class="grid grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-100">
                <div>
                    <p class="text-lg font-bold text-dark"><?= number_format($credits['total_credits'] ?? 0) ?></p>
                    <p class="text-[10px] text-gray-400 uppercase"><?= htmlspecialchars(__('messages_buy.purchased'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-lg font-bold text-dark"><?= number_format($credits['used_credits'] ?? 0) ?></p>
                    <p class="text-[10px] text-gray-400 uppercase"><?= htmlspecialchars(__('messages_buy.used'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="text-lg font-bold text-primary"><?= number_format($credits['remaining_credits'] ?? 0) ?></p>
                    <p class="text-[10px] text-gray-400 uppercase"><?= htmlspecialchars(__('messages_buy.remaining'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>

        <!-- Packs disponibles -->
        <div>
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4"><?= htmlspecialchars(__('messages_buy.packs_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            
            <div class="space-y-4">
                <!-- Pack 1000 messages -->
                <div class="pack-card glass-panel rounded-[2rem] p-6 cursor-pointer border-2 border-primary bg-primary/5" onclick="selectPack(1000, 2)">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center">
                                <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-black text-dark">1000</p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars(__('messages_buy.msg_word'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-black text-primary">$2</p>
                            <p class="text-xs text-gray-400">USD</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars(__('messages_buy.pack_tagline'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <!-- Pack 500 messages -->
                <div class="pack-card glass-panel rounded-[2rem] p-6 cursor-pointer border-2 border-transparent hover:border-gray-200" onclick="selectPack(500, 1.2)">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center">
                                <svg class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-dark">500</p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars(__('messages_buy.msg_word'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-dark">$1.20</p>
                            <p class="text-xs text-gray-400">USD</p>
                        </div>
                    </div>
                </div>

                <!-- Pack 2500 messages -->
                <div class="pack-card glass-panel rounded-[2rem] p-6 cursor-pointer border-2 border-transparent hover:border-gray-200" onclick="selectPack(2500, 4)">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center">
                                <svg class="h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-dark">2500</p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars(__('messages_buy.msg_word'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-orange-600">$4</p>
                            <p class="text-xs text-gray-400">USD</p>
                        </div>
                    </div>
                    <span class="inline-block mt-2 bg-orange-100 text-orange-700 text-[10px] font-bold px-2 py-1 rounded-full"><?= htmlspecialchars(__('messages_buy.save_pct'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>

        <!-- Formulaire d'achat -->
        <form id="purchaseForm" action="messages_buy_process.php" method="POST" class="glass-panel rounded-[2rem] p-6">
            <input type="hidden" name="pack_size" id="packSize" value="1000">
            <input type="hidden" name="pack_price" id="packPrice" value="2">
            
            <!-- Option cadeau -->
            <div class="mb-5">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="is_gift" id="isGift" class="w-5 h-5 text-primary rounded focus:ring-primary" onchange="toggleGift()">
                    <span class="font-bold text-dark"><?= htmlspecialchars(__('messages_buy.gift_checkbox'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>
            
            <!-- Destinataire du cadeau -->
            <div id="giftRecipient" class="mb-5 hidden">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2"><?= htmlspecialchars(__('messages_buy.recipient_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" name="recipient_code" 
                       placeholder="<?= htmlspecialchars(__('messages_buy.recipient_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm uppercase focus:ring-2 focus:ring-primary outline-none">
                <p class="text-[10px] text-gray-400 mt-1"><?= htmlspecialchars(__('messages_buy.recipient_help'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="flex items-center justify-between mb-4 p-4 bg-gray-50 rounded-xl">
                <div>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars(__('messages_buy.selected_pack'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="font-bold text-dark"><span id="selectedSize">1000</span> <?= htmlspecialchars(__('messages_buy.msg_word'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <p class="text-2xl font-black text-primary">$<span id="selectedPrice">2</span></p>
            </div>

            <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-blue-600 transition-all flex items-center justify-center space-x-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a1 1 0 11-2 0 1 1 0 012 0z" />
                </svg>
                <span><?= htmlspecialchars(__('messages_buy.pay_button'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        </form>

        <!-- Historique -->
        <?php if (!empty($history)): ?>
        <div class="glass-panel rounded-[2rem] p-5">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4"><?= htmlspecialchars(__('messages_buy.history_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="space-y-3">
                <?php foreach ($history as $purchase): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                    <div>
                        <p class="font-bold text-dark text-sm">+<?= number_format($purchase['credits_amount']) ?> messages</p>
                        <p class="text-[10px] text-gray-400"><?= date('d/m/Y H:i', strtotime($purchase['purchased_at'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-dark">$<?= $purchase['price'] ?></p>
                        <?php if ($purchase['is_gift']): ?>
                        <span class="text-[10px] bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full"><?= htmlspecialchars(__('messages_buy.gift_badge'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script>
        function selectPack(size, price) {
            document.getElementById('packSize').value = size;
            document.getElementById('packPrice').value = price;
            document.getElementById('selectedSize').textContent = size;
            document.getElementById('selectedPrice').textContent = price;
            
            // Mettre à jour l'UI
            document.querySelectorAll('.pack-card').forEach(card => {
                card.classList.remove('selected', 'border-primary', 'bg-primary/5');
                card.classList.add('border-transparent');
            });
            event.currentTarget.classList.add('selected', 'border-primary', 'bg-primary/5');
            event.currentTarget.classList.remove('border-transparent');
        }
        
        function toggleGift() {
            const isGift = document.getElementById('isGift').checked;
            document.getElementById('giftRecipient').classList.toggle('hidden', !isGift);
        }
    </script>

</body>
</html>
