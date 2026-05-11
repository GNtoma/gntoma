<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$stats = cl_fetch_dashboard_stats($pdo, (int) $user['id']);
$houses = array_slice(cl_fetch_houses($pdo, (int) $user['id']), 0, 3);
$pendingRentals = array_slice(cl_fetch_pending_rentals($pdo, (int) $user['id']), 0, 3);

cl_render_shell_start('Tableau de bord locatif', $user);
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
    <section class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="animate__animated animate__fadeInUp rounded-[2rem] bg-white p-5 shadow-soft">
                <p class="text-xs font-black uppercase tracking-[0.28em] text-slate-400">Maisons créées</p>
                <p class="mt-3 text-3xl font-black text-slate-900"><?= (int) $stats['houses_owned'] ?></p>
            </article>
            <article class="animate__animated animate__fadeInUp rounded-[2rem] bg-white p-5 shadow-soft">
                <p class="text-xs font-black uppercase tracking-[0.28em] text-slate-400">Maisons louées</p>
                <p class="mt-3 text-3xl font-black text-slate-900"><?= (int) $stats['houses_rented'] ?></p>
            </article>
            <article class="animate__animated animate__fadeInUp rounded-[2rem] bg-white p-5 shadow-soft">
                <p class="text-xs font-black uppercase tracking-[0.28em] text-slate-400">Locations actives</p>
                <p class="mt-3 text-3xl font-black text-slate-900"><?= (int) $stats['active_locations'] ?></p>
            </article>
            <article class="animate__animated animate__fadeInUp rounded-[2rem] bg-white p-5 shadow-soft">
                <p class="text-xs font-black uppercase tracking-[0.28em] text-slate-400">Paiements à suivre</p>
                <p class="mt-3 text-3xl font-black text-slate-900"><?= (int) $stats['payments_pending'] ?></p>
            </article>
        </div>

        <section class="glass rounded-[2rem] border border-white/70 p-6 shadow-soft">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Actions rapides</p>
                    <h2 class="mt-2 text-2xl font-black">Pilotez votre activité locative</h2>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="maison_create.php" class="rounded-full bg-primary px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Ajouter une maison</a>
                    <a href="maison_list.php" class="rounded-full bg-slate-900 px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-slate-800">Rechercher une maison</a>
                    <a href="location_validation.php" class="rounded-full bg-white px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-slate-700 shadow-sm transition hover:bg-slate-50">Valider les locations</a>
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Paiements</p>
                    <h2 class="mt-2 text-2xl font-black">Tableau mensuel dynamique</h2>
                </div>
                <a href="paiement_tableau.php" class="rounded-full bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-200">Vue complète</a>
            </div>
            <div id="payments-dynamic-zone" class="mt-5" hx-get="paiement_tableau.php" hx-trigger="load" hx-swap="innerHTML">
                <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-500">
                    <span class="htmx-indicator inline-flex h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-primary"></span>
                    Chargement des paiements...
                </div>
            </div>
        </section>
    </section>

    <aside class="space-y-6">
        <section class="rounded-[2rem] bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Validation</p>
                    <h2 class="mt-2 text-xl font-black">Demandes en attente</h2>
                </div>
                <a href="location_validation.php" class="text-sm font-black text-primary">Voir tout</a>
            </div>
            <div class="mt-5 space-y-4">
                <?php if (!$pendingRentals): ?>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm font-medium text-slate-500">Aucune location en attente de validation.</div>
                <?php else: ?>
                    <?php foreach ($pendingRentals as $rental): ?>
                        <article class="rounded-3xl border border-slate-200 p-4">
                            <p class="text-sm font-black text-slate-900"><?= cl_escape((string) $rental['house_title']) ?></p>
                            <p class="mt-2 text-sm text-slate-600">Bailleur : <?= cl_escape((string) $rental['landlord_name']) ?></p>
                            <p class="text-sm text-slate-600">Locataire : <?= cl_escape((string) $rental['tenant_name']) ?></p>
                            <p class="mt-2 text-xs font-black uppercase tracking-[0.22em] text-amber-500">En attente</p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-[2rem] bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Maisons</p>
                    <h2 class="mt-2 text-xl font-black">Vos dernières fiches</h2>
                </div>
                <a href="maison_list.php" class="text-sm font-black text-primary">Voir tout</a>
            </div>
            <div class="mt-5 space-y-4">
                <?php if (!$houses): ?>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm font-medium text-slate-500">Aucune maison enregistrée pour le moment.</div>
                <?php else: ?>
                    <?php foreach ($houses as $house): ?>
                        <a href="maison_details.php?id=<?= (int) $house['id'] ?>" class="block rounded-3xl border border-slate-200 p-4 transition hover:border-primary hover:bg-blue-50/50">
                            <div class="flex items-start gap-4">
                                <img src="<?= cl_escape(cl_house_cover_src($house['cover_image'] ?? null, (string) $house['title'])) ?>" alt="Maison" class="h-20 w-20 rounded-2xl object-cover">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-900"><?= cl_escape((string) $house['title']) ?></p>
                                    <p class="mt-1 text-sm text-slate-600"><?= cl_escape((string) $house['commune_name']) ?>, <?= cl_escape((string) $house['city_name']) ?></p>
                                    <p class="mt-2 text-xs font-black uppercase tracking-[0.22em] <?= $house['status'] === 'libre' ? 'text-emerald-500' : 'text-amber-500' ?>"><?= cl_escape((string) $house['status']) ?></p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </aside>
</div>
<?php cl_render_shell_end();
