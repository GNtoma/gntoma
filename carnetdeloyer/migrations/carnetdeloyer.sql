CREATE DATABASE IF NOT EXISTS sc3mwse0880_carnet_de_loyer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sc3mwse0880_carnet_de_loyer;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(10) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_code (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS geo_countries (
    code CHAR(2) NOT NULL,
    name VARCHAR(120) NOT NULL,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS geo_cities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_code CHAR(2) NOT NULL,
    name VARCHAR(150) NOT NULL,
    geoname_reference VARCHAR(32) DEFAULT NULL,
    population INT UNSIGNED NOT NULL DEFAULT 0,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_geo_cities_country_name (country_code, name),
    KEY idx_geo_cities_population (population)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS geo_communes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    city_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geo_communes_city_name (city_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS houses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    monthly_rent DECIMAL(12,2) NOT NULL,
    country_code CHAR(2) NOT NULL,
    city_id INT UNSIGNED NOT NULL,
    commune_id INT UNSIGNED NOT NULL,
    avenue VARCHAR(150) NOT NULL,
    house_number VARCHAR(50) NOT NULL,
    status ENUM('libre','occupee') NOT NULL DEFAULT 'libre',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_houses_owner (owner_user_id),
    KEY idx_houses_status (status),
    KEY idx_houses_location (country_code, city_id, commune_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    house_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_house_images_house (house_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rentals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    house_id INT UNSIGNED NOT NULL,
    landlord_user_id INT UNSIGNED NOT NULL,
    tenant_user_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    monthly_rent DECIMAL(12,2) NOT NULL,
    status ENUM('active','ended') NOT NULL DEFAULT 'active',
    validation_status ENUM('pending','validated','rejected') NOT NULL DEFAULT 'pending',
    validated_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rentals_house (house_id),
    KEY idx_rentals_landlord (landlord_user_id),
    KEY idx_rentals_tenant (tenant_user_id),
    KEY idx_rentals_status (status, validation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rental_id INT UNSIGNED NOT NULL,
    due_month DATE NOT NULL,
    due_date DATE NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) DEFAULT NULL,
    payment_status ENUM('paid','pending') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rental_payments_month (rental_id, due_month),
    KEY idx_rental_payments_status (payment_status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO geo_countries (code, name) VALUES
('BI', 'Burundi'),
('CD', 'Republique democratique du Congo'),
('CG', 'Republique du Congo'),
('RW', 'Rwanda');

INSERT IGNORE INTO geo_cities (id, country_code, name, geoname_reference, population, latitude, longitude) VALUES
(1, 'CD', 'Kinshasa', '2314302', 16315534, -4.3275800, 15.3135700),
(2, 'CD', 'Lubumbashi', '922704', 2589278, -11.6608900, 27.4793800),
(3, 'CD', 'Goma', '216281', 782000, -1.6791700, 29.2227800),
(4, 'CD', 'Bukavu', '217831', 870954, -2.4907700, 28.8428100),
(5, 'CD', 'Matadi', '2313002', 331893, -5.8166600, 13.4500000),
(6, 'CG', 'Brazzaville', '2260535', 2388873, -4.2661300, 15.2831800),
(7, 'RW', 'Kigali', '202061', 1132686, -1.9499500, 30.0588500),
(8, 'BI', 'Bujumbura', '425378', 1095330, -3.3822000, 29.3644000);

INSERT IGNORE INTO geo_communes (city_id, name) VALUES
(1, 'Bandalungwa'),
(1, 'Barumbu'),
(1, 'Bumbu'),
(1, 'Gombe'),
(1, 'Kalamu'),
(1, 'Kasa-Vubu'),
(1, 'Kimbanseke'),
(1, 'Kintambo'),
(1, 'Lemba'),
(1, 'Limete'),
(1, 'Lingwala'),
(1, 'Makala'),
(1, 'Masina'),
(1, 'Matete'),
(1, 'Mont-Ngafula'),
(1, 'Ndjili'),
(1, 'Ngaba'),
(1, 'Ngaliema'),
(1, 'Ngiri-Ngiri'),
(1, 'Nsele'),
(1, 'Selembao'),
(2, 'Annexe'),
(2, 'Kamalondo'),
(2, 'Kampemba'),
(2, 'Katuba'),
(2, 'Kenya'),
(2, 'Lubumbashi'),
(2, 'Ruashi'),
(3, 'Goma'),
(3, 'Karisimbi'),
(4, 'Bagira'),
(4, 'Ibanda'),
(4, 'Kadutu'),
(5, 'Matadi'),
(6, 'Bacongo'),
(6, 'Makélékélé'),
(6, 'Moungali'),
(6, 'Ouenzé'),
(6, 'Poto-Poto'),
(7, 'Gasabo'),
(7, 'Kicukiro'),
(7, 'Nyarugenge'),
(8, 'Bwiza'),
(8, 'Buyenzi'),
(8, 'Nyakabiga'),
(8, 'Rohero');
