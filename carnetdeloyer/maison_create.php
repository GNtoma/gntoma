<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();

$fragment = trim((string) ($_GET['fragment'] ?? ''));
if ($fragment !== '') {
    $countryCode = strtoupper(trim((string) ($_GET['country_code'] ?? '')));
    $cityId = (int) ($_GET['city_id'] ?? 0);
    $cityQuery = trim((string) ($_GET['city_query'] ?? ''));

    if ($fragment === 'city-selector') {
        cl_render_city_search_fragment($pdo, $countryCode, null);
        echo '<div id="commune-selector" hx-swap-oob="innerHTML">';
        cl_render_commune_fragment($pdo, 0, 0);
        echo '</div>';
        exit;
    }

    if ($fragment === 'city-results') {
        cl_render_city_results($pdo, $countryCode, $cityQuery);
        exit;
    }

    if ($fragment === 'commune-selector') {
        cl_render_commune_fragment($pdo, $cityId, 0);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = cl_post_string('title', 150);
    $description = cl_post_string('description', 5000);
    $monthlyRent = cl_post_amount('monthly_rent');
    $countryCode = strtoupper(cl_post_string('country_code', 3));
    $cityId = cl_post_int('city_id');
    $communeId = cl_post_int('commune_id');
    $avenue = cl_post_string('avenue', 150);
    $houseNumber = cl_post_string('house_number', 50);
    $status = cl_post_string('status', 20);

    if ($title === '' || $description === '' || $monthlyRent <= 0 || $avenue === '' || $houseNumber === '') {
        cl_set_flash('error', 'Tous les champs obligatoires doivent être remplis.');
        cl_redirect('maison_create.php');
    }

    if (!in_array($status, ['libre', 'occupee'], true)) {
        cl_set_flash('error', 'Statut de maison invalide.');
        cl_redirect('maison_create.php');
    }

    $location = cl_validate_location($pdo, $countryCode, $cityId, $communeId);
    if (!$location) {
        cl_set_flash('error', 'La localisation sélectionnée est invalide.');
        cl_redirect('maison_create.php');
    }

    $userCode = (string) ($user['user_code'] ?? '');
    $images = cl_store_uploaded_house_images($_FILES['images'] ?? [], $userCode);
    if (!$images) {
        cl_set_flash('error', 'Ajoutez au moins une image valide au format jpg, png ou webp.');
        cl_redirect('maison_create.php');
    }

    try {
        $pdo->beginTransaction();
        $houseId = cl_create_house($pdo, (int) $user['id'], [
            'title' => $title,
            'description' => $description,
            'monthly_rent' => $monthlyRent,
            'country_code' => $countryCode,
            'city_id' => $cityId,
            'commune_id' => $communeId,
            'avenue' => $avenue,
            'house_number' => $houseNumber,
            'status' => $status,
        ]);
        $renamedImages = cl_rename_house_images($images, $userCode, $houseId);
        cl_attach_house_images($pdo, $houseId, $renamedImages);
        $pdo->commit();
        cl_set_flash('success', 'Maison créée avec succès.');
        cl_redirect('maison_details.php?id=' . $houseId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cl_delete_uploaded_files($images);
        cl_delete_uploaded_files($renamedImages ?? []);
        cl_set_flash('error', 'Impossible de créer la maison.');
        cl_redirect('maison_create.php');
    }
}

cl_render_shell_start('Créer une maison', $user);
?>
<div class="grid gap-6 lg:grid-cols-[1.3fr,0.7fr]">
    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
        <form method="post" enctype="multipart/form-data" class="space-y-6">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-bold text-slate-700">Titre de la maison</label>
                    <input type="text" name="title" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-bold text-slate-700">Description</label>
                    <textarea name="description" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required></textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Loyer mensuel</label>
                    <input type="number" step="0.01" min="0" name="monthly_rent" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Statut</label>
                    <select name="status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary">
                        <option value="libre">Libre</option>
                        <option value="occupee">Occupée</option>
                    </select>
                </div>
            </div>

            <?php cl_render_city_picker($pdo); ?>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Avenue</label>
                    <input type="text" name="avenue" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Numéro</label>
                    <input type="text" name="house_number" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Images multiples</label>
                <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-primary" required>
            </div>

            <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Enregistrer la maison</button>
        </form>
    </section>

    <aside class="glass rounded-[2rem] border border-white/70 p-6 shadow-soft">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Localisation validée</p>
        <h2 class="mt-2 text-2xl font-black">Aucune ville fictive</h2>
        <p class="mt-4 text-sm leading-7 text-slate-600">Le formulaire impose une sélection réelle via le référentiel des pays, villes et communes. Le backend vérifie ensuite que les identifiants sélectionnés existent vraiment en base.</p>
        <div class="mt-6 space-y-3">
            <div class="rounded-2xl bg-white/80 p-4">
                <p class="text-sm font-black text-slate-900">HTMX</p>
                <p class="mt-2 text-sm text-slate-600">Recherche dynamique des villes et chargement ciblé des communes.</p>
            </div>
            <div class="rounded-2xl bg-white/80 p-4">
                <p class="text-sm font-black text-slate-900">MySQL</p>
                <p class="mt-2 text-sm text-slate-600">Stockage en IDs, index géographiques et validation serveur stricte.</p>
            </div>
        </div>
    </aside>
</div>
<?php cl_render_shell_end();
