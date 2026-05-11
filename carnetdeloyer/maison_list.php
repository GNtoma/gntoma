<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$houses = cl_fetch_houses($pdo, (int) $user['id']);
$searchFilters = [
    'query' => cl_get_string('q', 120),
    'city' => cl_get_string('city', 120),
    'commune' => cl_get_string('commune', 120),
    'min_rent' => cl_get_amount('min_rent'),
    'max_rent' => cl_get_amount('max_rent'),
];
if ($searchFilters['min_rent'] > 0 && $searchFilters['max_rent'] > 0 && $searchFilters['min_rent'] > $searchFilters['max_rent']) {
    [$searchFilters['min_rent'], $searchFilters['max_rent']] = [$searchFilters['max_rent'], $searchFilters['min_rent']];
}
$availableHouses = cl_search_available_houses($pdo, (int) $user['id'], $searchFilters);
$hasSearch = $searchFilters['query'] !== ''
    || $searchFilters['city'] !== ''
    || $searchFilters['commune'] !== ''
    || $searchFilters['min_rent'] > 0
    || $searchFilters['max_rent'] > 0;

cl_render_shell_start('Maisons', $user);
?>
<div class="space-y-8">
    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Patrimoine</p>
                <h2 class="mt-2 text-2xl font-black">Vos maisons et celles que vous occupez</h2>
            </div>
            <a href="maison_create.php" class="rounded-full bg-primary px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Nouvelle maison</a>
        </div>
        <div class="mt-6 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
            <?php if (!$houses): ?>
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm font-medium text-slate-500">Aucune maison liée à votre compte pour le moment.</div>
            <?php else: ?>
                <?php foreach ($houses as $house): ?>
                    <article class="overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-50/70 shadow-sm transition hover:-translate-y-1 hover:shadow-soft">
                        <img src="<?= cl_escape(cl_house_cover_src($house['cover_image'] ?? null, (string) $house['title'])) ?>" alt="Maison" class="h-52 w-full object-cover">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-black text-slate-900"><?= cl_escape((string) $house['title']) ?></h3>
                                    <p class="mt-1 text-sm text-slate-600"><?= cl_escape((string) $house['commune_name']) ?>, <?= cl_escape((string) $house['city_name']) ?></p>
                                </div>
                                <span class="rounded-full px-3 py-2 text-xs font-black uppercase tracking-[0.2em] <?= $house['status'] === 'libre' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>"><?= cl_escape((string) $house['status']) ?></span>
                            </div>
                            <p class="mt-4 text-sm font-black text-slate-900"><?= cl_format_currency((float) $house['monthly_rent']) ?></p>
                            <a href="maison_details.php?id=<?= (int) $house['id'] ?>" class="mt-5 inline-flex rounded-full bg-slate-900 px-4 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-slate-800">Voir les détails</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.28em] text-accent">Catalogue</p>
                <h2 class="mt-2 text-2xl font-black">Rechercher des maisons libres à louer</h2>
            </div>
            <a href="location_create.php" class="rounded-full bg-accent px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-teal-600">Créer une location</a>
        </div>
        <form method="get" class="mt-6 grid gap-4 rounded-[2rem] border border-slate-200 bg-slate-50/80 p-5 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="mb-2 block text-sm font-bold text-slate-700">Mot-clé</label>
                <input type="search" name="q" value="<?= cl_escape($searchFilters['query']) ?>" placeholder="Titre, ville, commune, bailleur..." class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Ville</label>
                <input type="text" name="city" value="<?= cl_escape($searchFilters['city']) ?>" placeholder="Ex: Kinshasa" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Commune</label>
                <input type="text" name="commune" value="<?= cl_escape($searchFilters['commune']) ?>" placeholder="Ex: Gombe" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Budget max</label>
                <input type="number" step="0.01" min="0" name="max_rent" value="<?= $searchFilters['max_rent'] > 0 ? cl_escape((string) $searchFilters['max_rent']) : '' ?>" placeholder="Ex: 500" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-primary">
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Budget min</label>
                <input type="number" step="0.01" min="0" name="min_rent" value="<?= $searchFilters['min_rent'] > 0 ? cl_escape((string) $searchFilters['min_rent']) : '' ?>" placeholder="Ex: 100" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-primary">
            </div>
            <div class="md:col-span-2 xl:col-span-5 flex flex-wrap gap-3">
                <button type="submit" class="rounded-full bg-primary px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Rechercher</button>
                <a href="maison_list.php" class="rounded-full bg-white px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-slate-700 transition hover:bg-slate-100">Réinitialiser</a>
            </div>
        </form>
        <div class="mt-5 flex flex-wrap items-center gap-3 text-sm font-semibold text-slate-600">
            <span class="rounded-full bg-slate-100 px-4 py-2"><?= count($availableHouses) ?> maison(s) trouvée(s)</span>
            <?php if ($hasSearch): ?>
                <span class="rounded-full bg-blue-50 px-4 py-2 text-primary">Recherche active</span>
            <?php endif; ?>
        </div>
        <div class="mt-6 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
            <?php if (!$availableHouses): ?>
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm font-medium text-slate-500"><?= $hasSearch ? 'Aucune maison ne correspond à votre recherche.' : 'Aucune maison libre disponible actuellement.' ?></div>
            <?php else: ?>
                <?php foreach ($availableHouses as $house): ?>
                    <article class="overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-50/70 shadow-sm">
                        <img src="<?= cl_escape(cl_house_cover_src($house['cover_image'] ?? null, (string) $house['title'])) ?>" alt="Maison libre" class="h-52 w-full object-cover">
                        <div class="p-5">
                            <h3 class="text-lg font-black text-slate-900"><?= cl_escape((string) $house['title']) ?></h3>
                            <p class="mt-1 text-sm text-slate-600"><?= cl_escape((string) $house['commune_name']) ?>, <?= cl_escape((string) $house['city_name']) ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?= cl_escape((string) $house['avenue']) ?>, N° <?= cl_escape((string) $house['house_number']) ?></p>
                            <p class="mt-1 text-sm text-slate-600">Bailleur : <?= cl_escape((string) $house['owner_name']) ?></p>
                            <p class="mt-4 text-sm font-black text-slate-900"><?= cl_format_currency((float) $house['monthly_rent']) ?></p>
                            <div class="mt-5 flex gap-3">
                                <a href="maison_details.php?id=<?= (int) $house['id'] ?>" class="rounded-full bg-slate-100 px-4 py-3 text-xs font-black uppercase tracking-[0.2em] text-slate-700 transition hover:bg-slate-200">Détails</a>
                                <a href="location_create.php?house_id=<?= (int) $house['id'] ?>" class="rounded-full bg-primary px-4 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Louer</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php cl_render_shell_end();
