<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$availableHouses = cl_fetch_available_houses($pdo, (int) $user['id']);
$selectedHouseId = (int) ($_GET['house_id'] ?? $_POST['house_id'] ?? 0);
$selectedHouse = null;
foreach ($availableHouses as $house) {
    if ((int) $house['id'] === $selectedHouseId) {
        $selectedHouse = $house;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $houseId = cl_post_int('house_id');
    $startDate = cl_post_string('start_date', 20);
    $endDate = cl_post_string('end_date', 20);

    if ($houseId <= 0 || $startDate === '' || $endDate === '') {
        cl_set_flash('error', 'Sélectionnez une maison et la période de location.');
        cl_redirect('location_create.php' . ($houseId > 0 ? '?house_id=' . $houseId : ''));
    }

    $houseDetails = null;
    foreach ($availableHouses as $house) {
        if ((int) $house['id'] === $houseId) {
            $houseDetails = $house;
            break;
        }
    }

    if (!$houseDetails) {
        cl_set_flash('error', 'Cette maison n’est pas disponible à la location.');
        cl_redirect('location_create.php');
    }

    try {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        if ($end < $start) {
            throw new RuntimeException('Période invalide.');
        }
    } catch (Throwable $e) {
        cl_set_flash('error', 'Période de location invalide.');
        cl_redirect('location_create.php?house_id=' . $houseId);
    }

    try {
        $pdo->beginTransaction();
        $ownerStmt = $pdo->prepare('SELECT owner_user_id, monthly_rent, status FROM houses WHERE id = ? AND status = "libre" LIMIT 1');
        $ownerStmt->execute([$houseId]);
        $houseRow = $ownerStmt->fetch();
        if (!$houseRow) {
            throw new RuntimeException('Maison non disponible.');
        }

        $existingStmt = $pdo->prepare('SELECT id FROM rentals WHERE house_id = ? AND status = "active" AND validation_status IN ("pending", "validated") LIMIT 1');
        $existingStmt->execute([$houseId]);
        if ($existingStmt->fetch()) {
            throw new RuntimeException('Maison déjà louée.');
        }

        $rentalId = cl_create_rental($pdo, [
            'house_id' => $houseId,
            'landlord_user_id' => (int) $houseRow['owner_user_id'],
            'tenant_user_id' => (int) $user['id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'monthly_rent' => (float) $houseRow['monthly_rent'],
            'status' => 'active',
            'validation_status' => 'pending',
            'created_by_user_id' => (int) $user['id'],
        ]);
        cl_generate_payment_schedule($pdo, $rentalId, $startDate, $endDate, (float) $houseRow['monthly_rent']);
        $pdo->commit();
        cl_set_flash('success', 'La location a été créée et attend validation.');
        cl_redirect('location_validation.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cl_set_flash('error', 'Impossible de créer la location.');
        cl_redirect('location_create.php' . ($houseId > 0 ? '?house_id=' . $houseId : ''));
    }
}

cl_render_shell_start('Créer une location', $user);
?>
<div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
        <form method="post" class="space-y-6">
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-700">Maison à louer</label>
                <select name="house_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                    <option value="0">Choisissez une maison libre</option>
                    <?php foreach ($availableHouses as $house): ?>
                        <option value="<?= (int) $house['id'] ?>" <?= (int) $house['id'] === $selectedHouseId ? 'selected' : '' ?>>
                            <?= cl_escape((string) $house['title']) ?> - <?= cl_escape((string) $house['city_name']) ?> - <?= cl_format_currency((float) $house['monthly_rent']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Début du contrat</label>
                    <input type="date" name="start_date" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">Fin du contrat</label>
                    <input type="date" name="end_date" class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none focus:border-primary" required>
                </div>
            </div>
            <button type="submit" class="w-full rounded-2xl bg-primary px-5 py-4 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Créer la location</button>
        </form>
    </section>

    <aside class="glass rounded-[2rem] border border-white/70 p-6 shadow-soft">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Prévisualisation</p>
        <?php if ($selectedHouse): ?>
            <h2 class="mt-2 text-2xl font-black"><?= cl_escape((string) $selectedHouse['title']) ?></h2>
            <p class="mt-3 text-sm text-slate-600">Bailleur : <?= cl_escape((string) $selectedHouse['owner_name']) ?></p>
            <p class="mt-1 text-sm text-slate-600">Adresse : <?= cl_escape((string) $selectedHouse['commune_name']) ?>, <?= cl_escape((string) $selectedHouse['city_name']) ?></p>
            <p class="mt-4 text-lg font-black text-slate-900"><?= cl_format_currency((float) $selectedHouse['monthly_rent']) ?></p>
        <?php else: ?>
            <h2 class="mt-2 text-2xl font-black">Choisissez une maison libre</h2>
            <p class="mt-3 text-sm leading-7 text-slate-600">Chaque location relie directement un locataire, une maison et un bailleur. Le système génère automatiquement les échéances mensuelles dès la création.</p>
        <?php endif; ?>
    </aside>
</div>
<?php cl_render_shell_end();
