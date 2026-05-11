<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = ph_current_user();
if (!$user) {
    ph_redirect('auth_login.php');
}

// Récupérer les catégories et fournisseurs
try {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    $suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    error_log('Erreur récupération données : ' . $e->getMessage());
    $categories = [];
    $suppliers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $supplierId = !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $barcode = trim((string) ($_POST['barcode'] ?? ''));
    $unit = trim((string) ($_POST['unit'] ?? ''));
    $purchasePrice = (float) ($_POST['purchase_price'] ?? 0);
    $sellingPrice = (float) ($_POST['selling_price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
    $minStockLevel = (int) ($_POST['min_stock_level'] ?? 10);
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if ($categoryId === 0 || $name === '' || $sellingPrice <= 0) {
        ph_set_flash('error', 'Catégorie, nom et prix de vente sont requis.');
        ph_redirect('product_create.php');
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('
            INSERT INTO products 
            (category_id, supplier_id, name, description, barcode, unit, purchase_price, selling_price, stock_quantity, min_stock_level, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $categoryId,
            $supplierId,
            $name,
            $description,
            $barcode,
            $unit,
            $purchasePrice,
            $sellingPrice,
            $stockQuantity,
            $minStockLevel,
            $expiryDate,
        ]);

        // Enregistrer le mouvement de stock si quantité > 0
        if ($stockQuantity > 0) {
            $productId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('
                INSERT INTO stock_movements 
                (product_id, movement_type, quantity, previous_quantity, new_quantity, reason, reference_type, reference_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $productId,
                'in',
                $stockQuantity,
                0,
                $stockQuantity,
                'Stock initial',
                'product',
                $productId,
                $user['id'],
            ]);
        }

        $pdo->commit();
        ph_set_flash('success', 'Produit créé avec succès.');
        ph_redirect('product_list.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erreur création produit : ' . $e->getMessage());
        ph_set_flash('error', 'Erreur lors de la création du produit.');
        ph_redirect('product_create.php');
    }
}

ph_render_shell_start('Créer un produit', $user);
?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <form method="post" class="space-y-6">
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Catégorie *</label>
                <select name="category_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Nom du produit *</label>
                <input type="text" name="name" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Description</label>
                <textarea name="description" rows="3" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Code-barres</label>
                    <input type="text" name="barcode" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Unité</label>
                    <input type="text" name="unit" placeholder="ex: comprimé, flacon" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Fournisseur</label>
                <select name="supplier_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                    <option value="">Sans fournisseur</option>
                    <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Prix d\'achat</label>
                    <input type="number" name="purchase_price" step="0.01" min="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Prix de vente *</label>
                    <input type="number" name="selling_price" step="0.01" min="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Stock initial</label>
                    <input type="number" name="stock_quantity" min="0" value="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Stock minimum</label>
                    <input type="number" name="min_stock_level" min="0" value="10" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Date d\'expiration</label>
                <input type="date" name="expiry_date" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
            </div>

            <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600">
                Créer le produit
            </button>
        </form>
    </div>
</div>
<?php ph_render_shell_end();
