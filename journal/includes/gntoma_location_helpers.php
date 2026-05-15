<?php
declare(strict_types=1);

/**
 * Helpers localisation GeoNames — validation serveur uniquement.
 */

if (!function_exists('gntoma_geonames_username')) {
    function gntoma_geonames_username(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $fromEnv = trim((string) (getenv('GNTOMA_GEONAMES_USERNAME') ?: ''));
        if ($fromEnv !== '') {
            return $resolved = $fromEnv;
        }

        $var = '';
        $secretsLocal = __DIR__ . '/../secrets.local.php';
        if (is_readable($secretsLocal)) {
            $GNTOMA_GEONAMES_USERNAME = '';
            require $secretsLocal;
            $var = trim((string) ($GNTOMA_GEONAMES_USERNAME ?? ''));
        }

        return $resolved = $var;
    }
}

if (!function_exists('gntoma_geonames_service')) {
    function gntoma_geonames_service(): ?GntomaGeonamesService
    {
        static $instance = null;
        static $failed = false;

        if ($failed) {
            return null;
        }
        if ($instance instanceof GntomaGeonamesService) {
            return $instance;
        }

        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $failed = true;

            return null;
        }

        $user = gntoma_geonames_username();
        if ($user === '') {
            error_log('GNTOMA GeoNames: GNTOMA_GEONAMES_USERNAME manquant (secrets.local.php ou variable d’environnement).');
            $failed = true;

            return null;
        }

        try {
            $instance = new GntomaGeonamesService($pdo, $user);

            return $instance;
        } catch (Throwable $e) {
            error_log('GNTOMA GeoNames service: ' . $e->getMessage());
            $failed = true;

            return null;
        }
    }
}

if (!function_exists('gntoma_users_has_geonames_columns')) {
    function gntoma_users_has_geonames_columns(PDO $pdo): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'location_geoname_id'");
            $has = $stmt !== false && $stmt->fetch() !== false;
        } catch (Throwable $e) {
            $has = false;
        }

        return $has;
    }
}

