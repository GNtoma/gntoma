<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rentalId = cl_post_int('rental_id');
    $decision = cl_post_string('decision', 20);

    if ($rentalId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        cl_set_flash('error', 'Action de validation invalide.');
        cl_redirect('location_validation.php');
    }

    try {
        $stmt = $pdo->prepare('SELECT id, house_id, landlord_user_id, tenant_user_id FROM rentals WHERE id = ? AND validation_status = "pending" LIMIT 1');
        $stmt->execute([$rentalId]);
        $rental = $stmt->fetch();
        if (!$rental || ((int) $rental['landlord_user_id'] !== (int) $user['id'] && (int) $rental['tenant_user_id'] !== (int) $user['id'])) {
            throw new RuntimeException('Location introuvable.');
        }

        $pdo->beginTransaction();
        if ($decision === 'approve') {
            $conflictStmt = $pdo->prepare('SELECT id FROM rentals WHERE house_id = ? AND id <> ? AND status = "active" AND validation_status = "validated" LIMIT 1');
            $conflictStmt->execute([(int) $rental['house_id'], $rentalId]);
            if ($conflictStmt->fetch()) {
                throw new RuntimeException('Une autre location validée existe déjà pour cette maison.');
            }

            $upd = $pdo->prepare('UPDATE rentals SET validation_status = "validated", validated_at = NOW() WHERE id = ?');
            $upd->execute([$rentalId]);
            $houseUpd = $pdo->prepare('UPDATE houses SET status = "occupee" WHERE id = ?');
            $houseUpd->execute([(int) $rental['house_id']]);
            cl_set_flash('success', 'La location a été validée.');
        } else {
            $upd = $pdo->prepare('UPDATE rentals SET validation_status = "rejected", status = "ended", validated_at = NOW() WHERE id = ?');
            $upd->execute([$rentalId]);
            $paymentUpd = $pdo->prepare('DELETE FROM rental_payments WHERE rental_id = ?');
            $paymentUpd->execute([$rentalId]);
            cl_set_flash('warning', 'La location a été rejetée.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cl_set_flash('error', 'La validation a échoué.');
    }

    cl_redirect('location_validation.php');
}

$pendingRentals = cl_fetch_pending_rentals($pdo, (int) $user['id']);
$allRentals = cl_fetch_rentals_for_user($pdo, (int) $user['id']);

cl_render_shell_start('Validation des locations', $user);
?>
<div class="grid gap-6 xl:grid-cols-[1fr,1fr]">
    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">À traiter</p>
        <h2 class="mt-2 text-2xl font-black">Locations en attente</h2>
        <div class="mt-6 space-y-4">
            <?php if (!$pendingRentals): ?>
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm font-medium text-slate-500">Aucune location en attente.</div>
            <?php else: ?>
                <?php foreach ($pendingRentals as $rental): ?>
                    <article class="rounded-[2rem] border border-slate-200 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-lg font-black text-slate-900"><?= cl_escape((string) $rental['house_title']) ?></h3>
                                <p class="mt-2 text-sm text-slate-600">Bailleur : <?= cl_escape((string) $rental['landlord_name']) ?></p>
                                <p class="text-sm text-slate-600">Locataire : <?= cl_escape((string) $rental['tenant_name']) ?></p>
                                <p class="mt-2 text-sm font-bold text-slate-700">Période : <?= cl_escape((string) $rental['start_date']) ?> → <?= cl_escape((string) $rental['end_date']) ?></p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <form method="post">
                                    <input type="hidden" name="rental_id" value="<?= (int) $rental['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="rounded-full bg-emerald-500 px-4 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-600">Valider</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="rental_id" value="<?= (int) $rental['id'] ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="rounded-full bg-red-500 px-4 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-red-600">Rejeter</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Historique</p>
        <h2 class="mt-2 text-2xl font-black">Toutes vos locations</h2>
        <div class="mt-6 space-y-4 max-h-[44rem] overflow-auto pr-2">
            <?php if (!$allRentals): ?>
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm font-medium text-slate-500">Aucune location enregistrée.</div>
            <?php else: ?>
                <?php foreach ($allRentals as $rental): ?>
                    <article class="rounded-[2rem] border border-slate-200 p-5">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-black text-slate-900"><?= cl_escape((string) $rental['house_title']) ?></p>
                                <p class="mt-1 text-sm text-slate-600"><?= cl_escape((string) $rental['landlord_name']) ?> ↔ <?= cl_escape((string) $rental['tenant_name']) ?></p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black uppercase tracking-[0.2em] text-slate-700"><?= cl_escape((string) $rental['status']) ?></span>
                                <span class="rounded-full <?= $rental['validation_status'] === 'validated' ? 'bg-emerald-100 text-emerald-700' : ($rental['validation_status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?> px-3 py-2 text-xs font-black uppercase tracking-[0.2em]">
                                    <?= cl_escape((string) $rental['validation_status']) ?>
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php cl_render_shell_end();
