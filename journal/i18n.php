<?php

declare(strict_types=1);

/**
 * Internationalisation GNTOMA (fr / en).
 * Charger après session_start() sur les pages web ; appeler gntoma_init_locale_from_request() une fois.
 */

if (!function_exists('gntoma_locale')) {
    function gntoma_locale(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $allowed = ['fr', 'en'];

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['gntoma_locale'])) {
            $l = strtolower((string) $_SESSION['gntoma_locale']);
            if (in_array($l, $allowed, true)) {
                return $resolved = $l;
            }
        }

        if (!empty($_COOKIE['gntoma_locale'])) {
            $l = strtolower((string) $_COOKIE['gntoma_locale']);
            if (in_array($l, $allowed, true)) {
                return $resolved = $l;
            }
        }

        return $resolved = 'fr';
    }
}

if (!function_exists('gntoma_set_locale')) {
    function gntoma_set_locale(string $locale): void
    {
        $allowed = ['fr', 'en'];
        $l = strtolower($locale);
        if (!in_array($l, $allowed, true)) {
            $l = 'fr';
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['gntoma_locale'] = $l;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');

        setcookie('gntoma_locale', $l, [
            'expires' => time() + 365 * 24 * 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('gntoma_init_locale_from_request')) {
    /** À appeler après session_start() : applique ?lang=fr|en une fois. */
    function gntoma_init_locale_from_request(): void
    {
        if (!isset($_GET['lang'])) {
            return;
        }

        $raw = strtolower(trim((string) $_GET['lang']));
        gntoma_set_locale($raw);
    }
}

if (!function_exists('gntoma_translations')) {
    /**
     * @return array<string, mixed>
     */
    function gntoma_translations(string $locale): array
    {
        static $cache = [];

        $l = in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
        if (isset($cache[$l])) {
            return $cache[$l];
        }

        $file = __DIR__ . '/lang/' . $l . '.php';
        if (!is_readable($file)) {
            $file = __DIR__ . '/lang/fr.php';
        }

        /** @var array<string, mixed> $data */
        $data = require $file;

        return $cache[$l] = $data;
    }
}

if (!function_exists('__')) {
    /**
     * Traduction par clés à points : dashboard.title
     *
     * @param array<string, string|int|float> $replace
     */
    function __(string $key, array $replace = []): string
    {
        $parts = explode('.', $key);
        $bags = [gntoma_translations(gntoma_locale()), gntoma_translations('fr')];

        foreach ($bags as $bag) {
            $value = $bag;
            foreach ($parts as $p) {
                if (!is_array($value) || !array_key_exists($p, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$p];
            }
            if (is_string($value)) {
                $out = $value;
                foreach ($replace as $k => $v) {
                    $out = str_replace(':' . $k, (string) $v, $out);
                }

                return $out;
            }
        }

        return $key;
    }
}

if (!function_exists('gntoma_html_lang')) {
    function gntoma_html_lang(): string
    {
        return gntoma_locale() === 'en' ? 'en' : 'fr';
    }
}

if (!function_exists('gntoma_lang_same_page_href')) {
    /**
     * Lien vers la même URL avec `lang` (rechargement léger : cookie + session mis à jour par `gntoma_init_locale_from_request`).
     */
    function gntoma_lang_same_page_href(string $lang): string
    {
        $allowed = ['fr', 'en'];
        $l = strtolower($lang);
        if (!in_array($l, $allowed, true)) {
            $l = 'fr';
        }

        $q = $_GET;
        $q['lang'] = $l;

        return '?' . http_build_query($q);
    }
}

if (!function_exists('gntoma_lang_switch_markup')) {
    /**
     * Pastilles FR | EN : restent sur la page courante (pas de redirection vers journal/index.php).
     */
    function gntoma_lang_switch_markup(): string
    {
        $fr = htmlspecialchars(__('dashboard.lang_fr'), ENT_QUOTES, 'UTF-8');
        $en = htmlspecialchars(__('dashboard.lang_en'), ENT_QUOTES, 'UTF-8');
        $frActive = gntoma_locale() === 'fr' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200';
        $enActive = gntoma_locale() === 'en' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200';

        $hrefFr = htmlspecialchars(gntoma_lang_same_page_href('fr'), ENT_QUOTES, 'UTF-8');
        $hrefEn = htmlspecialchars(gntoma_lang_same_page_href('en'), ENT_QUOTES, 'UTF-8');

        return '<span class="inline-flex items-center gap-1">'
            . '<a href="' . $hrefFr . '" class="text-[10px] font-black px-2 py-1 rounded-lg ' . $frActive . '">' . $fr . '</a>'
            . '<a href="' . $hrefEn . '" class="text-[10px] font-black px-2 py-1 rounded-lg ' . $enActive . '">' . $en . '</a>'
            . '</span>';
    }
}