if (!function_exists('gntoma_location_from_request')) {
    /**
     * Valide location_geoname_id POST — seule source acceptée pour une localisation.
     *
     * @return array{ok: bool, error?: string, place?: array<string, mixed>}
     */
    function gntoma_location_from_request(array $source): array
    {
        $geonameId = (int) ($source['location_geoname_id'] ?? 0);
        if ($geonameId < 1) {
            return ['ok' => false, 'error' => 'required'];
        }

        $service = gntoma_geonames_service();
        if ($service === null) {
            return ['ok' => false, 'error' => 'unavailable'];
        }

        $place = $service->resolveGeonameId($geonameId);
        if ($place === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        return ['ok' => true, 'place' => $place];
    }
}

if (!function_exists('gntoma_user_location_from_row')) {
    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    function gntoma_user_location_from_row(array $user): ?array
    {
        $gid = (int) ($user['location_geoname_id'] ?? 0);
        if ($gid < 1) {
            return null;
        }

        return [
            'geoname_id' => $gid,
            'name' => (string) ($user['location_name'] ?? ''),
            'admin1' => $user['location_admin1'] ?? null,
            'admin2' => $user['location_admin2'] ?? null,
            'country_code' => (string) ($user['location_country_code'] ?? ''),
            'country_name' => (string) ($user['location_country_name'] ?? ''),
            'lat' => isset($user['location_lat']) ? (float) $user['location_lat'] : null,
            'lng' => isset($user['location_lng']) ? (float) $user['location_lng'] : null,
            'population' => isset($user['location_population']) ? (int) $user['location_population'] : null,
            'label' => gntoma_location_label_from_parts(
                (string) ($user['location_name'] ?? ''),
                $user['location_admin1'] ?? null,
                $user['location_admin2'] ?? null,
                (string) ($user['location_country_name'] ?? '')
            ),
        ];
    }
}

if (!function_exists('gntoma_location_label_from_parts')) {
    function gntoma_location_label_from_parts(string $name, ?string $admin1, ?string $admin2, string $countryName): string
    {
        $parts = [trim($name)];
        if ($admin2 !== null && trim($admin2) !== '' && strcasecmp(trim($admin2), $parts[0]) !== 0) {
            $parts[] = trim($admin2);
        } elseif ($admin1 !== null && trim($admin1) !== '' && strcasecmp(trim($admin1), $parts[0]) !== 0) {
            $parts[] = trim($admin1);
        }
        if ($countryName !== '') {
            $parts[] = trim($countryName);
        }

        return implode(', ', array_filter($parts, static fn (string $p): bool => $p !== ''));
    }
}

if (!function_exists('gntoma_place_from_user_row')) {
    /**
     * Lieu dérivé des colonnes users (legacy ou déjà enregistré).
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    function gntoma_place_from_user_row(array $user): ?array
    {
        $city = trim((string) ($user['city'] ?? ''));
        if ($city === '') {
            return null;
        }

        $country = trim((string) ($user['country'] ?? ''));
        $admin2 = isset($user['commune']) && $user['commune'] !== '' ? (string) $user['commune'] : null;

        return [
            'geoname_id' => (int) ($user['location_geoname_id'] ?? 0),
            'name' => $city,
            'admin1' => null,
            'admin2' => $admin2,
            'country_code' => '',
            'country_name' => $country !== '' ? $country : 'RDC',
            'lat' => isset($user['location_lat']) ? (float) $user['location_lat'] : 0.0,
            'lng' => isset($user['location_lng']) ? (float) $user['location_lng'] : 0.0,
            'population' => isset($user['location_population']) ? (int) $user['location_population'] : null,
            'label' => gntoma_location_label_from_parts($city, null, $admin2, $country !== '' ? $country : 'RDC'),
        ];
    }
}

if (!function_exists('gntoma_profile_resolve_location_for_save')) {
    /**
     * Résout la localisation à enregistrer (GeoNames ou conservation de l’existant).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string, place?: array<string, mixed>}
     */
    function gntoma_profile_resolve_location_for_save(array $user, array $post): array
    {
        $geonameId = (int) ($post['location_geoname_id'] ?? 0);
        if ($geonameId < 1) {
            $geonameId = (int) ($user['location_geoname_id'] ?? 0);
        }

        if ($geonameId > 0) {
            $validated = gntoma_location_from_request(['location_geoname_id' => $geonameId]);
            if ($validated['ok']) {
                return $validated;
            }
            if (($validated['error'] ?? '') !== 'unavailable') {
                return $validated;
            }
        }

        $fromRow = gntoma_place_from_user_row($user);
        if ($fromRow !== null && trim((string) $fromRow['name']) !== '') {
            return ['ok' => true, 'place' => $fromRow];
        }

        if ($geonameId > 0) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        return ['ok' => false, 'error' => 'required'];
    }
}

if (!function_exists('gntoma_apply_user_location')) {
    /**
     * @param array<string, mixed> $place
     */
    function gntoma_apply_user_location(PDO $pdo, string $userCode, array $place): bool
    {
        if (!gntoma_users_has_geonames_columns($pdo)) {
            return false;
        }

        $geonameId = (int) ($place['geoname_id'] ?? 0);
        if ($geonameId < 1) {
            return false;
        }

        $cityLegacy = (string) $place['name'];
        $communeLegacy = $place['admin2'] ?? $place['admin1'] ?? null;
        $countryLegacy = (string) $place['country_name'];

        $stmt = $pdo->prepare('
            UPDATE users SET
                location_geoname_id = ?,
                location_name = ?,
                location_admin1 = ?,
                location_admin2 = ?,
                location_country_code = ?,
                location_country_name = ?,
                location_lat = ?,
                location_lng = ?,
                location_population = ?,
                city = ?,
                commune = ?,
                country = ?
            WHERE UPPER(TRIM(user_code)) = ?
        ');

        return $stmt->execute([
            (int) $place['geoname_id'],
            (string) $place['name'],
            $place['admin1'],
            $place['admin2'],
            (string) $place['country_code'],
            (string) $place['country_name'],
            (float) $place['lat'],
            (float) $place['lng'],
            $place['population'],
            $cityLegacy,
            $communeLegacy,
            $countryLegacy,
            gntoma_normalize_user_code($userCode) ?? $userCode,
        ]);
    }
}
