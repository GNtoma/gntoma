<?php
declare(strict_types=1);

/**
 * Composant sélecteur de lieu GeoNames.
 *
 * @param array{
 *   id?: string,
 *   initial?: array<string, mixed>|null,
 *   country?: string|null,
 *   required?: bool,
 *   input_class?: string,
 * } $picker
 */
function gntoma_render_location_picker(array $picker = []): void
{
    $pickerId = preg_replace('/[^a-z0-9_-]/i', '', (string) ($picker['id'] ?? 'location')) ?: 'location';
    $initial = $picker['initial'] ?? null;
    $geonameId = is_array($initial) ? (int) ($initial['geoname_id'] ?? 0) : 0;
    $label = is_array($initial) ? trim((string) ($initial['label'] ?? '')) : '';
    $country = strtoupper(trim((string) ($picker['country'] ?? '')));
    $countryAttr = preg_match('/^[A-Z]{2}$/', $country) ? $country : '';
    $required = (bool) ($picker['required'] ?? true);
    $inputClass = trim((string) ($picker['input_class'] ?? 'w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary outline-none'));
    $minLen = GntomaGeonamesService::minQueryLength();
    $hasSelection = $geonameId > 0 && $label !== '';
    $requireHidden = $required && !($geonameId < 1 && $label !== '');
    ?>
    <div
        id="gntoma-loc-<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>"
        class="gntoma-loc<?= $hasSelection ? ' has-selection' : '' ?>"
        data-gntoma-location-picker
        data-initial-geoname-id="<?= $geonameId > 0 ? (int) $geonameId : '' ?>"
        data-search-url="geonames/search.php"
        data-min-len="<?= (int) $minLen ?>"
        <?= $countryAttr !== '' ? 'data-country="' . htmlspecialchars($countryAttr, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
        data-msg-empty="<?= htmlspecialchars(__('geonames.no_results'), ENT_QUOTES, 'UTF-8') ?>"
        data-msg-error="<?= htmlspecialchars(__('geonames.error_api'), ENT_QUOTES, 'UTF-8') ?>"
        data-msg-network="<?= htmlspecialchars(__('geonames.error_network'), ENT_QUOTES, 'UTF-8') ?>"
        data-msg-selected="<?= htmlspecialchars(__('geonames.selected'), ENT_QUOTES, 'UTF-8') ?>"
        data-msg-hint="<?= htmlspecialchars(__('geonames.hint'), ENT_QUOTES, 'UTF-8') ?>"
        role="combobox"
        aria-expanded="false"
        aria-haspopup="listbox"
    >
        <input type="hidden" name="location_geoname_id" data-gntoma-loc-id value="<?= $geonameId > 0 ? (int) $geonameId : '' ?>"<?= $requireHidden ? ' required' : '' ?>>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-2" for="gntoma-loc-input-<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(__('profile_edit.location_place'), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <div class="gntoma-loc__input-wrap">
            <input
                type="text"
                id="gntoma-loc-input-<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>"
                data-gntoma-loc-input
                data-initial-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
                placeholder="<?= htmlspecialchars(__('profile_edit.location_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                class="<?= htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') ?>"
                aria-autocomplete="list"
                aria-controls="gntoma-loc-list-<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>"
            >
            <span class="gntoma-loc__loader" data-gntoma-loc-loader aria-hidden="true"></span>
        </div>
        <div
            id="gntoma-loc-list-<?= htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8') ?>"
            class="gntoma-loc__list"
            data-gntoma-loc-list
            role="listbox"
        ></div>
        <p class="gntoma-loc__selected" data-gntoma-loc-selected></p>
    </div>
    <?php
}
