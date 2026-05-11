CREATE DATABASE IF NOT EXISTS sc3mwse0880_pharmacie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sc3mwse0880_pharmacie;

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

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150),
    phone VARCHAR(50),
    email VARCHAR(190),
    address VARCHAR(255),
    city_id INT UNSIGNED NOT NULL,
    commune_id INT UNSIGNED NOT NULL,
    country_code CHAR(2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_suppliers_location (country_code, city_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    barcode VARCHAR(50) DEFAULT NULL,
    unit VARCHAR(50) DEFAULT NULL,
    purchase_price DECIMAL(12,2) DEFAULT NULL,
    selling_price DECIMAL(12,2) NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    min_stock_level INT UNSIGNED NOT NULL DEFAULT 10,
    expiry_date DATE DEFAULT NULL,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_category (category_id),
    KEY idx_products_supplier (supplier_id),
    KEY idx_products_status (status),
    KEY idx_products_stock (stock_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in','out','adjustment') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    previous_quantity INT UNSIGNED NOT NULL,
    new_quantity INT UNSIGNED NOT NULL,
    reason VARCHAR(255),
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_movements_product (product_id),
    KEY idx_stock_movements_type (movement_type),
    KEY idx_stock_movements_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(190),
    address VARCHAR(255),
    city_id INT UNSIGNED NOT NULL,
    commune_id INT UNSIGNED NOT NULL,
    country_code CHAR(2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_location (country_code, city_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED DEFAULT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    final_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','mobile_money','card','credit') NOT NULL DEFAULT 'cash',
    payment_status ENUM('paid','pending','partial') NOT NULL DEFAULT 'paid',
    status ENUM('completed','cancelled','refunded') NOT NULL DEFAULT 'completed',
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sales_customer (customer_id),
    KEY idx_sales_status (status),
    KEY idx_sales_date (created_at),
    KEY idx_sales_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sale_items_sale (sale_id),
    KEY idx_sale_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO geo_countries (code, name) VALUES
('BI', 'Burundi'),
('CD', 'Republique democratique du Congo'),
('CG', 'Republique du Congo'),
('RW', 'Rwanda');

INSERT IGNORE INTO geo_cities (id, country_code, name, geoname_reference, population, latitude, longitude) VALUES
(1, 'CD', 'Kinshasa', '2314302', 16315534, -4.32758, 15.31357),
(2, 'CD', 'Lubumbashi', '922704', 2589278, -11.66089, 27.47938),
(3, 'CD', 'Goma', '216281', 782000, -1.67917, 29.22278),
(4, 'CD', 'Bukavu', '217831', 870954, -2.49077, 28.84281),
(5, 'CD', 'Matadi', '2313002', 331893, -5.81666, 13.45000),
(6, 'CG', 'Brazzaville', '2260535', 2388873, -4.26613, 15.28318),
(7, 'RW', 'Kigali', '202061', 1132686, -1.94995, 30.05885),
(8, 'BI', 'Bujumbura', '425378', 1095330, -3.38220, 29.36440);

INSERT IGNORE INTO geo_communes (city_id, name) VALUES
(1, 'Bandalungwa'), (1, 'Barumbu'), (1, 'Bumbu'), (1, 'Gombe'), (1, 'Kalamu'),
(1, 'Kasa-Vubu'), (1, 'Kimbanseke'), (1, 'Kintambo'), (1, 'Lemba'), (1, 'Limete'),
(1, 'Lingwala'), (1, 'Makala'), (1, 'Masina'), (1, 'Matete'), (1, 'Mont-Ngafula'),
(1, 'Ndjili'), (1, 'Ngaba'), (1, 'Ngaliema'), (1, 'Ngiri-Ngiri'), (1, 'Nsele'), (1, 'Selembao'),
(2, 'Annexe'), (2, 'Kamalondo'), (2, 'Kampemba'), (2, 'Katuba'), (2, 'Kenya'),
(2, 'Lubumbashi'), (2, 'Ruashi'),
(3, 'Goma'), (3, 'Karisimbi'),
(4, 'Bagira'), (4, 'Ibanda'), (4, 'Kadutu'),
(5, 'Matadi'),
(6, 'Bacongo'), (6, 'Makelekele'), (6, 'Moungali'), (6, 'Ouenze'), (6, 'Poto-Poto'),
(7, 'Gasabo'), (7, 'Kicukiro'), (7, 'Nyarugenge'),
(8, 'Bwiza'), (8, 'Buyenzi'), (8, 'Nyakabiga'), (8, 'Rohero');

INSERT IGNORE INTO categories (name, description) VALUES
('Antibiotiques', 'Médicaments pour traiter les infections bactériennes'),
('Antalgiques', 'Médicaments pour soulager la douleur'),
('Antipyrétiques', 'Médicaments pour réduire la fièvre'),
('Anti-inflammatoires', 'Médicaments pour réduire l\'inflammation'),
('Vitamines', 'Suppléments vitaminiques'),
('Cardiovasculaires', 'Médicaments pour le cœur et la circulation'),
('Dermatologiques', 'Médicaments pour la peau'),
('Digestifs', 'Médicaments pour le système digestif'),
('Respiratoires', 'Médicaments pour les voies respiratoires'),
('Autres', 'Autres types de médicaments');
