<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = ph_current_user();
if (!$user) {
    ph_redirect('auth_login.php');
}

// Récupérer les produits disponibles
try {
    $products = $pdo->query('SELECT id, name, selling_price, stock_quantity FROM products WHERE status = "active" AND stock_quantity > 0 ORDER BY name ASC')->fetchAll();
    $customers = $pdo->query('SELECT id, name FROM customers ORDER BY name ASC')->fetchAll();
} catch (Throwable $e) {
    error_log('Erreur récupération données : ' . $e->getMessage());
    $products = [];
    $customers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
    $productIds = (array) ($_POST['product_id'] ?? []);
    $quantities = (array) ($_POST['quantity'] ?? []);
    $paymentMethod = (string) ($_POST['payment_method'] ?? 'cash');
    $discountAmount = (float) ($_POST['discount_amount'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (empty($productIds)) {
        ph_set_flash('error', 'Au moins un produit est requis.');
        ph_redirect('sale_create.php');
    }

    try {
        $pdo->beginTransaction();

        // Récupérer les prix et stocks
        $totalAmount = 0;
        $items = [];

        foreach ($productIds as $index => $productId) {
            $quantity = (int) ($quantities[$index] ?? 0);
            if ($quantity <= 0) continue;

            $stmt = $pdo->prepare('SELECT id, selling_price, stock_quantity FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product || $product['stock_quantity'] < $quantity) {
                throw new RuntimeException("Stock insuffisant pour le produit #$productId");
            }

            $itemTotal = $product['selling_price'] * $quantity;
            $totalAmount += $itemTotal;
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $product['selling_price'],
                'total_amount' => $itemTotal,
            ];
        }

        if (empty($items)) {
            throw new RuntimeException('Aucun produit valide');
        }

        $finalAmount = max(0, $totalAmount - $discountAmount);

        // Créer la vente
        $stmt = $pdo->prepare('
            INSERT INTO sales 
            (customer_id, total_amount, discount_amount, final_amount, payment_method, payment_status, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $customerId,
            $totalAmount,
            $discountAmount,
            $finalAmount,
            $paymentMethod,
            'paid',
            'completed',
            $notes,
            $user['id'],
        ]);

        $saleId = (int) $pdo->lastInsertId();

        // Ajouter les articles et décrémenter le stock
        foreach ($items as $item) {
            // Insérer l'article de vente
            $stmt = $pdo->prepare('
                INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_amount)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $saleId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_amount'],
            ]);

            // Décrémenter le stock
            $stmt = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
            $stmt->execute([$item['quantity'], $item['product_id']]);

            // Enregistrer le mouvement de stock
            $stmt = $pdo->prepare('SELECT stock_quantity FROM products WHERE id = ?');
            $stmt->execute([$item['product_id']]);
            $newQuantity = $stmt->fetchColumn();

            $stmt = $pdo->prepare('
                INSERT INTO stock_movements 
                (product_id, movement_type, quantity, previous_quantity, new_quantity, reason, reference_type, reference_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $item['product_id'],
                'out',
                $item['quantity'],
                $newQuantity + $item['quantity'],
                $newQuantity,
                'Vente #' . $saleId,
                'sale',
                $saleId,
                $user['id'],
            ]);
        }

        $pdo->commit();
        ph_set_flash('success', 'Vente créée avec succès. Total : ' . number_format($finalAmount, 2) . ' $');
        ph_redirect('sale_list.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erreur création vente : ' . $e->getMessage());
        ph_set_flash('error', 'Erreur lors de la création de la vente : ' . $e->getMessage());
        ph_redirect('sale_create.php');
    }
}

ph_render_shell_start('Nouvelle vente', $user);
?>
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <form method="post" class="space-y-6">
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Client (optionnel)</label>
                <select name="customer_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                    <option value="">Client anonyme</option>
                    <?php foreach ($customers as $cust): ?>
                    <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Produits</label>
                <div id="products-container" class="space-y-3">
                    <?php foreach ($products as $product): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                        <input type="checkbox" name="product_id[]" value="<?= $product['id'] ?>" class="w-5 h-5 accent-primary product-checkbox">
                        <div class="flex-1">
                            <p class="font-bold text-dark"><?= htmlspecialchars($product['name']) ?></p>
                            <p class="text-sm text-gray-500"><?= number_format($product['selling_price'], 2) ?> $ | Stock : <?= $product['stock_quantity'] ?></p>
                        </div>
                        <input type="number" name="quantity[]" min="1" max="<?= $product['stock_quantity'] ?>" value="1" class="w-20 rounded-xl border border-slate-200 px-3 py-2 text-center product-quantity" disabled>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($products)): ?>
                <p class="text-gray-500 text-center py-4">Aucun produit disponible en stock</p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Méthode de paiement</label>
                    <select name="payment_method" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                        <option value="cash">Espèces</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="card">Carte bancaire</option>
                        <option value="credit">Crédit</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Remise</label>
                    <input type="number" name="discount_amount" step="0.01" min="0" value="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Notes (optionnel)</label>
                <textarea name="notes" rows="2" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary"></textarea>
            </div>

            <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600">
                Enregistrer la vente
            </button>
        </form>
    </div>

    <script>
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const quantityInput = this.closest('.flex').querySelector('.product-quantity');
                quantityInput.disabled = !this.checked;
            });
        });
    </script>
</div>
<?php ph_render_shell_end();
