<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function cl_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cl_is_htmx_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_HX_REQUEST'] ?? '')) === 'true';
}

function cl_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function cl_set_flash(string $type, string $message): void
{
    $_SESSION['cl_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function cl_get_flash(): ?array
{
    $flash = $_SESSION['cl_flash'] ?? null;
    unset($_SESSION['cl_flash']);
    return is_array($flash) ? $flash : null;
}

function cl_current_user(): ?array
{
    // Utilise la session du journal
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_code = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('Erreur cl_current_user : ' . $e->getMessage());
        return null;
    }
}

function cl_require_user(): array
{
    $user = cl_current_user();
    if (!$user) {
        cl_redirect('auth_login.php');
    }
    return $user;
}

function cl_render_head(string $title): void
{
    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . cl_escape($title) . '</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">';
    echo '<script src="https://unpkg.com/htmx.org@1.9.12"></script>';
    echo '<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>';
    echo '<script>';
    echo 'tailwind.config={theme:{extend:{colors:{primary:"#2563eb",secondary:"#0f172a",accent:"#14b8a6",danger:"#ef4444",success:"#22c55e",warning:"#f59e0b"},boxShadow:{soft:"0 20px 60px rgba(15,23,42,.08)"}}}};';
    echo '</script>';
    echo '<style>body{background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 35%,#ecfeff 100%);min-height:100vh}.glass{background:rgba(255,255,255,.82);backdrop-filter:blur(18px)}[v-cloak]{display:none}.htmx-indicator{display:none}.htmx-request .htmx-indicator,.htmx-request.htmx-indicator{display:inline-flex}</style>';
    echo '</head>';
}

function cl_render_shell_start(string $title, ?array $user = null): void
{
    cl_render_head($title);
    echo '<body class="text-slate-900">';
    echo '<div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8">';
    echo '<div class="mx-auto max-w-7xl">';
    echo '<header class="glass animate__animated animate__fadeInDown mb-6 rounded-3xl border border-white/60 px-5 py-4 shadow-soft">';
    echo '<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">';
    echo '<div>';
    echo '<p class="text-xs font-black uppercase tracking-[0.35em] text-primary">Carnet de loyer</p>';
    echo '<h1 class="mt-1 text-2xl font-black sm:text-3xl">' . cl_escape($title) . '</h1>';
    echo '</div>';
    if ($user) {
        echo '<div class="flex flex-wrap items-center gap-3">';
        echo '<span class="rounded-full bg-slate-900 px-4 py-2 text-sm font-bold text-white">' . cl_escape($user['full_name']) . '</span>';
        echo '<a class="rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50" href="index.php">Tableau de bord</a>';
        echo '<a class="rounded-full bg-emerald-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-600" href="../journal/dashboard_6.php">Journal</a>';
        echo '<a class="rounded-full bg-red-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-600" href="auth_logout.php">Déconnexion</a>';
        echo '</div>';
    }
    echo '</div>';
    echo '</header>';
    $flash = cl_get_flash();
    echo '<div id="flash-zone">';
    if ($flash) {
        $map = [
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'error' => 'border-red-200 bg-red-50 text-red-700',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        ];
        $class = $map[$flash['type']] ?? 'border-slate-200 bg-white text-slate-700';
        echo '<div class="animate__animated animate__fadeIn mb-6 rounded-2xl border px-4 py-3 text-sm font-semibold ' . $class . '">' . cl_escape($flash['message']) . '</div>';
    }
    echo '</div>';
}

function cl_render_shell_end(): void
{
    echo '</div>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
}

function cl_post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function cl_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function cl_post_amount(string $key): float
{
    return round((float) ($_POST[$key] ?? 0), 2);
}

function cl_get_string(string $key, int $maxLength = 255): string
{
    $value = trim((string) ($_GET[$key] ?? ''));
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

function cl_get_amount(string $key): float
{
    $raw = trim((string) ($_GET[$key] ?? ''));
    if ($raw === '') {
        return 0.0;
    }
    return round((float) $raw, 2);
}

function cl_validate_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function cl_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function cl_find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT id, user_code, full_name, email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function cl_find_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, user_code, full_name, email, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function cl_generate_user_code(PDO $pdo): string
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $index = $count + 1;
    $letter = chr(64 + (int) ceil($index / 999));
    if ($letter > 'Z') { $letter = 'Z'; }
    $num = (($index - 1) % 999) + 1;
    return $letter . $num;
}

function cl_login_user(array $user): void
{
    // Utilise la session du journal
    $_SESSION['user_id'] = $user['user_code'];
}

function cl_logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function cl_validate_location(PDO $pdo, string $countryCode, int $cityId, int $communeId): ?array
{
    if ($countryCode === '' || $cityId <= 0 || $communeId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.id AS city_id, c.name AS city_name, c.country_code, m.id AS commune_id, m.name AS commune_name
         FROM geo_cities c
         INNER JOIN geo_communes m ON m.city_id = c.id
         WHERE c.id = ? AND m.id = ? AND c.country_code = ?
         LIMIT 1'
    );
    $stmt->execute([$cityId, $communeId, strtoupper($countryCode)]);
    $location = $stmt->fetch();

    return $location ?: null;
}

function cl_fetch_countries(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT code, name FROM geo_countries ORDER BY name ASC');
    return $stmt->fetchAll();
}

function cl_fetch_city_by_id(PDO $pdo, int $cityId): ?array
{
    $stmt = $pdo->prepare('SELECT id, country_code, name FROM geo_cities WHERE id = ? LIMIT 1');
    $stmt->execute([$cityId]);
    $city = $stmt->fetch();
    return $city ?: null;
}

function cl_fetch_communes_by_city(PDO $pdo, int $cityId): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM geo_communes WHERE city_id = ? ORDER BY name ASC');
    $stmt->execute([$cityId]);
    return $stmt->fetchAll();
}

