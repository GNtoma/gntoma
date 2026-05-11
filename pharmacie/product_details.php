<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = ph_current_user();
if (!$user) {
    ph_redirect('auth_login.php');
}

$productId = (int) ($_GET['id'] ?? 0);

try {
    $stmt = $pdo->prepare('
        SELECT p.*, c.name as category_name, s.name as supplier_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.id = ?
    ');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        ph_set_flash('error', 'Produit non trouvé.');
        ph_redirect('product_list.php');
    }

    // Récupérer les mouvements de stock récents
    $stmt = $pdo->prepare('
        SELECT * FROM stock_movements 
        WHERE product_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ');
    $stmt->execute([$productId]);
    $movements = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Erreur détails produit : ' . $e->getMessage());
    ph_set_flash('error', 'Erreur lors du chargement du produit.');
    ph_redirect('product_list.php');
}

ph_render_shell_start(htmlspecialchars($product['name']), $user);
?>
<div class="space-y-6">
    <!-- Détails du produit -->
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h2 class="text-lg font-black text-dark mb-4">Informations générales</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Nom</p>
                        <p class="font-bold text-dark"><?= htmlspecialchars($product['name']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Catégorie</p>
                        <p class="text-gray-700"><?= htmlspecialchars($product['category_name'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Description</p>
                        <p class="text-gray-700"><?= htmlspecialchars($product['description'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Code-barres</p>
                        <p class="text-gray-700"><?= htmlspecialchars($product['barcode'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Unité</p>
                        <p class="text-gray-700"><?= htmlspecialchars($product['unit'] ?? '-') ?></p>
                    </div>
                </div>
            </div>
            <div>
                <h2 class="text-lg font-black text-dark mb-4">Prix et stock</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Prix d\'achat</p>
                        <p class="text-gray-700"><?= number_format($product['purchase_price'], 2) ?> $</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Prix de vente</p>
                        <p class="text-2xl font-black text-primary"><?= number_format($product['selling_price'], 2) ?> $</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Stock actuel</p>
                        <p class="text-2xl font-black <?= $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-orange-600' : 'text-dark' ?>">
                            <?= $product['stock_quantity'] ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Stock minimum</p>
                        <p class="text-gray-700"><?= $product['min_stock_level'] ?></p>
                    </div>
                    <?php if ($product['expiry_date']): ?>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Date d\'expiration</p>
                        <p class="<?= $product['expiry_date'] < date('Y-m-d') ? 'text-red-600 font-bold' : 'text-gray-700' ?>">
                            <?= date('d/m/Y', strtotime($product['expiry_date'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($product['supplier_name']): ?>
        <div class="mt-6 pt-6 border-t border-gray-100">
            <p class="text-xs text-gray-500 uppercase tracking-wider font-bold">Fournisseur</p>
            <p class="text-gray-700"><?= htmlspecialchars($product['supplier_name']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Mouvements de stock -->
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <h3 class="text-lg font-black text-dark mb-4">Mouvements de stock récents</h3>
        <?php if (!empty($movements)): ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Avant</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Après</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Motif</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full <?= $movement['movement_type'] === 'in' ? 'bg-green-100 text-green-700' : ($movement['movement_type'] === 'out' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                                <?= strtoupper($movement['movement_type']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-bold"><?= $movement['quantity'] ?></td>
                        <td class="px-4 py-3 text-right text-gray-500"><?= $movement['previous_quantity'] ?></td>
                        <td class="px-4 py-3 text-right font-bold text-dark"><?= $movement['new_quantity'] ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($movement['reason'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-gray-500 text-sm"><?= date('d/m/Y H:i', strtotime($movement['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-center py-4">Aucun mouvement de stock</p>
        <?php endif; ?>
    </div>

    <div class="flex gap-3">
        <a href="product_list.php" class="flex-1 bg-white border border-gray-200 text-dark font-bold py-3 rounded-2xl text-center hover:bg-gray-50 transition">
            ← Retour à la liste
        </a>
    </div>
</div>
<?php ph_render_shell_end();
