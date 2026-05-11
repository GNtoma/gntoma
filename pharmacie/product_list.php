<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = ph_current_user();
if (!$user) {
    ph_redirect('auth_login.php');
}

$search = trim((string) ($_GET['search'] ?? ''));
$categoryFilter = (int) ($_GET['category'] ?? 0);

try {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(name LIKE ? OR barcode LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($categoryFilter > 0) {
        $where[] = 'category_id = ?';
        $params[] = $categoryFilter;
    }

    $sql = 'SELECT p.*, c.name as category_name, s.name as supplier_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Erreur liste produits : ' . $e->getMessage());
    $products = [];
    $categories = [];
}

ph_render_shell_start('Liste des produits', $user);
?>
<div class="space-y-6">
    <!-- Filtres -->
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <form method="get" class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par nom ou code-barres" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
            </div>
            <div class="w-full sm:w-48">
                <select name="category" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                    <option value="0">Toutes catégories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter === $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="w-full sm:w-auto rounded-2xl bg-primary px-6 py-3 text-white font-bold hover:bg-emerald-600 transition">
                Filtrer
            </button>
        </form>
    </div>

    <!-- Liste des produits -->
    <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
        <?php if (!empty($products)): ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Produit</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Catégorie</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Prix vente</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($products as $product): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <div>
                                <p class="font-bold text-dark"><?= htmlspecialchars($product['name']) ?></p>
                                <?php if ($product['barcode']): ?>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($product['barcode']) ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                        <td class="px-6 py-4 text-right font-bold text-primary"><?= number_format($product['selling_price'], 2) ?> $</td>
                        <td class="px-6 py-4 text-center">
                            <span class="<?= $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-orange-600 font-bold' : 'text-gray-700' ?>">
                                <?= $product['stock_quantity'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($product['expiry_date'] && $product['expiry_date'] < date('Y-m-d')): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">Expiré</span>
                            <?php elseif ($product['status'] === 'inactive'): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-700">Inactif</span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">Actif</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center gap-2">
                                <a href="product_details.php?id=<?= $product['id'] ?>" class="text-primary hover:text-emerald-600 font-bold text-sm">Voir</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="p-8 text-center text-gray-500">
            Aucun produit trouvé
        </div>
        <?php endif; ?>
    </div>

    <a href="product_create.php" class="inline-flex items-center justify-center bg-primary text-white font-bold px-8 py-4 rounded-2xl hover:bg-emerald-600 transition">
        + Nouveau produit
    </a>
</div>
<?php ph_render_shell_end();