function cl_render_city_picker(PDO $pdo, string $countryCode = '', int $selectedCityId = 0): void
{
    $countries = cl_fetch_countries($pdo);
    $selectedCity = $selectedCityId > 0 ? cl_fetch_city_by_id($pdo, $selectedCityId) : null;
    echo '<div class="space-y-4 rounded-3xl border border-slate-200 bg-white/80 p-4">';
    echo '<div>'; 
    echo '<label class="mb-2 block text-sm font-bold text-slate-700">Pays</label>';
    echo '<select name="country_code" id="country_code" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium outline-none focus:border-primary" hx-get="maison_create.php?fragment=city-selector" hx-target="#city-selector" hx-include="#country_code">';
    echo '<option value="">Sélectionner un pays</option>';
    foreach ($countries as $country) {
        $selected = strtoupper($countryCode) === strtoupper((string) $country['code']) ? ' selected' : '';
        echo '<option value="' . cl_escape((string) $country['code']) . '"' . $selected . '>' . cl_escape((string) $country['name']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div id="city-selector">';
    cl_render_city_search_fragment($pdo, $countryCode, $selectedCity);
    echo '</div>';
    echo '<div id="commune-selector">';
    cl_render_commune_fragment($pdo, $selectedCity ? (int) $selectedCity['id'] : 0, 0);
    echo '</div>';
    echo '</div>';
}

function cl_render_city_search_fragment(PDO $pdo, string $countryCode, ?array $selectedCity = null): void
{
    echo '<div class="space-y-3">';
    echo '<label class="block text-sm font-bold text-slate-700">Ville</label>';
    if ($countryCode === '') {
        echo '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">Choisissez d’abord un pays.</div>';
        echo '<input type="hidden" name="city_id" value="0">';
        echo '</div>';
        return;
    }
    echo '<input type="search" name="city_query" value="' . cl_escape((string) ($selectedCity['name'] ?? '')) . '" placeholder="Rechercher une ville réelle" autocomplete="off" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium outline-none focus:border-primary" hx-get="maison_create.php?fragment=city-results" hx-trigger="keyup changed delay:250ms, search" hx-target="#city-results" hx-include="#country_code, [name=city_query]">';
    echo '<input type="hidden" name="city_id" id="city_id" value="' . (int) ($selectedCity['id'] ?? 0) . '">';
    echo '<div id="city-results">';
    cl_render_city_results($pdo, $countryCode, (string) ($selectedCity['name'] ?? ''));
    echo '</div>';
    echo '</div>';
}

function cl_render_city_results(PDO $pdo, string $countryCode, string $query): void
{
    $countryCode = strtoupper(trim($countryCode));
    $query = trim($query);
    if ($countryCode === '') {
        echo '<div class="text-sm text-slate-500">Aucun pays sélectionné.</div>';
        return;
    }
    $stmt = $pdo->prepare('SELECT id, name FROM geo_cities WHERE country_code = ? AND name LIKE ? ORDER BY population DESC, name ASC LIMIT 8');
    $stmt->execute([$countryCode, '%' . $query . '%']);
    $cities = $stmt->fetchAll();
    if (!$cities) {
        echo '<div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">Aucune ville réelle trouvée pour cette recherche.</div>';
        return;
    }
    echo '<div class="grid gap-2">';
    foreach ($cities as $city) {
        echo '<button type="button" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:border-primary hover:bg-blue-50" onclick="document.getElementById(\'city_id\').value=' . (int) $city['id'] . ';this.closest(\'.space-y-3\').querySelector(\'[name=city_query]\').value=' . json_encode((string) $city['name']) . ';" hx-get="maison_create.php?fragment=commune-selector" hx-target="#commune-selector" hx-include="#country_code,#city_id">' . cl_escape((string) $city['name']) . '</button>';
    }
    echo '</div>';
}

function cl_render_commune_fragment(PDO $pdo, int $cityId, int $selectedCommuneId = 0): void
{
    echo '<div class="space-y-3">';
    echo '<label class="block text-sm font-bold text-slate-700">Commune</label>';
    if ($cityId <= 0) {
        echo '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">Sélectionnez une ville réelle pour afficher ses communes.</div>';
        echo '<input type="hidden" name="commune_id" value="0">';
        echo '</div>';
        return;
    }
    $communes = cl_fetch_communes_by_city($pdo, $cityId);
    if (!$communes) {
        echo '<div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">Aucune commune n’est disponible pour cette ville.</div>';
        echo '<input type="hidden" name="commune_id" value="0">';
        echo '</div>';
        return;
    }
    echo '<select name="commune_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium outline-none focus:border-primary">';
    echo '<option value="0">Choisir une commune</option>';
    foreach ($communes as $commune) {
        $selected = (int) $commune['id'] === $selectedCommuneId ? ' selected' : '';
        echo '<option value="' . (int) $commune['id'] . '"' . $selected . '>' . cl_escape((string) $commune['name']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

function cl_fetch_dashboard_stats(PDO $pdo, int $userId): array
{
    $stats = [
        'houses_owned' => 0,
        'houses_rented' => 0,
        'active_locations' => 0,
        'payments_paid' => 0,
        'payments_pending' => 0,
    ];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM houses WHERE owner_user_id = ?');
    $stmt->execute([$userId]);
    $stats['houses_owned'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT house_id) FROM rentals WHERE tenant_user_id = ? AND status = "active" AND validation_status = "validated"');
    $stmt->execute([$userId]);
    $stats['houses_rented'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rentals WHERE (tenant_user_id = ? OR landlord_user_id = ?) AND status = "active" AND validation_status = "validated"');
    $stmt->execute([$userId, $userId]);
    $stats['active_locations'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT payment_status, COUNT(*) AS total FROM rental_payments rp INNER JOIN rentals r ON r.id = rp.rental_id WHERE (r.tenant_user_id = ? OR r.landlord_user_id = ?) AND r.validation_status = "validated" GROUP BY payment_status');
    $stmt->execute([$userId, $userId]);
    foreach ($stmt->fetchAll() as $row) {
        if (($row['payment_status'] ?? '') === 'paid') {
            $stats['payments_paid'] = (int) $row['total'];
        }
        if (($row['payment_status'] ?? '') === 'pending') {
            $stats['payments_pending'] = (int) $row['total'];
        }
    }

    return $stats;
}

function cl_fetch_houses(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT h.*, gc.name AS city_name, gm.name AS commune_name, gco.name AS country_name,
                (SELECT image_path FROM house_images hi WHERE hi.house_id = h.id ORDER BY hi.is_primary DESC, hi.id ASC LIMIT 1) AS cover_image
         FROM houses h
         INNER JOIN geo_cities gc ON gc.id = h.city_id
         INNER JOIN geo_communes gm ON gm.id = h.commune_id
         INNER JOIN geo_countries gco ON gco.code = h.country_code
         WHERE h.owner_user_id = ? OR EXISTS (
            SELECT 1 FROM rentals r WHERE r.house_id = h.id AND r.tenant_user_id = ? AND r.status = "active" AND r.validation_status = "validated"
         )
         ORDER BY h.created_at DESC'
    );
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

function cl_fetch_house_details(PDO $pdo, int $houseId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT h.*, owner.full_name AS owner_name, gc.name AS city_name, gm.name AS commune_name, gco.name AS country_name
         FROM houses h
         INNER JOIN users owner ON owner.id = h.owner_user_id
         INNER JOIN geo_cities gc ON gc.id = h.city_id
         INNER JOIN geo_communes gm ON gm.id = h.commune_id
         INNER JOIN geo_countries gco ON gco.code = h.country_code
         WHERE h.id = ? AND (
            h.owner_user_id = ? OR h.status = "libre" OR EXISTS (
                SELECT 1 FROM rentals r WHERE r.house_id = h.id AND (r.tenant_user_id = ? OR r.landlord_user_id = ?)
            )
         )
         LIMIT 1'
    );
    $stmt->execute([$houseId, $userId, $userId, $userId]);
    $house = $stmt->fetch();
    if (!$house) {
        return null;
    }
    $imgStmt = $pdo->prepare('SELECT id, image_path, is_primary FROM house_images WHERE house_id = ? ORDER BY is_primary DESC, id ASC');
    $imgStmt->execute([$houseId]);
    $house['images'] = $imgStmt->fetchAll();
    return $house;
}

function cl_create_house(PDO $pdo, int $ownerUserId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO houses (owner_user_id, title, description, monthly_rent, country_code, city_id, commune_id, avenue, house_number, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
    );
    $stmt->execute([
        $ownerUserId,
        $data['title'],
        $data['description'],
        $data['monthly_rent'],
        $data['country_code'],
        $data['city_id'],
        $data['commune_id'],
        $data['avenue'],
        $data['house_number'],
        $data['status'],
    ]);
    return (int) $pdo->lastInsertId();
}

function cl_attach_house_images(PDO $pdo, int $houseId, array $images): void
{
    $stmt = $pdo->prepare('INSERT INTO house_images (house_id, image_path, is_primary) VALUES (?, ?, ?)');
    foreach ($images as $index => $image) {
        $stmt->execute([$houseId, $image, $index === 0 ? 1 : 0]);
    }
}

function cl_store_uploaded_house_images(array $files, string $userCode): array
{
    $stored = [];
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $stored;
    }
    $baseDir = __DIR__ . '/images';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }
    $safeCode = preg_replace('/[^a-zA-Z0-9]/', '', $userCode);
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $tmp = (string) $files['tmp_name'][$i];
        $original = (string) $files['name'][$i];
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }
        $filename = 'images/' . $safeCode . '_tmp_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $absolute = __DIR__ . '/' . $filename;
        if (move_uploaded_file($tmp, $absolute)) {
            $stored[] = $filename;
        }
    }
    return $stored;
}

function cl_rename_house_images(array $images, string $userCode, int $houseId): array
{
    $safeCode = preg_replace('/[^a-zA-Z0-9]/', '', $userCode);
    $renamed = [];
    $baseDir = __DIR__ . '/images';
    foreach ($images as $index => $image) {
        $oldAbsolute = __DIR__ . '/' . $image;
        if (!file_exists($oldAbsolute)) {
            $renamed[] = $image;
            continue;
        }
        $ext = pathinfo($oldAbsolute, PATHINFO_EXTENSION);
        $newName = 'images/' . strtolower($safeCode) . '_maison' . $houseId . '_' . ($index + 1) . '.' . $ext;
        $newAbsolute = __DIR__ . '/' . $newName;
        if (rename($oldAbsolute, $newAbsolute)) {
            $renamed[] = $newName;
        } else {
            $renamed[] = $image;
        }
    }
    return $renamed;
}

function cl_delete_uploaded_files(array $files): void
{
    foreach ($files as $file) {
        $file = trim((string) $file);
        if ($file === '') {
            continue;
        }

        $absolute = __DIR__ . '/' . ltrim($file, '/\\');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}

function cl_fetch_available_houses(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT h.id, h.title, h.monthly_rent, h.country_code, gc.name AS city_name, gm.name AS commune_name, owner.full_name AS owner_name,
                (SELECT image_path FROM house_images hi WHERE hi.house_id = h.id ORDER BY hi.is_primary DESC, hi.id ASC LIMIT 1) AS cover_image
         FROM houses h
         INNER JOIN users owner ON owner.id = h.owner_user_id
         INNER JOIN geo_cities gc ON gc.id = h.city_id
         INNER JOIN geo_communes gm ON gm.id = h.commune_id
         WHERE h.status = "libre" AND h.owner_user_id <> ?
           AND NOT EXISTS (
                SELECT 1 FROM rentals r
                WHERE r.house_id = h.id
                  AND r.status = "active"
                  AND r.validation_status IN ("pending", "validated")
           )
         ORDER BY h.created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function cl_search_available_houses(PDO $pdo, int $userId, array $filters): array
{
    $sql = 'SELECT h.id, h.title, h.description, h.monthly_rent, h.country_code, h.avenue, h.house_number,
                   gc.name AS city_name, gm.name AS commune_name, gco.name AS country_name, owner.full_name AS owner_name,
                   (SELECT image_path FROM house_images hi WHERE hi.house_id = h.id ORDER BY hi.is_primary DESC, hi.id ASC LIMIT 1) AS cover_image
            FROM houses h
            INNER JOIN users owner ON owner.id = h.owner_user_id
            INNER JOIN geo_cities gc ON gc.id = h.city_id
            INNER JOIN geo_communes gm ON gm.id = h.commune_id
            INNER JOIN geo_countries gco ON gco.code = h.country_code
            WHERE h.status = "libre"
              AND h.owner_user_id <> ?
              AND NOT EXISTS (
                    SELECT 1 FROM rentals r
                    WHERE r.house_id = h.id
                      AND r.status = "active"
                      AND r.validation_status IN ("pending", "validated")
              )';

    $params = [$userId];

    $query = trim((string) ($filters['query'] ?? ''));
    if ($query !== '') {
        $sql .= ' AND (
                    h.title LIKE ?
                    OR h.description LIKE ?
                    OR h.avenue LIKE ?
                    OR gc.name LIKE ?
                    OR gm.name LIKE ?
                    OR gco.name LIKE ?
                    OR owner.full_name LIKE ?
                )';
        $like = '%' . $query . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    $city = trim((string) ($filters['city'] ?? ''));
    if ($city !== '') {
        $sql .= ' AND gc.name LIKE ?';
        $params[] = '%' . $city . '%';
    }

    $commune = trim((string) ($filters['commune'] ?? ''));
    if ($commune !== '') {
        $sql .= ' AND gm.name LIKE ?';
        $params[] = '%' . $commune . '%';
    }

    $minRent = (float) ($filters['min_rent'] ?? 0);
    if ($minRent > 0) {
        $sql .= ' AND h.monthly_rent >= ?';
        $params[] = $minRent;
    }

    $maxRent = (float) ($filters['max_rent'] ?? 0);
    if ($maxRent > 0) {
        $sql .= ' AND h.monthly_rent <= ?';
        $params[] = $maxRent;
    }

    $sql .= ' ORDER BY h.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function cl_generate_payment_schedule(PDO $pdo, int $rentalId, string $startDate, string $endDate, float $monthlyRent): void
{
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $current = $start->modify('first day of this month');
    $endMarker = $end->modify('first day of this month');
    $stmt = $pdo->prepare(
        'INSERT INTO rental_payments (rental_id, due_month, due_date, amount_due, payment_status) VALUES (?, ?, ?, ?, "pending")'
    );

    while ($current <= $endMarker) {
        $dueDate = $current->setDate((int) $current->format('Y'), (int) $current->format('m'), min(5, (int) $current->format('t')));
        $stmt->execute([
            $rentalId,
            $current->format('Y-m-01'),
            $dueDate->format('Y-m-d'),
            $monthlyRent,
        ]);
        $current = $current->modify('+1 month');
    }
}

function cl_create_rental(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO rentals (house_id, landlord_user_id, tenant_user_id, start_date, end_date, monthly_rent, status, validation_status, created_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['house_id'],
        $data['landlord_user_id'],
        $data['tenant_user_id'],
        $data['start_date'],
        $data['end_date'],
        $data['monthly_rent'],
        $data['status'],
        $data['validation_status'],
        $data['created_by_user_id'],
    ]);
    return (int) $pdo->lastInsertId();
}

function cl_fetch_pending_rentals(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, h.title AS house_title, landlord.full_name AS landlord_name, tenant.full_name AS tenant_name
         FROM rentals r
         INNER JOIN houses h ON h.id = r.house_id
         INNER JOIN users landlord ON landlord.id = r.landlord_user_id
         INNER JOIN users tenant ON tenant.id = r.tenant_user_id
         WHERE r.validation_status = "pending" AND (r.landlord_user_id = ? OR r.tenant_user_id = ?)
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

function cl_fetch_rentals_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, h.title AS house_title, landlord.full_name AS landlord_name, tenant.full_name AS tenant_name
         FROM rentals r
         INNER JOIN houses h ON h.id = r.house_id
         INNER JOIN users landlord ON landlord.id = r.landlord_user_id
         INNER JOIN users tenant ON tenant.id = r.tenant_user_id
         WHERE r.landlord_user_id = ? OR r.tenant_user_id = ?
         ORDER BY FIELD(r.status, "active", "ended"), r.created_at DESC'
    );
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

function cl_fetch_payment_rows(PDO $pdo, int $userId, int $houseId = 0): array
{
    $sql = 'SELECT rp.id, rp.rental_id, rp.due_month, rp.due_date, rp.amount_due, rp.amount_paid, rp.payment_status, rp.paid_at,
                   h.id AS house_id,
                   h.title AS house_title, landlord.full_name AS landlord_name, tenant.full_name AS tenant_name
            FROM rental_payments rp
            INNER JOIN rentals r ON r.id = rp.rental_id
            INNER JOIN houses h ON h.id = r.house_id
            INNER JOIN users landlord ON landlord.id = r.landlord_user_id
            INNER JOIN users tenant ON tenant.id = r.tenant_user_id
            WHERE (r.landlord_user_id = ? OR r.tenant_user_id = ?)
              AND r.validation_status = "validated"';

    $params = [$userId, $userId];
    if ($houseId > 0) {
        $sql .= ' AND h.id = ?';
        $params[] = $houseId;
    }

    $sql .= ' ORDER BY rp.due_month DESC, rp.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function cl_fetch_payment_by_id(PDO $pdo, int $paymentId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT rp.*, r.house_id, r.landlord_user_id, r.tenant_user_id, h.title AS house_title, landlord.full_name AS landlord_name, tenant.full_name AS tenant_name
         FROM rental_payments rp
         INNER JOIN rentals r ON r.id = rp.rental_id
         INNER JOIN houses h ON h.id = r.house_id
         INNER JOIN users landlord ON landlord.id = r.landlord_user_id
         INNER JOIN users tenant ON tenant.id = r.tenant_user_id
         WHERE rp.id = ? AND (r.landlord_user_id = ? OR r.tenant_user_id = ?) AND r.validation_status = "validated"
         LIMIT 1'
    );
    $stmt->execute([$paymentId, $userId, $userId]);
    $payment = $stmt->fetch();
    return $payment ?: null;
}

function cl_mark_payment_paid(PDO $pdo, int $paymentId, float $amountPaid): void
{
    $stmt = $pdo->prepare('UPDATE rental_payments SET payment_status = "paid", amount_paid = ?, paid_at = NOW() WHERE id = ?');
    $stmt->execute([$amountPaid, $paymentId]);
}

function cl_format_currency(float $amount): string
{
    return number_format($amount, 2, ',', ' ') . ' USD';
}

function cl_house_cover_src(?string $path, string $label = 'Maison'): string
{
    $path = trim((string) $path);
    if ($path !== '') {
        return $path;
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480" viewBox="0 0 640 480"><rect width="640" height="480" rx="32" fill="#e2e8f0"/><rect x="110" y="180" width="420" height="190" rx="24" fill="#cbd5e1"/><path d="M160 210L320 90l160 120" fill="#93c5fd"/><rect x="190" y="255" width="90" height="115" rx="14" fill="#f8fafc"/><rect x="340" y="245" width="120" height="80" rx="14" fill="#f8fafc"/><text x="320" y="430" text-anchor="middle" font-size="28" font-family="Arial" fill="#334155">' . cl_escape($label) . '</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function cl_count_payments_by_status(array $payments): array
{
    $summary = ['paid' => 0, 'pending' => 0];
    foreach ($payments as $payment) {
        $status = $payment['payment_status'] ?? 'pending';
        if (!isset($summary[$status])) {
            $summary[$status] = 0;
        }
        $summary[$status]++;
    }
    return $summary;
}

function cl_render_payment_table_component(array $payments, int $currentHouseId = 0): void
{
    $summary = cl_count_payments_by_status($payments);
    $json = json_encode(array_values($payments), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    if ($json === false) {
        $json = '[]';
    }
    echo '<div id="payments-table-root" class="space-y-6" v-cloak>';
    echo '<div class="grid gap-4 md:grid-cols-3">';
    echo '<div class="rounded-3xl bg-white p-5 shadow-soft"><p class="text-xs font-black uppercase tracking-[0.28em] text-slate-400">Total</p><p class="mt-3 text-3xl font-black text-slate-900">{{ payments.length }}</p></div>';
    echo '<div class="rounded-3xl bg-emerald-50 p-5 shadow-soft"><p class="text-xs font-black uppercase tracking-[0.28em] text-emerald-500">Payés</p><p class="mt-3 text-3xl font-black text-emerald-700">{{ counts.paid }}</p></div>';
    echo '<div class="rounded-3xl bg-amber-50 p-5 shadow-soft"><p class="text-xs font-black uppercase tracking-[0.28em] text-amber-500">En attente</p><p class="mt-3 text-3xl font-black text-amber-700">{{ counts.pending }}</p></div>';
    echo '</div>';
    echo '<div class="rounded-[2rem] bg-white p-5 shadow-soft">';
    echo '<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">';
    echo '<div><p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Tableau dynamique</p><h3 class="mt-2 text-xl font-black">Suivi mensuel des paiements</h3></div>';
    echo '<div class="flex flex-wrap items-center gap-3">';
    echo '<input v-model="search" type="search" placeholder="Filtrer par maison ou participant" class="w-72 rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-primary">';
    echo '<select v-model="filterStatus" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold outline-none focus:border-primary"><option value="all">Tous</option><option value="pending">Non payés</option><option value="paid">Payés</option></select>';
    echo '</div>';
    echo '</div>';
    echo '<div class="mt-5 overflow-x-auto">';
    echo '<table class="min-w-full divide-y divide-slate-100">';
    echo '<thead><tr class="text-left text-xs font-black uppercase tracking-[0.24em] text-slate-400"><th class="px-4 py-3">Mois</th><th class="px-4 py-3">Maison</th><th class="px-4 py-3">Participants</th><th class="px-4 py-3">Montant</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3">Action</th></tr></thead>';
    echo '<tbody class="divide-y divide-slate-100">';
    echo '<tr v-for="row in filteredPayments" :key="row.id" class="hover:bg-slate-50/80">';
    echo '<td class="px-4 py-4 text-sm font-bold text-slate-900">{{ formatMonth(row.due_month) }}<p class="mt-1 text-xs font-medium text-slate-500">Échéance {{ row.due_date }}</p></td>';
    echo '<td class="px-4 py-4 text-sm font-semibold text-slate-700">{{ row.house_title }}</td>';
    echo '<td class="px-4 py-4 text-sm text-slate-600"><p><span class="font-bold">Bailleur :</span> {{ row.landlord_name }}</p><p class="mt-1"><span class="font-bold">Locataire :</span> {{ row.tenant_name }}</p></td>';
    echo '<td class="px-4 py-4 text-sm font-black text-slate-900">{{ formatCurrency(row.amount_due) }}</td>';
    echo '<td class="px-4 py-4"><span :class="row.payment_status === \"paid\" ? \"bg-emerald-100 text-emerald-700\" : \"bg-amber-100 text-amber-700\"" class="inline-flex rounded-full px-3 py-2 text-xs font-black uppercase tracking-[0.2em]">{{ row.payment_status === "paid" ? "Payé" : "Non payé" }}</span></td>';
    echo '<td class="px-4 py-4">';
    echo '<div class="flex flex-wrap items-center gap-2">';
    echo '<a :href="`recu.php?id=${row.id}`" class="rounded-full bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-slate-700 transition hover:bg-slate-200">Reçu</a>';
    echo '<form v-if="row.payment_status !== \"paid\"" :action="`paiement_valider.php?id=${row.id}`" method="post" hx-post="paiement_valider.php" hx-target="#payments-dynamic-zone" hx-swap="innerHTML">';
    echo '<input type="hidden" name="payment_id" :value="row.id">';
    if ($currentHouseId > 0) {
        echo '<input type="hidden" name="house_id" value="' . $currentHouseId . '">';
    }
    echo '<button type="submit" class="rounded-full bg-primary px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-blue-700">Marquer payé</button>';
    echo '</form>';
    echo '<span v-else class="text-xs font-bold text-emerald-600">Validé {{ row.paid_at ?? \"\" }}</span>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
    echo '<tr v-if="filteredPayments.length === 0"><td colspan="6" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Aucun paiement ne correspond au filtre courant.</td></tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<script>';
    echo '(() => {';
    echo 'const data=' . $json . ';';
    echo 'const initialCounts=' . json_encode($summary, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';';
    echo 'const mount=document.getElementById("payments-table-root");';
    echo 'if(!mount){return;}';
    echo 'if(mount.__vue_app__){mount.__vue_app__.unmount();}';
    echo 'const app=Vue.createApp({data(){return{payments:data,search:"",filterStatus:"all",counts:{paid:initialCounts.paid||0,pending:initialCounts.pending||0}};},computed:{filteredPayments(){const search=this.search.trim().toLowerCase();return this.payments.filter((row)=>{const okStatus=this.filterStatus==="all"||row.payment_status===this.filterStatus;const blob=[row.house_title,row.landlord_name,row.tenant_name,row.due_month].join(" ").toLowerCase();const okSearch=search===""||blob.includes(search);return okStatus&&okSearch;});}},methods:{formatMonth(value){const parts=String(value||"").split("-");const year=Number(parts[0]||0);const month=Number(parts[1]||1);const date=new Date(year,Math.max(month-1,0),1);return new Intl.DateTimeFormat("fr-FR",{month:"long",year:"numeric"}).format(date);},formatCurrency(value){return new Intl.NumberFormat("fr-FR",{style:"currency",currency:"USD"}).format(Number(value||0));}},mounted(){if(window.htmx){window.htmx.process(this.$el);}}});';
    echo 'mount.__vue_app__=app;app.mount(mount);';
    echo '})();';
    echo '</script>';
}
