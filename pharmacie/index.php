<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = ph_current_user();
if (!$user) {
    ph_redirect('auth_login.php');
}

// Récupérer les statistiques
try {
    $stats = [
        'products' => $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'low_stock' => $pdo->query('SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level')->fetchColumn(),
        'sales_today' => $pdo->query('SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'revenue_today' => $pdo->query('SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
    ];
    
    $lowStockProducts = $pdo->query('
        SELECT id, name, stock_quantity, min_stock_level 
        FROM products 
        WHERE stock_quantity <= min_stock_level 
        ORDER BY stock_quantity ASC 
        LIMIT 5
    ')->fetchAll();
    
    $recentSales = $pdo->query('
        SELECT s.id, s.final_amount, s.created_at, 
               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
        FROM sales s 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ')->fetchAll();
} catch (Throwable $e) {
    error_log('Erreur dashboard pharmacie : ' . $e->getMessage());
    $stats = ['products' => 0, 'low_stock' => 0, 'sales_today' => 0, 'revenue_today' => 0];
    $lowStockProducts = [];
    $recentSales = [];
}

ph_render_shell_start('Tableau de bord', $user);
?>
<div class="grid gap-6 lg:grid-cols-4">
    <!-- Stats Cards -->
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Produits</p>
                <p class="text-3xl font-black text-dark"><?= $stats['products'] ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Stock faible</p>
                <p class="text-3xl font-black text-orange-600"><?= $stats['low_stock'] ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Ventes aujourd\'hui</p>
                <p class="text-3xl font-black text-dark"><?= $stats['sales_today'] ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Revenu aujourd\'hui</p>
                <p class="text-3xl font-black text-primary"><?= number_format($stats['revenue_today'], 2) ?> $</p>
            </div>
            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<?php if (!empty($lowStockProducts)): ?>
<div class="mt-6 bg-orange-50 border border-orange-200 rounded-2xl p-6">
    <h3 class="text-lg font-bold text-orange-800 mb-4 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        Stock faible
    </h3>
    <div class="space-y-2">
        <?php foreach ($lowStockProducts as $product): ?>
        <div class="bg-white rounded-xl p-3 flex justify-between items-center">
            <span class="font-medium text-dark"><?= htmlspecialchars($product['name']) ?></span>
            <span class="text-sm font-bold text-orange-600"><?= $product['stock_quantity'] ?> / <?= $product['min_stock_level'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Sales -->
<div class="mt-6 bg-white rounded-2xl p-6 shadow-soft">
    <h3 class="text-lg font-bold text-dark mb-4">Ventes récentes</h3>
    <?php if (!empty($recentSales)): ?>
    <div class="space-y-3">
        <?php foreach ($recentSales as $sale): ?>
        <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
            <div>
                <p class="font-medium text-dark">Vente #<?= $sale['id'] ?></p>
                <p class="text-sm text-gray-500"><?= $sale['items_count'] ?> article(s)</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-primary"><?= number_format($sale['final_amount'], 2) ?> $</p>
                <p class="text-xs text-gray-400"><?= date('H:i', strtotime($sale['created_at'])) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-gray-500 text-center py-4">Aucune vente récente</p>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="product_create.php" class="bg-primary text-white rounded-2xl p-4 text-center font-bold hover:bg-emerald-600 transition">
        + Nouveau produit
    </a>
    <a href="product_list.php" class="bg-white border border-gray-200 rounded-2xl p-4 text-center font-bold hover:bg-gray-50 transition">
        Liste des produits
    </a>
    <a href="sale_create.php" class="bg-primary text-white rounded-2xl p-4 text-center font-bold hover:bg-emerald-600 transition">
        + Nouvelle vente
    </a>
    <a href="sale_list.php" class="bg-white border border-gray-200 rounded-2xl p-4 text-center font-bold hover:bg-gray-50 transition">
        Historique des ventes
    </a>
</div>

<?php ph_render_shell_end();
