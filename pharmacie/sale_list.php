<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = ph_current_user();
if (!$user) {
    ph_redirect('auth_login.php');
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');

try {
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(s.id LIKE ? OR c.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($statusFilter !== '') {
        $where[] = 's.status = ?';
        $params[] = $statusFilter;
    }

    $sql = 'SELECT s.*, c.name as customer_name, 
                   (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
            FROM sales s 
            LEFT JOIN customers c ON s.customer_id = c.id 
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY s.created_at DESC 
            LIMIT 50';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    $totalRevenue = $pdo->query('SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE status = "completed"')->fetchColumn();
} catch (Throwable $e) {
    error_log('Erreur liste ventes : ' . $e->getMessage());
    $sales = [];
    $totalRevenue = 0;
}

ph_render_shell_start('Historique des ventes', $user);
?>
<div class="space-y-6">
    <!-- Stats -->
    <div class="bg-primary rounded-2xl p-6 text-white">
        <p class="text-sm font-medium opacity-90">Revenu total</p>
        <p class="text-3xl font-black"><?= number_format($totalRevenue, 2) ?> $</p>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-2xl p-6 shadow-soft">
        <form method="get" class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par N° vente ou client" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
            </div>
            <div class="w-full sm:w-48">
                <select name="status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                    <option value="">Tous statuts</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Complétées</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Annulées</option>
                    <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Remboursées</option>
                </select>
            </div>
            <button type="submit" class="w-full sm:w-auto rounded-2xl bg-primary px-6 py-3 text-white font-bold hover:bg-emerald-600 transition">
                Filtrer
            </button>
        </form>
    </div>

    <!-- Liste des ventes -->
    <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
        <?php if (!empty($sales)): ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">N° Vente</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Articles</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Montant</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Paiement</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($sales as $sale): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-bold text-primary">#<?= $sale['id'] ?></td>
                        <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($sale['customer_name'] ?? 'Anonyme') ?></td>
                        <td class="px-6 py-4 text-center text-gray-600"><?= $sale['items_count'] ?></td>
                        <td class="px-6 py-4 text-right font-bold text-dark"><?= number_format($sale['final_amount'], 2) ?> $</td>
                        <td class="px-6 py-4 text-center text-gray-600">
                            <?= match($sale['payment_method']) {
                                'cash' => 'Espèces',
                                'mobile_money' => 'Mobile Money',
                                'card' => 'Carte',
                                'credit' => 'Crédit',
                                default => $sale['payment_method']
                            } ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($sale['status'] === 'completed'): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700">Complétée</span>
                            <?php elseif ($sale['status'] === 'cancelled'): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">Annulée</span>
                            <?php elseif ($sale['status'] === 'refunded'): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-orange-100 text-orange-700">Remboursée</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-sm"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="p-8 text-center text-gray-500">
            Aucune vente trouvée
        </div>
        <?php endif; ?>
    </div>

    <a href="sale_create.php" class="inline-flex items-center justify-center bg-primary text-white font-bold px-8 py-4 rounded-2xl hover:bg-emerald-600 transition">
        + Nouvelle vente
    </a>
</div>
<?php ph_render_shell_end();
