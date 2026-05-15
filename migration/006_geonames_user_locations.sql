-- ============================================================
-- MIGRATION 006 : Localisations normalisées GeoNames (GNTOMA)
-- Référentiel officiel : geonameId + coordonnées GPS + hiérarchie admin
-- ============================================================

-- Cache des lieux résolus (réduit les appels API + accélère la validation)
CREATE TABLE IF NOT EXISTS geonames_place_cache (
    geoname_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    country_code CHAR(2) NOT NULL,
    country_name VARCHAR(100) NOT NULL,
    admin1 VARCHAR(120) NULL DEFAULT NULL COMMENT 'Province / région (adminName1)',
    admin2 VARCHAR(120) NULL DEFAULT NULL COMMENT 'Département / commune (adminName2)',
    feature_class CHAR(1) NOT NULL DEFAULT 'P',
    feature_code VARCHAR(10) NULL DEFAULT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    population INT UNSIGNED NULL DEFAULT NULL,
    label VARCHAR(255) NOT NULL COMMENT 'Libellé affichage normalisé',
    raw_json JSON NULL,
    cached_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (geoname_id),
    KEY idx_geonames_cache_country (country_code),
    KEY idx_geonames_cache_name (name),
    KEY idx_geonames_cache_coords (latitude, longitude),
    KEY idx_geonames_cache_population (population)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cache GeoNames — lieux validés';

-- Journalisation légère anti-abus API (par IP / fenêtre minute)
CREATE TABLE IF NOT EXISTS geonames_api_rate (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_key VARCHAR(64) NOT NULL COMMENT 'Hash IP ou session',
    window_minute INT UNSIGNED NOT NULL,
    hit_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geonames_rate_client_window (client_key, window_minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonnes utilisateur (source de vérité = GeoNames)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS location_geoname_id BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'ID GeoNames (obligatoire pour toute nouvelle localisation)',
    ADD COLUMN IF NOT EXISTS location_name VARCHAR(200) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_admin1 VARCHAR(120) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_admin2 VARCHAR(120) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_country_code CHAR(2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_country_name VARCHAR(100) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_lat DECIMAL(10, 7) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_lng DECIMAL(10, 7) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS location_population INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_location_geoname (location_geoname_id),
    ADD INDEX IF NOT EXISTS idx_users_location_country (location_country_code),
    ADD INDEX IF NOT EXISTS idx_users_location_coords (location_lat, location_lng);
