<?php
declare(strict_types=1);

/**
 * Client serveur GeoNames (searchJSON / getJSON).
 * Le username ne doit jamais être exposé au navigateur.
 */
final class GntomaGeonamesService
{
    private const API_BASE = 'https://secure.geonames.org';
    private const SEARCH_PATH = '/searchJSON';
    private const GET_PATH = '/getJSON';
    private const TIMEOUT_SEC = 5;
    private const CONNECT_TIMEOUT_SEC = 3;
    private const MAX_ROWS_DEFAULT = 10;
    private const MIN_QUERY_LEN = 2;
    private const MAX_QUERY_LEN = 120;

    private string $username;
    private PDO $pdo;
    private int $rateLimitPerMinute;

    public function __construct(PDO $pdo, string $username, int $rateLimitPerMinute = 40)
    {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('GeoNames username is required');
        }
        $this->pdo = $pdo;
        $this->username = $username;
        $this->rateLimitPerMinute = max(5, $rateLimitPerMinute);
    }

    public static function minQueryLength(): int
    {
        return self::MIN_QUERY_LEN;
    }

    /**
     * @return array{ok: bool, error?: string, results?: list<array<string, mixed>>}
     */
    public function search(string $query, ?string $countryCode = null, int $maxRows = self::MAX_ROWS_DEFAULT): array
    {
        $q = $this->sanitizeQuery($query);
        if ($q === null) {
            return ['ok' => true, 'results' => []];
        }

        if (!$this->checkRateLimit()) {
            return ['ok' => false, 'error' => 'rate_limit'];
        }

        $params = [
            'q' => $q,
            'username' => $this->username,
            'featureClass' => 'P',
            'orderby' => 'population',
            'style' => 'FULL',
            'maxRows' => (string) min(20, max(1, $maxRows)),
            'lang' => gntoma_locale() === 'en' ? 'en' : 'fr',
        ];
        if ($countryCode !== null && preg_match('/^[A-Z]{2}$/', $countryCode)) {
            $params['country'] = $countryCode;
        }

        $payload = $this->httpGet(self::SEARCH_PATH, $params);
        if ($payload === null) {
            return ['ok' => false, 'error' => 'network'];
        }

        if (isset($payload['status']['message'])) {
            error_log('GeoNames search error: ' . (string) $payload['status']['message']);

            return ['ok' => false, 'error' => 'api'];
        }

        $geonames = $payload['geonames'] ?? [];
        if (!is_array($geonames)) {
            return ['ok' => true, 'results' => []];
        }

        $results = [];
        foreach ($geonames as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeFromApi($row);
            if ($normalized !== null) {
                $this->cachePlace($normalized);
                $results[] = $normalized;
            }
        }

        return ['ok' => true, 'results' => $results];
    }

    /**
     * Valide et normalise un geonameId (getJSON + cache).
     *
     * @return array<string, mixed>|null
     */
    public function resolveGeonameId(int $geonameId): ?array
    {
        if ($geonameId < 1) {
            return null;
        }

        $cached = $this->fetchFromCache($geonameId);
        if ($cached !== null) {
            return $cached;
        }

        if (!$this->checkRateLimit()) {
            return null;
        }

        $payload = $this->httpGet(self::GET_PATH, [
            'geonameId' => (string) $geonameId,
            'username' => $this->username,
            'style' => 'FULL',
        ]);
        if ($payload === null || !is_array($payload)) {
            return null;
        }

        if (isset($payload['status']['message'])) {
            return null;
        }

        $normalized = $this->normalizeFromApi($payload);
        if ($normalized === null) {
            return null;
        }

        $this->cachePlace($normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function normalizeFromApi(array $row): ?array
    {
        $geonameId = (int) ($row['geonameId'] ?? 0);
        if ($geonameId < 1) {
            return null;
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $lat = isset($row['lat']) ? (float) $row['lat'] : null;
        $lng = isset($row['lng']) ? (float) $row['lng'] : null;
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        $countryCode = strtoupper(trim((string) ($row['countryCode'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return null;
        }

        $countryName = trim((string) ($row['countryName'] ?? ''));
        $admin1 = trim((string) ($row['adminName1'] ?? ''));
        $admin2 = trim((string) ($row['adminName2'] ?? ''));
        $population = isset($row['population']) && $row['population'] !== '' ? (int) $row['population'] : null;

        $labelParts = [$name];
        if ($admin1 !== '' && strcasecmp($admin1, $name) !== 0) {
            $labelParts[] = $admin1;
        }
        if ($countryName !== '') {
            $labelParts[] = $countryName;
        }
        $label = implode(', ', $labelParts);

        return [
            'geoname_id' => $geonameId,
            'name' => $name,
            'admin1' => $admin1 !== '' ? $admin1 : null,
            'admin2' => $admin2 !== '' ? $admin2 : null,
            'country_code' => $countryCode,
            'country_name' => $countryName !== '' ? $countryName : $countryCode,
            'lat' => $lat,
            'lng' => $lng,
            'population' => $population !== null && $population > 0 ? $population : null,
            'feature_class' => (string) ($row['fcl'] ?? 'P'),
            'feature_code' => (string) ($row['fcode'] ?? ''),
            'label' => $label,
        ];
    }

    /**
     * @param array<string, mixed> $place
     */
    public function cachePlace(array $place): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO geonames_place_cache (
                    geoname_id, name, country_code, country_name, admin1, admin2,
                    feature_class, feature_code, latitude, longitude, population, label
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    country_code = VALUES(country_code),
                    country_name = VALUES(country_name),
                    admin1 = VALUES(admin1),
                    admin2 = VALUES(admin2),
                    feature_class = VALUES(feature_class),
                    feature_code = VALUES(feature_code),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    population = VALUES(population),
                    label = VALUES(label),
                    cached_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([
                (int) $place['geoname_id'],
                (string) $place['name'],
                (string) $place['country_code'],
                (string) $place['country_name'],
                $place['admin1'],
                $place['admin2'],
                substr((string) ($place['feature_class'] ?? 'P'), 0, 1),
                substr((string) ($place['feature_code'] ?? ''), 0, 10) ?: null,
                (float) $place['lat'],
                (float) $place['lng'],
                $place['population'],
                (string) $place['label'],
            ]);
        } catch (Throwable $e) {
            error_log('geonames_place_cache: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromCache(int $geonameId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT geoname_id, name, country_code, country_name, admin1, admin2,
                       latitude AS lat, longitude AS lng, population, label,
                       feature_class, feature_code
                FROM geonames_place_cache
                WHERE geoname_id = ?
                LIMIT 1
            ');
            $stmt->execute([$geonameId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'geoname_id' => (int) $row['geoname_id'],
                'name' => (string) $row['name'],
                'admin1' => $row['admin1'] !== null ? (string) $row['admin1'] : null,
                'admin2' => $row['admin2'] !== null ? (string) $row['admin2'] : null,
                'country_code' => (string) $row['country_code'],
                'country_name' => (string) $row['country_name'],
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'population' => $row['population'] !== null ? (int) $row['population'] : null,
                'feature_class' => (string) $row['feature_class'],
                'feature_code' => (string) ($row['feature_code'] ?? ''),
                'label' => (string) $row['label'],
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private function sanitizeQuery(string $query): ?string
    {
        $q = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        if ($q === '') {
            return null;
        }
        $q = mb_substr($q, 0, self::MAX_QUERY_LEN);
        if (mb_strlen($q) < self::MIN_QUERY_LEN) {
            return null;
        }

        return $q;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>|null
     */
    private function httpGet(string $path, array $params): ?array
    {
        $url = self::API_BASE . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT_SEC,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SEC,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: GNTOMA/1.0'],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => self::TIMEOUT_SEC,
                    'header' => "Accept: application/json\r\nUser-Agent: GNTOMA/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                return null;
            }
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException $e) {
            error_log('GeoNames JSON decode: ' . $e->getMessage());

            return null;
        }
    }

    private function checkRateLimit(): bool
    {
        $clientKey = $this->clientKey();
        $window = (int) floor(time() / 60);

        try {
            $this->pdo->beginTransaction();
            $sel = $this->pdo->prepare('
                SELECT hit_count FROM geonames_api_rate
                WHERE client_key = ? AND window_minute = ?
                FOR UPDATE
            ');
            $sel->execute([$clientKey, $window]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $hits = (int) $row['hit_count'] + 1;
                if ($hits > $this->rateLimitPerMinute) {
                    $this->pdo->rollBack();

                    return false;
                }
                $upd = $this->pdo->prepare('
                    UPDATE geonames_api_rate SET hit_count = ? WHERE client_key = ? AND window_minute = ?
                ');
                $upd->execute([$hits, $clientKey, $window]);
            } else {
                $ins = $this->pdo->prepare('
                    INSERT INTO geonames_api_rate (client_key, window_minute, hit_count) VALUES (?, ?, 1)
                ');
                $ins->execute([$clientKey, $window]);
            }
            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Si tables absentes, autoriser (dégradé) mais journaliser
            error_log('geonames rate limit: ' . $e->getMessage());

            return true;
        }
    }

    private function clientKey(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $sid = session_status() === PHP_SESSION_ACTIVE ? (string) session_id() : '';

        return hash('sha256', $ip . '|' . $sid);
    }
}
