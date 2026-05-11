<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$houseId = (int) ($_GET['id'] ?? 0);
$house = $houseId > 0 ? cl_fetch_house_details($pdo, $houseId, (int) $user['id']) : null;

if (!$house) {
    cl_set_flash('error', 'Maison introuvable.');
    cl_redirect('maison_list.php');
}

$availabilityStmt = $pdo->prepare('SELECT id FROM rentals WHERE house_id = ? AND status = "active" AND validation_status IN ("pending", "validated") LIMIT 1');
$availabilityStmt->execute([$houseId]);
$canCreateRental = (int) $house['owner_user_id'] !== (int) $user['id']
    && (string) $house['status'] === 'libre'
    && !$availabilityStmt->fetch();

$rentals = cl_fetch_rentals_for_user($pdo, (int) $user['id']);
$rentals = array_values(array_filter($rentals, static fn(array $row): bool => (int) $row['house_id'] === $houseId));
$canViewPayments = (int) $house['owner_user_id'] === (int) $user['id'] || $rentals !== [];

cl_render_shell_start('Détails de la maison', $user);
?>
<div class="grid gap-6 xl:grid-cols-[1.35fr,0.65fr]">
    <section class="space-y-6">
        <article class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                <?php if (!$house['images']): ?>
                    <img src="<?= cl_escape(cl_house_cover_src(null, (string) $house['title'])) ?>" alt="Image maison" class="h-64 w-full rounded-[1.5rem] object-cover md:col-span-2 xl:col-span-3">
                <?php else: ?>
                    <?php foreach ($house['images'] as $image): ?>
                        <img src="../<?= cl_escape((string) $image['image_path']) ?>" alt="Image maison" class="h-64 w-full rounded-[1.5rem] object-cover">
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="border-t border-slate-100 p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Maison</p>
                        <h2 class="mt-2 text-3xl font-black"><?= cl_escape((string) $house['title']) ?></h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600"><?= nl2br(cl_escape((string) $house['description'])) ?></p>
                    </div>
                    <span class="rounded-full px-4 py-3 text-xs font-black uppercase tracking-[0.2em] <?= $house['status'] === 'libre' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>"><?= cl_escape((string) $house['status']) ?></span>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-3xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-[0.25em] text-slate-400">Pays</p><p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) $house['country_name']) ?></p></div>
                    <div class="rounded-3xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-[0.25em] text-slate-400">Ville</p><p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) $house['city_name']) ?></p></div>
                    <div class="rounded-3xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-[0.25em] text-slate-400">Commune</p><p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) $house['commune_name']) ?></p></div>
                    <div class="rounded-3xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-[0.25em] text-slate-400">Adresse</p><p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) $house['avenue']) ?>, N° <?= cl_escape((string) $house['house_number']) ?></p></div>
                </div>
            </div>
        </article>

        <section class="rounded-[2rem] bg-white p-6 shadow-soft">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Historique</p>
                    <h2 class="mt-2 text-2xl font-black">Locations liées à cette maison</h2>
                </div>
                <?php if ($canCreateRental): ?>
                    <a href="location_create.php?house_id=<?= (int) $house['id'] ?>" class="rounded-full bg-primary px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Créer une location</a>
                <?php endif; ?>
            </div>
            <div class="mt-5 space-y-4">
                <?php if (!$rentals): ?>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm font-medium text-slate-500">Aucune location liée à cette maison.</div>
                <?php else: ?>
                    <?php foreach ($rentals as $rental): ?>
                        <article class="rounded-3xl border border-slate-200 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-sm font-black text-slate-900">Locataire : <?= cl_escape((string) $rental['tenant_name']) ?></p>
                                    <p class="mt-1 text-sm text-slate-600">Période : <?= cl_escape((string) $rental['start_date']) ?> → <?= cl_escape((string) $rental['end_date']) ?></p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black uppercase tracking-[0.2em] text-slate-700"><?= cl_escape((string) $rental['status']) ?></span>
                                    <span class="rounded-full <?= $rental['validation_status'] === 'validated' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?> px-3 py-2 text-xs font-black uppercase tracking-[0.2em]">
                                        <?= cl_escape((string) $rental['validation_status']) ?>
                                    </span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </section>

    <aside class="space-y-6">
        <section class="rounded-[2rem] bg-white p-6 shadow-soft">
            <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Résumé</p>
            <h2 class="mt-2 text-2xl font-black">Fiche financière</h2>
            <div class="mt-5 space-y-4">
                <div class="rounded-3xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Loyer mensuel</p>
                    <p class="mt-2 text-lg font-black text-slate-900"><?= cl_format_currency((float) $house['monthly_rent']) ?></p>
                </div>
                <div class="rounded-3xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Bailleur</p>
                    <p class="mt-2 text-lg font-black text-slate-900"><?= cl_escape((string) $house['owner_name']) ?></p>
                </div>
                <div class="rounded-3xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Actions</p>
                    <div class="mt-3 flex flex-col gap-3">
                        <?php if ($canCreateRental): ?>
                            <a href="location_create.php?house_id=<?= (int) $house['id'] ?>" class="rounded-2xl bg-primary px-4 py-3 text-center text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Nouvelle location</a>
                        <?php endif; ?>
                        <?php if ($canViewPayments): ?>
                            <a href="paiement_tableau.php?house_id=<?= (int) $house['id'] ?>" class="rounded-2xl bg-slate-900 px-4 py-3 text-center text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-slate-800">Voir les paiements</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>
<?php cl_render_shell_end();
