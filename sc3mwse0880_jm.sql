-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : lun. 11 mai 2026 à 15:20
-- Version du serveur : 11.4.10-MariaDB
-- Version de PHP : 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sc3mwse0880_jm`
--
-- GNTOMA : garder ce fichier aligné avec les scripts dans migration/
--          (messagerie / OTP : voir migration/004_messaging_chat_otp_alignment.sql).
--

-- --------------------------------------------------------

--
-- Structure de la table `access_requests`
--

CREATE TABLE `access_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(20) NOT NULL COMMENT 'Numero unique D1, D2, etc.',
  `journal_id` int(11) NOT NULL,
  `requester_user_code` varchar(20) NOT NULL,
  `author_user_code` varchar(20) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `response_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demandes d acces aux journaux payants';

-- --------------------------------------------------------

--
-- Structure de la table `access_request_counters`
--

CREATE TABLE `access_request_counters` (
  `id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL,
  `last_request_number` int(11) DEFAULT 0 COMMENT 'Dernier numero D1, D2...',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compteurs de demandes par journal';

-- --------------------------------------------------------

--
-- Structure de la table `author_follows`
--

CREATE TABLE `author_follows` (
  `id` int(10) UNSIGNED NOT NULL,
  `follower_user_code` varchar(20) NOT NULL,
  `followed_user_code` varchar(20) NOT NULL,
  `followed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `author_ranking`
--

CREATE TABLE `author_ranking` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `month` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `views_count` int(11) NOT NULL DEFAULT 0,
  `requests_count` int(11) NOT NULL DEFAULT 0,
  `acceptances_count` int(11) NOT NULL DEFAULT 0,
  `score` decimal(10,2) NOT NULL DEFAULT 0.00,
  `position` int(11) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `follow_requests`
--

CREATE TABLE `follow_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_number` varchar(20) NOT NULL,
  `requester_user_code` varchar(20) NOT NULL,
  `followed_user_code` varchar(20) NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `follow_request_counters`
--

CREATE TABLE `follow_request_counters` (
  `followed_user_code` varchar(20) NOT NULL,
  `last_request_number` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `geo_cities`
--

CREATE TABLE `geo_cities` (
  `id` int(10) UNSIGNED NOT NULL,
  `country_code` char(2) NOT NULL,
  `name` varchar(150) NOT NULL,
  `geoname_reference` varchar(32) DEFAULT NULL,
  `population` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `geo_cities`
--

INSERT INTO `geo_cities` (`id`, `country_code`, `name`, `geoname_reference`, `population`, `latitude`, `longitude`) VALUES
(1, 'CD', 'Kinshasa', '2314302', 16315534, -4.3275800, 15.3135700),
(2, 'CD', 'Lubumbashi', '922704', 2589278, -11.6608900, 27.4793800),
(3, 'CD', 'Goma', '216281', 782000, -1.6791700, 29.2227800),
(4, 'CD', 'Bukavu', '217831', 870954, -2.4907700, 28.8428100),
(5, 'CD', 'Matadi', '2313002', 331893, -5.8166600, 13.4500000),
(6, 'CG', 'Brazzaville', '2260535', 2388873, -4.2661300, 15.2831800),
(7, 'RW', 'Kigali', '202061', 1132686, -1.9499500, 30.0588500),
(8, 'BI', 'Bujumbura', '425378', 1095330, -3.3822000, 29.3644000),
(9, 'ZA', 'Johannesburg', '993800', 5635127, -26.2022700, 28.0436300),
(10, 'ZA', 'Cape Town', '3369157', 4618000, -33.9258400, 18.4232200),
(11, 'NG', 'Lagos', '2332459', 14800000, 6.5243800, 3.3792100),
(12, 'NG', 'Abuja', '2352778', 3842000, 9.0578500, 7.4950800),
(13, 'KE', 'Nairobi', '184745', 4738000, -1.2920700, 36.8219500),
(14, 'KE', 'Mombasa', '186301', 1211000, -4.0434800, 39.6682100),
(15, 'TZ', 'Dar es Salaam', '160263', 6393000, -6.7923500, 39.2083000),
(16, 'TZ', 'Dodoma', '160196', 853000, -6.1639400, 35.7516000),
(17, 'UG', 'Kampala', '232422', 1683000, 0.3476000, 32.5825200),
(18, 'GH', 'Accra', '2306104', 2388000, 5.6037200, -0.1869600),
(19, 'SN', 'Dakar', '2253354', 3186000, 14.6937000, -17.4440600),
(20, 'CI', 'Abidjan', '2293538', 4980000, 5.3599500, -4.0082600),
(21, 'CM', 'Douala', '2232593', 2766000, 4.0482700, 9.7042800),
(22, 'CM', 'Yaounde', '2220957', 2442000, 3.8480300, 11.5020800),
(23, 'ET', 'Addis Ababa', '344979', 3389000, 9.0054000, 38.7636100),
(24, 'MA', 'Casablanca', '2553604', 3749000, 33.5731100, -7.5898400),
(25, 'MA', 'Rabat', '2538474', 572000, 34.0132500, -6.8325500),
(26, 'TN', 'Tunis', '2464470', 1056000, 36.8064900, 10.1815300),
(27, 'DZ', 'Alger', '2507480', 3416000, 36.7537700, 3.0588100),
(28, 'EG', 'Le Caire', '360630', 20900000, 30.0444200, 31.2357100),
(29, 'EG', 'Alexandrie', '361058', 5166000, 31.2000900, 29.9187400),
(30, 'FR', 'Paris', '2988507', 2161000, 48.8566100, 2.3522200),
(31, 'FR', 'Lyon', '2996944', 515000, 45.7484600, 4.8467100),
(32, 'FR', 'Marseille', '2995469', 861000, 43.2969500, 5.3810700),
(33, 'FR', 'Lille', '2998324', 233000, 50.6292500, 3.0572600),
(34, 'FR', 'Toulouse', '2972315', 493000, 43.6046500, 1.4442100),
(35, 'FR', 'Nice', '2990440', 341000, 43.7031300, 7.2660800),
(36, 'FR', 'Nantes', '2990969', 314000, 47.2172500, -1.5533600),
(37, 'FR', 'Strasbourg', '2973783', 277000, 48.5839200, 7.7455300),
(38, 'FR', 'Bordeaux', '3031582', 249000, 44.8377900, -0.5791800),
(39, 'BE', 'Bruxelles', '2800866', 1851000, 50.8503400, 4.3517100),
(40, 'CH', 'Geneve', '2660646', 201000, 46.2043900, 6.1431600),
(41, 'CH', 'Zurich', '2657896', 402000, 47.3768900, 8.5416900),
(42, 'CA', 'Montreal', '6077243', 1780000, 45.5016900, -73.5672500),
(43, 'CA', 'Toronto', '6167865', 2930000, 43.6532300, -79.3831800),
(44, 'CA', 'Vancouver', '6173331', 662000, 49.2827300, -123.1207300),
(45, 'US', 'New York', '5128581', 8468000, 40.7127800, -74.0059700),
(46, 'US', 'Los Angeles', '5368361', 3899000, 34.0522300, -118.2436800),
(47, 'US', 'Chicago', '4887398', 2665000, 41.8781100, -87.6298000),
(48, 'US', 'Miami', '4164138', 442000, 25.7616800, -80.1917900),
(49, 'GB', 'Londres', '2643743', 8982000, 51.5073500, -0.1277600),
(50, 'GB', 'Manchester', '2643123', 553000, 53.4807600, -2.2426300),
(51, 'DE', 'Berlin', '2950159', 3645000, 52.5200100, 13.4049500),
(52, 'DE', 'Munich', '2867714', 148000, 48.1351300, 11.5819800),
(53, 'IT', 'Rome', '3169070', 2873000, 41.9027800, 12.4963700),
(54, 'IT', 'Milan', '3173435', 1366000, 45.4642000, 9.1899800),
(55, 'ES', 'Madrid', '3117735', 6642000, 40.4167800, -3.7037900),
(56, 'ES', 'Barcelone', '3128760', 1620000, 41.3850600, 2.1734000),
(57, 'PT', 'Lisbonne', '2267057', 505000, 38.7222500, -9.1393400),
(58, 'NL', 'Amsterdam', '2759794', 873000, 52.3675700, 4.9041400),
(59, 'BR', 'Sao Paulo', '3448439', 12330000, -23.5505200, -46.6333100),
(60, 'BR', 'Rio de Janeiro', '3451190', 6750000, -22.9068500, -43.1729000),
(61, 'MX', 'Mexico', '3530597', 9200000, 19.4326100, -99.1331800),
(62, 'AR', 'Buenos Aires', '3435910', 1549000, -34.6037200, -58.3815900),
(63, 'CO', 'Bogota', '3688689', 7181000, 4.7109900, -74.0720900),
(64, 'PE', 'Lima', '3936456', 1077000, -12.0463700, -77.0427900),
(65, 'CL', 'Santiago', '3871336', 7119000, -33.4488900, -70.6692700),
(66, 'CN', 'Pekin', '1816670', 21540000, 39.9042100, 116.4074000),
(67, 'CN', 'Shanghai', '1796236', 26320000, 31.2303900, 121.4737000),
(68, 'JP', 'Tokyo', '1850147', 13960000, 35.6895000, 139.6917100),
(69, 'JP', 'Osaka', '1853909', 2752000, 34.6937400, 135.5022600),
(70, 'IN', 'New Delhi', '1261481', 32500000, 28.6139400, 77.2090200),
(71, 'IN', 'Mumbai', '1275339', 20410000, 19.0759800, 72.8776600),
(72, 'KR', 'Seoul', '1835848', 9776000, 37.5665400, 126.9779700),
(73, 'ID', 'Jakarta', '1642911', 10560000, -6.2087600, 106.8456000),
(74, 'TR', 'Istanbul', '745044', 15460000, 41.0082400, 28.9783600),
(75, 'TR', 'Ankara', '323786', 5504000, 39.9333600, 32.8597400),
(76, 'AE', 'Dubai', '292223', 3331000, 25.2048500, 55.2707800),
(77, 'SA', 'Riyad', '108410', 6981000, 24.7135500, 46.6753000),
(78, 'QA', 'Doha', '290030', 956000, 25.2854500, 51.5310400),
(79, 'IL', 'Tel Aviv', '293397', 452000, 32.0853000, 34.7817700),
(80, 'AU', 'Sydney', '2147714', 5312000, -33.8688200, 151.2093000),
(81, 'AU', 'Melbourne', '2158177', 5078000, -37.8136300, 144.9630600),
(82, 'NZ', 'Auckland', '2193733', 1665000, -36.8484600, 174.7633300),
(83, 'TH', 'Bangkok', '1609350', 10540000, 13.7563300, 100.5017600),
(84, 'MY', 'Kuala Lumpur', '1735161', 1808000, 3.1390000, 101.6868500),
(85, 'PH', 'Manille', '1701668', 1780000, 14.5995100, 120.9842200),
(86, 'VN', 'Ho Chi Minh', '1566083', 8993000, 10.8231000, 106.6296600),
(87, 'VN', 'Hanoi', '1581130', 5100000, 21.0277600, 105.8341600),
(88, 'RU', 'Moscou', '524901', 12530000, 55.7558300, 37.6173000),
(89, 'RU', 'Saint-Petersbourg', '498817', 5384000, 59.9342800, 30.3351000),
(90, 'PL', 'Varsovie', '756135', 1794000, 52.2297700, 21.0117800),
(91, 'SE', 'Stockholm', '2673730', 975000, 59.3293200, 18.0685800),
(92, 'NO', 'Oslo', '3143244', 634000, 59.9138700, 10.7522500),
(93, 'DK', 'Copenhague', '2618425', 632000, 55.6761000, 12.5683400),
(94, 'FI', 'Helsinki', '658225', 656000, 60.1698600, 24.9383800),
(95, 'UA', 'Kiev', '703448', 2967000, 50.4501000, 30.5234000),
(96, 'RO', 'Bucarest', '683506', 1836000, 44.4267700, 26.1025400),
(97, 'GR', 'Athenes', '264371', 6640000, 37.9838100, 23.7275400),
(98, 'AT', 'Vienne', '2761369', 191000, 48.2081700, 16.3738200),
(99, 'CZ', 'Prague', '3067696', 1309000, 50.0755400, 14.4378000),
(100, 'HU', 'Budapest', '3054643', 1756000, 47.4979100, 19.0402300),
(101, 'IE', 'Dublin', '2964574', 553000, 53.3498100, -6.2603100),
(102, 'LU', 'Luxembourg', '2960316', 120000, 49.6116200, 6.1319300),
(103, 'HR', 'Zagreb', '3186886', 810000, 45.8150100, 15.9819200),
(104, 'LT', 'Vilnius', '593116', 588000, 54.6871600, 25.2796500),
(105, 'LV', 'Riga', '456172', 630000, 56.9496500, 24.1051800),
(106, 'EE', 'Tallinn', '588409', 437000, 59.4369600, 24.7535300),
(107, 'BG', 'Sofia', '727011', 1234000, 42.6977100, 23.3218700),
(108, 'BD', 'Dacca', '1185241', 21010000, 23.8103300, 90.4125200),
(109, 'PK', 'Karachi', '1174872', 14910000, 24.8607300, 67.0011400),
(110, 'LK', 'Colombo', '1248991', 753000, 6.9270800, 79.8612400),
(111, 'SG', 'Singapour', '1880252', 5686000, 1.3520800, 103.8198400),
(112, 'TW', 'Taipei', '1668341', 2646000, 25.0339600, 121.5645100),
(113, 'MN', 'Oulan-Bator', '2028462', 147000, 47.8864000, 106.9057400),
(114, 'IR', 'Teheran', '112931', 8694000, 35.6892000, 51.3889700),
(115, 'IQ', 'Bagdad', '98182', 7181000, 33.3152400, 44.3660700),
(116, 'JO', 'Amman', '250441', 4007000, 31.9453700, 35.9283700),
(117, 'LB', 'Beyrouth', '276781', 2411000, 33.8937900, 35.5017800),
(118, 'KW', 'Koweit', '285787', 3201000, 29.3758600, 47.9774100),
(119, 'OM', 'Mascate', '287286', 1421000, 23.5858900, 58.4059200),
(120, 'SD', 'Khartoum', '379252', 5663000, 15.5006500, 32.5599000),
(121, 'AO', 'Luanda', '2240449', 8246000, -8.8399900, 13.2894400),
(122, 'MZ', 'Maputo', '1040652', 1193000, -25.9692500, 32.5731700),
(123, 'ZM', 'Lusaka', '909137', 2680000, -15.3875300, 28.3228200),
(124, 'ZW', 'Harare', '890299', 1541000, -17.8251700, 31.0335100),
(125, 'AO', 'Huambo', '3348319', 2771000, -12.7761100, 15.7391700),
(126, 'TG', 'Lome', '2365267', 824000, 6.1256300, 1.2254100),
(127, 'BJ', 'Cotonou', '2394819', 679000, 6.3653600, 2.4182700),
(128, 'GA', 'Libreville', '2399697', 703000, 0.3922500, 9.4535600);

-- --------------------------------------------------------

--
-- Structure de la table `geo_communes`
--

CREATE TABLE `geo_communes` (
  `id` int(10) UNSIGNED NOT NULL,
  `city_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `geo_communes`
--

INSERT INTO `geo_communes` (`id`, `city_id`, `name`) VALUES
(1, 1, 'Bandalungwa'),
(2, 1, 'Barumbu'),
(3, 1, 'Bumbu'),
(4, 1, 'Gombe'),
(5, 1, 'Kalamu'),
(6, 1, 'Kasa-Vubu'),
(7, 1, 'Kimbanseke'),
(8, 1, 'Kintambo'),
(9, 1, 'Lemba'),
(10, 1, 'Limete'),
(11, 1, 'Lingwala'),
(12, 1, 'Makala'),
(13, 1, 'Masina'),
(14, 1, 'Matete'),
(15, 1, 'Mont-Ngafula'),
(16, 1, 'Ndjili'),
(17, 1, 'Ngaba'),
(18, 1, 'Ngaliema'),
(19, 1, 'Ngiri-Ngiri'),
(20, 1, 'Nsele'),
(21, 1, 'Selembao'),
(22, 2, 'Annexe'),
(23, 2, 'Kamalondo'),
(24, 2, 'Kampemba'),
(25, 2, 'Katuba'),
(26, 2, 'Kenya'),
(27, 2, 'Lubumbashi'),
(28, 2, 'Ruashi'),
(29, 3, 'Goma'),
(30, 3, 'Karisimbi'),
(31, 4, 'Bagira'),
(32, 4, 'Ibanda'),
(33, 4, 'Kadutu'),
(34, 5, 'Matadi'),
(35, 6, 'Bacongo'),
(36, 6, 'Makelekele'),
(37, 6, 'Moungali'),
(38, 6, 'Ouenze'),
(39, 6, 'Poto-Poto'),
(40, 7, 'Gasabo'),
(41, 7, 'Kicukiro'),
(42, 7, 'Nyarugenge'),
(44, 8, 'Buyenzi'),
(43, 8, 'Bwiza'),
(45, 8, 'Nyakabiga'),
(46, 8, 'Rohero'),
(48, 9, 'Rosebank'),
(47, 9, 'Sandton'),
(49, 9, 'Soweto'),
(50, 10, 'Green Point'),
(51, 10, 'Sea Point'),
(52, 11, 'Ikeja'),
(53, 11, 'Victoria Island'),
(54, 12, 'Asokoro'),
(55, 12, 'Maitama'),
(57, 13, 'Karen'),
(56, 13, 'Westlands'),
(58, 14, 'Nyali'),
(60, 15, 'Ilala'),
(59, 15, 'Kinondoni'),
(70, 30, '10e arrondissement'),
(71, 30, '11e arrondissement'),
(72, 30, '12e arrondissement'),
(73, 30, '13e arrondissement'),
(74, 30, '14e arrondissement'),
(75, 30, '15e arrondissement'),
(76, 30, '16e arrondissement'),
(77, 30, '17e arrondissement'),
(78, 30, '18e arrondissement'),
(79, 30, '19e arrondissement'),
(61, 30, '1er arrondissement'),
(80, 30, '20e arrondissement'),
(62, 30, '2e arrondissement'),
(63, 30, '3e arrondissement'),
(64, 30, '4e arrondissement'),
(65, 30, '5e arrondissement'),
(66, 30, '6e arrondissement'),
(67, 30, '7e arrondissement'),
(68, 30, '8e arrondissement'),
(69, 30, '9e arrondissement'),
(81, 31, '1er arrondissement'),
(82, 31, '2e arrondissement'),
(83, 31, '3e arrondissement'),
(84, 31, '4e arrondissement'),
(85, 31, '5e arrondissement'),
(86, 31, '6e arrondissement'),
(87, 31, '7e arrondissement'),
(88, 31, '8e arrondissement'),
(89, 31, '9e arrondissement'),
(90, 32, '1er secteur'),
(91, 32, '2e secteur'),
(92, 32, '3e secteur'),
(93, 32, '4e secteur'),
(94, 32, '5e secteur'),
(95, 32, '6e secteur'),
(96, 32, '7e secteur'),
(97, 32, '8e secteur'),
(159, 39, 'Bruxelles-ville'),
(160, 39, 'Ixelles'),
(161, 39, 'Uccle'),
(172, 40, 'Carouge'),
(171, 40, 'Centre'),
(173, 40, 'Eaux-Vives'),
(156, 43, 'Downtown'),
(158, 43, 'Etobicoke'),
(157, 43, 'Scarborough'),
(126, 45, 'Bronx'),
(124, 45, 'Brooklyn'),
(123, 45, 'Manhattan'),
(125, 45, 'Queens'),
(127, 45, 'Staten Island'),
(128, 46, 'Downtown'),
(129, 46, 'Hollywood'),
(130, 46, 'Santa Monica'),
(132, 47, 'Hyde Park'),
(133, 47, 'Lincoln Park'),
(131, 47, 'Loop'),
(99, 49, 'Camden'),
(101, 49, 'Hackney'),
(100, 49, 'Kensington'),
(102, 49, 'Tower Hamlets'),
(98, 49, 'Westminster'),
(104, 51, 'Charlottenburg'),
(105, 51, 'Kreuzberg'),
(103, 51, 'Mitte'),
(106, 51, 'Prenzlauer Berg'),
(165, 53, 'Centro Storico'),
(167, 53, 'Testaccio'),
(166, 53, 'Trastevere'),
(107, 55, 'Centro'),
(109, 55, 'Chamberi'),
(108, 55, 'Salamanca'),
(110, 56, 'Eixample'),
(111, 56, 'Gracia'),
(112, 56, 'Sarria-Sant Gervasi'),
(169, 57, 'Bairro Alto'),
(168, 57, 'Baixa'),
(170, 57, 'Belem'),
(162, 58, 'Centrum'),
(163, 58, 'De Pijp'),
(164, 58, 'Jordaan'),
(113, 59, 'Jardins'),
(114, 59, 'Moema'),
(115, 59, 'Pinheiros'),
(116, 59, 'Vila Madalena'),
(117, 60, 'Copacabana'),
(118, 60, 'Ipanema'),
(119, 60, 'Leblon'),
(121, 61, 'Condesa'),
(120, 61, 'Polanco'),
(122, 61, 'Roma Norte'),
(144, 66, 'Chaoyang'),
(146, 66, 'Dongcheng'),
(145, 66, 'Haidian'),
(143, 68, 'Akihabara'),
(142, 68, 'Ginza'),
(141, 68, 'Shibuya'),
(140, 68, 'Shinjuku'),
(148, 71, 'Andheri'),
(147, 71, 'Bandra'),
(149, 71, 'Colaba'),
(150, 72, 'Gangnam'),
(151, 72, 'Hongdae'),
(152, 72, 'Itaewon'),
(137, 74, 'Besiktas'),
(138, 74, 'Kadikoy'),
(139, 74, 'Sisli'),
(136, 76, 'Deira'),
(134, 76, 'Downtown Dubai'),
(135, 76, 'Jumeirah'),
(154, 80, 'Bondi'),
(153, 80, 'CBD'),
(155, 80, 'Darlinghurst');

-- --------------------------------------------------------

--
-- Structure de la table `geo_countries`
--

CREATE TABLE `geo_countries` (
  `code` char(2) NOT NULL,
  `name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `geo_countries`
--

INSERT INTO `geo_countries` (`code`, `name`) VALUES
('AE', 'Emirats arabes unis'),
('AF', 'Afghanistan'),
('AL', 'Albanie'),
('AM', 'Armenie'),
('AO', 'Angola'),
('AR', 'Argentine'),
('AT', 'Autriche'),
('AU', 'Australie'),
('AZ', 'Azerbaidjan'),
('BA', 'Bosnie-Herzegovine'),
('BD', 'Bangladesh'),
('BE', 'Belgique'),
('BF', 'Burkina Faso'),
('BG', 'Bulgarie'),
('BH', 'Bahrein'),
('BI', 'Burundi'),
('BJ', 'Benin'),
('BR', 'Bresil'),
('BW', 'Botswana'),
('CA', 'Canada'),
('CD', 'Republique democratique du Congo'),
('CF', 'Republique centrafricaine'),
('CG', 'Republique du Congo'),
('CH', 'Suisse'),
('CI', 'Cote d\'Ivoire'),
('CL', 'Chili'),
('CM', 'Cameroun'),
('CN', 'Chine'),
('CO', 'Colombie'),
('CV', 'Cap-Vert'),
('CY', 'Chypre'),
('CZ', 'Tchequie'),
('DE', 'Allemagne'),
('DJ', 'Djibouti'),
('DK', 'Danemark'),
('DZ', 'Algerie'),
('EE', 'Estonie'),
('EG', 'Egypte'),
('EH', 'Sahara occidental'),
('ER', 'Erythree'),
('ES', 'Espagne'),
('ET', 'Ethiopie'),
('FI', 'Finlande'),
('FR', 'France'),
('GA', 'Gabon'),
('GB', 'Royaume-Uni'),
('GE', 'Georgie'),
('GH', 'Ghana'),
('GM', 'Gambie'),
('GN', 'Guinee'),
('GQ', 'Guinee equatoriale'),
('GR', 'Grece'),
('GW', 'Guinee-Bissau'),
('HK', 'Hong Kong'),
('HR', 'Croatie'),
('HU', 'Hongrie'),
('ID', 'Indonesie'),
('IE', 'Irlande'),
('IL', 'Israel'),
('IN', 'Inde'),
('IQ', 'Irak'),
('IR', 'Iran'),
('IS', 'Islande'),
('IT', 'Italie'),
('JO', 'Jordanie'),
('JP', 'Japon'),
('KE', 'Kenya'),
('KG', 'Kirghizistan'),
('KH', 'Cambodge'),
('KM', 'Comores'),
('KR', 'Coree du Sud'),
('KW', 'Koweit'),
('KZ', 'Kazakhstan'),
('LA', 'Laos'),
('LB', 'Liban'),
('LK', 'Sri Lanka'),
('LR', 'Liberia'),
('LS', 'Lesotho'),
('LT', 'Lituanie'),
('LU', 'Luxembourg'),
('LV', 'Lettonie'),
('LY', 'Libye'),
('MA', 'Maroc'),
('MD', 'Moldavie'),
('ME', 'Montenegro'),
('MG', 'Madagascar'),
('MK', 'Macedoine du Nord'),
('ML', 'Mali'),
('MM', 'Myanmar'),
('MN', 'Mongolie'),
('MO', 'Macao'),
('MR', 'Mauritanie'),
('MT', 'Malte'),
('MU', 'Maurice'),
('MW', 'Malawi'),
('MX', 'Mexique'),
('MY', 'Malaisie'),
('MZ', 'Mozambique'),
('NA', 'Namibie'),
('NE', 'Niger'),
('NG', 'Nigeria'),
('NL', 'Pays-Bas'),
('NO', 'Norvege'),
('NP', 'Nepal'),
('NZ', 'Nouvelle-Zelande'),
('OM', 'Oman'),
('PE', 'Perou'),
('PH', 'Philippines'),
('PK', 'Pakistan'),
('PL', 'Pologne'),
('PT', 'Portugal'),
('QA', 'Qatar'),
('RO', 'Roumanie'),
('RS', 'Serbie'),
('RU', 'Russie'),
('RW', 'Rwanda'),
('SA', 'Arabie saoudite'),
('SC', 'Seychelles'),
('SD', 'Soudan'),
('SE', 'Suede'),
('SG', 'Singapour'),
('SI', 'Slovenie'),
('SK', 'Slovaquie'),
('SL', 'Sierra Leone'),
('SN', 'Senegal'),
('SO', 'Somalie'),
('SS', 'Soudan du Sud'),
('ST', 'Sao Tome-et-Principe'),
('SY', 'Syrie'),
('SZ', 'Eswatini'),
('TD', 'Tchad'),
('TG', 'Togo'),
('TH', 'Thailande'),
('TJ', 'Tadjikistan'),
('TM', 'Turkmenistan'),
('TN', 'Tunisie'),
('TR', 'Turquie'),
('TW', 'Taiwan'),
('TZ', 'Tanzanie'),
('UA', 'Ukraine'),
('UG', 'Ouganda'),
('US', 'Etats-Unis'),
('UZ', 'Ouzbekistan'),
('VN', 'Vietnam'),
('YE', 'Yemen'),
('ZA', 'Afrique du Sud'),
('ZM', 'Zambie'),
('ZW', 'Zimbabwe');

-- --------------------------------------------------------

--
-- Structure de la table `journals`
--

CREATE TABLE `journals` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` enum('public','private','paid') DEFAULT 'private',
  `cover_image` varchar(255) DEFAULT NULL COMMENT 'Image de couverture du journal',
  `keywords` varchar(500) DEFAULT NULL COMMENT 'Mots-cles du journal',
  `price` decimal(10,2) DEFAULT NULL COMMENT 'Prix du journal en CDF',
  `price_currency` varchar(3) DEFAULT 'CDF' COMMENT 'Devise du prix',
  `expires_at` date DEFAULT NULL COMMENT 'Date d expiration (10 ans apres creation)',
  `reader_count` int(11) DEFAULT 0 COMMENT 'Nombre de lecteurs',
  `comments_count` int(11) DEFAULT 0 COMMENT 'Nombre de commentaires',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des journaux avec support images, mots-cles et prix';

--
-- Déchargement des données de la table `journals`
--

INSERT INTO `journals` (`id`, `user_code`, `title`, `status`, `cover_image`, `keywords`, `price`, `price_currency`, `expires_at`, `reader_count`, `comments_count`, `created_at`) VALUES
(1, 'A3', 'Le livre d\'Henoch', 'paid', NULL, 'henock', 4.00, 'CDF', '2036-05-06', 0, 0, '2026-05-06 14:46:39'),
(2, 'A2', 'La gentillesse', 'paid', 'uploads/journals/covers/A2_cover1.jpg', '', 4.00, 'CDF', '2036-05-06', 0, 0, '2026-05-06 16:54:35'),
(3, 'A3', 'La Sexualité sacrée', 'public', 'uploads/journals/covers/A3_cover2.png', 'Amour spiritualité', NULL, 'CDF', '2036-05-06', 0, 0, '2026-05-06 22:33:04');

-- --------------------------------------------------------

--
-- Structure de la table `journal_comments`
--

CREATE TABLE `journal_comments` (
  `id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL COMMENT 'ID du journal commente',
  `user_code` varchar(20) NOT NULL COMMENT 'Code utilisateur',
  `author_user_code` varchar(20) NOT NULL COMMENT 'Code auteur du journal',
  `content` text NOT NULL COMMENT 'Contenu du commentaire',
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `parent_id` int(11) DEFAULT NULL COMMENT 'ID commentaire parent',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Commentaires des lecteurs';

-- --------------------------------------------------------

--
-- Structure de la table `journal_pages`
--

CREATE TABLE `journal_pages` (
  `id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `page_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `journal_pages`
--

INSERT INTO `journal_pages` (`id`, `journal_id`, `title`, `content`, `image_path`, `page_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Page 1', NULL, NULL, 1, '2026-05-06 15:49:56', '2026-05-06 15:49:56'),
(2, 2, 'Page 1', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">Je suis gentil</div>\r\n                            ', NULL, 1, '2026-05-06 16:54:41', '2026-05-06 16:55:56'),
(3, 2, 'Page 2', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">Tu es gentille&nbsp;</div>\r\n                            <div class=\"my-4\"><img src=\"../uploads/journals/2/A2_j2_p3.jpg\" alt=\"\" class=\"max-w-full rounded-xl shadow-lg\"></div>', 'uploads/journals/2/A2_j2_p3.jpg', 2, '2026-05-06 16:55:00', '2026-05-06 16:55:31'),
(4, 3, 'Page 1', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\"><p><strong>1/12 — Le Commencement du Feu Intérieur</strong></p>\r\n<p>La sexualité sacrée ne commence pas par le corps.<br>\r\nElle commence par la présence. ✨</p>\r\n<p>Avant même le toucher, il existe une vibration.<br>\r\nUne manière de regarder.<br>\r\nUne manière d’écouter.<br>\r\nUne manière d’habiter son propre être.</p>\r\n<p>Beaucoup cherchent le plaisir sans avoir rencontré leur propre profondeur. Alors le désir devient consommation, agitation, fuite ou dépendance. Mais lorsque l’être humain apprend à se reconnecter à lui-même, le désir change de nature. Il cesse d’être une faim aveugle pour devenir une énergie consciente.</p>\r\n<p>Le corps n’est pas impur.<br>\r\nLe désir n’est pas un ennemi.<br>\r\nL’énergie sexuelle est une force de création.</p>\r\n<p>C’est cette énergie qui peut donner naissance à un enfant, à une œuvre, à une vision, à une transformation intérieure. Lorsqu’elle est dirigée avec conscience, elle élève l’esprit au lieu de l’emprisonner.</p>\r\n<p>La sexualité sacrée enseigne que deux corps peuvent se toucher sans jamais réellement se rencontrer… tandis que deux âmes peuvent fusionner dans un simple regard silencieux.</p>\r\n<p>Le véritable lien commence lorsque :</p>\r\n<ul>\r\n<li>le masque tombe,</li>\r\n<li>la peur diminue,</li>\r\n<li>et que la présence devient plus forte que la performance.</li>\r\n</ul>\r\n<p>Aimer consciemment, c’est entrer dans un espace où :\r\nle corps parle,\r\nle cœur écoute,\r\net l’âme reconnaît.</p>\r\n<p>La sexualité sacrée n’est pas seulement l’union de deux personnes.<br>\r\nC’est l’union entre le corps, le cœur, l’énergie et la conscience. 🔥</p>\r\n<p><strong><a href=\"http://www.gntoma.com\">www.gntoma.com</a></strong></p></div>\r\n                            ', NULL, 1, '2026-05-06 22:33:19', '2026-05-06 22:34:20'),
(5, 3, 'Page 2', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">\r\n                        <p class=\"mb-4\">Commencez à écrire votre page..</p>\r\n                    </div>\r\n                            ', NULL, 2, '2026-05-06 22:34:33', '2026-05-06 22:35:04'),
(6, 3, 'Page 3', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">3/12 — Le Désir Conscient<div><br></div><div>Le désir n’est pas un péché.</div><div>Le désir est une direction. 🔥</div><div><br></div><div>Il révèle ce qui attire l’âme, ce qui manque au cœur, ce que le corps cherche à expérimenter. Mais lorsque le désir est inconscient, il peut devenir une prison. Lorsqu’il devient conscient, il se transforme en puissance créatrice.</div><div><br></div><div>La société a souvent enseigné deux extrêmes :</div><div><br></div><div>soit réprimer totalement le désir,</div><div><br></div><div>soit s’y abandonner sans conscience.</div><div><br></div><div><br></div><div>La sexualité sacrée propose un troisième chemin : ressentir profondément… sans se perdre.</div><div><br></div><div>Le vrai pouvoir ne consiste pas à séduire tout le monde.</div><div>Le vrai pouvoir consiste à maîtriser son énergie.</div><div><br></div><div>Une personne consciente de son énergie sexuelle développe :</div><div><br></div><div>une présence magnétique,</div><div><br></div><div>une parole plus vibrante,</div><div><br></div><div>un regard plus vivant,</div><div><br></div><div>une force intérieure calme.</div><div><br></div><div><br></div><div>Car l’énergie sexuelle n’est pas seulement liée à l’acte intime. Elle influence aussi :</div><div><br></div><div>la créativité,</div><div><br></div><div>la confiance,</div><div><br></div><div>l’intuition,</div><div><br></div><div>la motivation,</div><div><br></div><div>le rayonnement personnel.</div><div><br></div><div><br></div><div>Quand cette énergie est dispersée dans des relations vides, dans des compulsions ou dans des excès, l’être se fatigue intérieurement. Mais lorsqu’elle est cultivée avec respect, elle nourrit le corps et l’esprit comme un feu sacré.</div><div><br></div><div>Le désir conscient ne cherche pas seulement à prendre.</div><div>Il cherche aussi à ressentir, honorer et transmettre.</div><div><br></div><div>Dans la sexualité sacrée, l’autre personne n’est pas un objet de consommation émotionnelle ou physique.</div><div>Elle devient un miroir vivant.</div><div><br></div><div>Un miroir qui révèle :</div><div><br></div><div>nos blessures,</div><div><br></div><div>nos peurs,</div><div><br></div><div>nos attachements,</div><div><br></div><div>mais aussi notre capacité à aimer sincèrement.</div><div><br></div><div><br></div><div>Certaines connexions excitent le corps.</div><div>D’autres réveillent l’âme.</div><div><br></div><div>Et les plus rares réussissent à faire les deux à la fois. ✨</div><div><br></div><div>www.gntoma.com</div></div>\r\n                            ', NULL, 3, '2026-05-06 22:35:28', '2026-05-06 22:37:00'),
(7, 3, 'Page 4', NULL, NULL, 4, '2026-05-06 22:37:04', '2026-05-06 22:37:04'),
(8, 3, 'Page 5', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">5/12 — Le Regard qui Réveille l’Âme<div><br></div><div>Avant les lèvres, avant les mains, avant les corps…</div><div>Il y a le regard. 👁️✨</div><div><br></div><div>Un vrai regard peut traverser les défenses les plus profondes.</div><div>Il peut révéler un désir caché, une blessure silencieuse ou une vérité que les mots n’osent pas dire.</div><div><br></div><div>La sexualité sacrée accorde une immense importance à la présence du regard, parce que les yeux transmettent l’énergie intérieure.</div><div><br></div><div>Certaines personnes regardent pour posséder.</div><div>D’autres regardent pour comprendre.</div><div>Et quelques rares êtres regardent avec l’âme.</div><div><br></div><div>Lorsqu’un regard devient conscient, il crée une connexion invisible mais puissante. Le temps semble ralentir. Le corps devient plus sensible. L’esprit cesse de courir dans toutes les directions.</div><div><br></div><div>Le regard sincère dit parfois :</div><div><br></div><div>“Je te vois réellement.”</div><div><br></div><div>“Tu peux déposer tes masques.”</div><div><br></div><div>“Tu n’as pas besoin de jouer un rôle ici.”</div><div><br></div><div><br></div><div>Beaucoup de relations échouent parce que les êtres se touchent physiquement sans jamais se rencontrer intérieurement.</div><div><br></div><div>La sexualité sacrée enseigne qu’il faut apprendre à voir l’autre au-delà :</div><div><br></div><div>de son apparence,</div><div><br></div><div>de ses performances,</div><div><br></div><div>de ses séductions,</div><div><br></div><div>de ses protections émotionnelles.</div><div><br></div><div><br></div><div>Car derrière chaque corps existe une histoire invisible.</div><div><br></div><div>Il existe des regards qui consument. 🔥</div><div>Et d’autres qui guérissent.</div><div><br></div><div>Un regard habité par la conscience peut calmer une peur, réveiller la confiance et ouvrir le cœur sans même prononcer une phrase.</div><div><br></div><div>C’est aussi pour cela que l’intimité sacrée demande de la vérité.</div><div>Parce qu’un corps peut mentir… mais rarement les yeux.</div><div><br></div><div>Quand deux êtres se regardent profondément sans peur, sans domination, sans masque, quelque chose de rare apparaît :</div><div><br></div><div>Une sensation d’être reconnu au-delà du physique.</div><div><br></div><div>Et parfois, cette reconnaissance touche une partie de nous qui attendait cela depuis très longtemps. 🌙</div><div><br></div><div>www.gntoma.com</div></div>\r\n                            ', NULL, 5, '2026-05-06 22:37:23', '2026-05-06 22:45:03'),
(9, 3, 'Page 6', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\"><p><strong>6/12 — La Respiration et l’Énergie Vivante</strong></p>\r\n<p>La respiration est un pont entre le corps et l’esprit. 🌬️</p>\r\n<p>La plupart des gens respirent sans conscience.<br>\r\nRapidement.<br>\r\nSuperficiellement.<br>\r\nComme s’ils étaient coupés d’eux-mêmes.</p>\r\n<p>Pourtant, dans la sexualité sacrée, la respiration joue un rôle essentiel. Elle influence :</p>\r\n<ul>\r\n<li>les émotions,</li>\r\n<li>l’intensité du ressenti,</li>\r\n<li>la circulation de l’énergie,</li>\r\n<li>et même la qualité de la connexion entre deux êtres.</li>\r\n</ul>\r\n<p>Quand une personne est stressée, son souffle devient court.<br>\r\nQuand elle est présente et ouverte, son souffle devient plus profond.</p>\r\n<p>Respirer consciemment pendant l’intimité permet de ralentir le mental et d’amplifier les sensations sans tomber dans la précipitation.</p>\r\n<p>Deux êtres qui synchronisent leurs respirations commencent souvent à ressentir une étrange harmonie intérieure. Comme si leurs corps entraient dans un même rythme invisible.</p>\r\n<p>La sexualité sacrée ne cherche pas uniquement l’explosion du plaisir.<br>\r\nElle cherche aussi l’expansion de la conscience. ✨</p>\r\n<p>C’est pourquoi elle valorise :</p>\r\n<ul>\r\n<li>la lenteur,</li>\r\n<li>l’écoute du corps,</li>\r\n<li>la présence émotionnelle,</li>\r\n<li>et la circulation naturelle de l’énergie.</li>\r\n</ul>\r\n<p>Lorsque le souffle devient calme et profond, l’énergie intime cesse de rester bloquée dans le simple désir physique. Elle commence à monter dans tout le corps :</p>\r\n<ul>\r\n<li>dans le cœur,</li>\r\n<li>dans la voix,</li>\r\n<li>dans le regard,</li>\r\n<li>dans l’esprit.</li>\r\n</ul>\r\n<p>Certaines traditions considèrent même cette énergie comme une force vitale capable de revitaliser l’être humain lorsqu’elle est utilisée avec sagesse.</p>\r\n<p>Le problème n’est donc pas l’énergie sexuelle.<br>\r\nLe problème est l’inconscience.</p>\r\n<p>Une énergie puissante sans conscience peut détruire.<br>\r\nMais une énergie puissante dirigée avec présence peut transformer une vie entière.</p>\r\n<p>Respirer ensemble.<br>\r\nRessentir ensemble.<br>\r\nÊtre totalement présent.</p>\r\n<p>Parfois, cela crée une intimité plus profonde que tous les mots du monde. 🔥</p>\r\n<p><strong><a href=\"http://www.gntoma.com\">www.gntoma.com</a></strong></p></div>\r\n                            ', NULL, 6, '2026-05-06 22:45:17', '2026-05-06 22:46:05'),
(10, 3, 'Page 7', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\"><p><strong>7/12 — Le Respect du Temple Intérieur</strong></p>\r\n<p>Le corps humain n’est pas un simple objet de plaisir.<br>\r\nC’est un espace vivant chargé d’émotions, de mémoire et d’énergie. ✨</p>\r\n<p>La sexualité sacrée enseigne que chaque personne porte en elle un temple intérieur. Et un temple ne se profane pas. Il se respecte.</p>\r\n<p>Cela change complètement la manière de vivre l’intimité.</p>\r\n<p>Quand une personne ne respecte pas son propre corps, elle accepte souvent :</p>\r\n<ul>\r\n<li>des relations qui l’épuisent,</li>\r\n<li>des paroles qui la diminuent,</li>\r\n<li>des contacts sans amour,</li>\r\n<li>ou des situations qui blessent son âme.</li>\r\n</ul>\r\n<p>Le manque de respect envers soi-même ouvre souvent la porte à des connexions destructrices.</p>\r\n<p>À l’inverse, lorsqu’un être comprend sa valeur intérieure, son énergie change naturellement. Il devient plus sélectif, plus conscient et plus calme.</p>\r\n<p>La sexualité sacrée ne consiste pas à séduire le plus possible.<br>\r\nElle consiste à préserver la qualité de son énergie. 🔥</p>\r\n<p>Certaines présences apportent :</p>\r\n<ul>\r\n<li>la paix,</li>\r\n<li>la douceur,</li>\r\n<li>la clarté,</li>\r\n<li>la guérison émotionnelle.</li>\r\n</ul>\r\n<p>D’autres apportent :</p>\r\n<ul>\r\n<li>le chaos,</li>\r\n<li>la confusion,</li>\r\n<li>la fatigue intérieure,</li>\r\n<li>ou la dépendance affective.</li>\r\n</ul>\r\n<p>Le corps ressent souvent la vérité avant l’esprit.</p>\r\n<p>Parfois, une personne semble parfaite extérieurement… mais notre énergie se ferme en sa présence. D’autres fois, quelqu’un dégage une paix difficile à expliquer.</p>\r\n<p>Apprendre à écouter cette intelligence intérieure est essentiel.</p>\r\n<p>La sexualité sacrée rappelle aussi qu’aucune intimité ne devrait être construite sur :</p>\r\n<ul>\r\n<li>la manipulation,</li>\r\n<li>la peur,</li>\r\n<li>la pression,</li>\r\n<li>le mensonge,</li>\r\n<li>ou la domination émotionnelle.</li>\r\n</ul>\r\n<p>L’amour conscient ne cherche pas à contrôler.<br>\r\nIl cherche à honorer.</p>\r\n<p>Honorer le rythme de l’autre.<br>\r\nHonorer ses émotions.<br>\r\nHonorer ses limites.<br>\r\nHonorer son humanité.</p>\r\n<p>Car lorsqu’une relation détruit la paix intérieure, même le plaisir finit par devenir vide.</p>\r\n<p>Mais lorsqu’une connexion nourrit l’âme, même un simple contact peut devenir profondément transformateur. 🌙</p>\r\n<p><strong><a href=\"http://www.gntoma.com\">www.gntoma.com</a></strong></p></div>\r\n                            ', NULL, 7, '2026-05-06 22:46:05', '2026-05-06 22:47:07'),
(11, 3, 'Page 8', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">8/12 — L’Alchimie du Masculin et du Féminin<div><br></div><div>Chaque être humain porte en lui deux forces profondes :</div><div>le masculin et le féminin. ☯️</div><div><br></div><div>La sexualité sacrée ne parle pas seulement du genre biologique. Elle parle surtout d’énergies intérieures.</div><div><br></div><div>Le masculin conscient apporte souvent :</div><div><br></div><div>la stabilité,</div><div><br></div><div>la direction,</div><div><br></div><div>la protection,</div><div><br></div><div>la présence calme.</div><div><br></div><div><br></div><div>Le féminin conscient apporte :</div><div><br></div><div>l’intuition,</div><div><br></div><div>la sensibilité,</div><div><br></div><div>la créativité,</div><div><br></div><div>la capacité d’ouvrir émotionnellement l’espace.</div><div><br></div><div><br></div><div>Lorsque ces deux forces sont déséquilibrées, les relations deviennent souvent des combats de pouvoir, de peur ou de dépendance.</div><div><br></div><div>Mais lorsqu’elles apprennent à collaborer, une harmonie rare apparaît.</div><div><br></div><div>Le masculin sacré ne cherche pas à dominer.</div><div>Il cherche à sécuriser.</div><div><br></div><div>Le féminin sacré ne cherche pas à manipuler.</div><div>Il cherche à inspirer.</div><div><br></div><div>Dans beaucoup de relations modernes, les êtres humains sont blessés intérieurement :</div><div><br></div><div>peur d’être abandonné,</div><div><br></div><div>peur d’être contrôlé,</div><div><br></div><div>peur de ne pas être assez,</div><div><br></div><div>peur d’aimer sincèrement.</div><div><br></div><div><br></div><div>Alors chacun construit des armures.</div><div><br></div><div>Certaines personnes utilisent la séduction comme protection.</div><div>D’autres utilisent la froideur émotionnelle.</div><div>D’autres encore utilisent le contrôle ou le silence.</div><div><br></div><div>Mais la sexualité sacrée demande autre chose :</div><div><br></div><div>Le courage d’être authentique. 🔥</div><div><br></div><div>Car une relation profonde ne peut exister là où chacun joue un personnage.</div><div><br></div><div>Quand le masculin et le féminin commencent à s’équilibrer intérieurement, l’amour devient plus mature. Moins basé sur le manque. Plus basé sur la conscience.</div><div><br></div><div>On cesse de chercher quelqu’un pour combler un vide.</div><div>On commence à partager une plénitude.</div><div><br></div><div>Alors l’intimité change de qualité :</div><div><br></div><div>il y a moins de jeux,</div><div><br></div><div>moins de domination,</div><div><br></div><div>moins de peur,</div><div><br></div><div>et davantage de vérité.</div><div><br></div><div><br></div><div>La connexion devient plus douce… mais aussi plus puissante.</div><div><br></div><div>Parce qu’au fond, l’union sacrée ne consiste pas seulement à aimer quelqu’un.</div><div>Elle consiste aussi à réconcilier les forces opposées à l’intérieur de soi-même. ✨</div><div><br></div><div>www.gntoma.com</div></div>\r\n                            ', NULL, 8, '2026-05-06 22:47:25', '2026-05-06 22:48:17'),
(12, 3, 'Page 9', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\"><p><strong>9/12 — L’Énergie Après l’Union</strong></p>\r\n<p>Chaque rencontre laisse une trace. 🌙</p>\r\n<p>Après une intimité profonde, quelque chose demeure :</p>\r\n<ul>\r\n<li>une sensation de paix,</li>\r\n<li>une confusion intérieure,</li>\r\n<li>une légèreté,</li>\r\n<li>une fatigue étrange,</li>\r\n<li>ou parfois un attachement difficile à expliquer.</li>\r\n</ul>\r\n<p>La sexualité sacrée enseigne que l’énergie continue à circuler même après l’union physique.</p>\r\n<p>C’est pourquoi certaines connexions restent gravées longtemps dans le cœur et dans le corps. Non seulement à cause des émotions… mais aussi à cause du lien énergétique créé entre deux êtres.</p>\r\n<p>Certaines personnes repartent avec notre paix.<br>\r\nD’autres réveillent notre lumière. ✨</p>\r\n<p>Voilà pourquoi le choix des connexions est important.</p>\r\n<p>Dans un monde où l’intimité devient parfois rapide et superficielle, beaucoup oublient qu’ouvrir son corps, c’est aussi ouvrir une partie de son univers intérieur.</p>\r\n<p>Après certaines relations, on se sent inspiré, vivant, aligné.<br>\r\nAprès d’autres, on se sent vide, lourd ou dispersé.</p>\r\n<p>Le corps parle toujours.<br>\r\nL’énergie aussi.</p>\r\n<p>La sexualité sacrée invite donc à prendre soin de soi après chaque union :</p>\r\n<ul>\r\n<li>retrouver le silence,</li>\r\n<li>respirer profondément,</li>\r\n<li>écouter ses émotions,</li>\r\n<li>purifier son esprit,</li>\r\n<li>et revenir à son propre centre.</li>\r\n</ul>\r\n<p>Car l’amour conscient ne consiste pas seulement à savoir s’unir.<br>\r\nIl consiste aussi à savoir préserver son équilibre intérieur.</p>\r\n<p>Certaines personnes deviennent dépendantes non d’un être… mais de l’énergie ressentie avec lui.</p>\r\n<p>Et lorsque cette connexion disparaît, elles ont l’impression qu’une partie d’elles-mêmes s’effondre.</p>\r\n<p>C’est pour cela qu’il est essentiel de rester connecté à soi-même avant de se connecter aux autres. 🔥</p>\r\n<p>Une âme consciente ne cherche pas uniquement l’intensité.<br>\r\nElle cherche aussi la paix.</p>\r\n<p>Parce qu’une relation sacrée ne doit pas détruire ton énergie.<br>\r\nElle doit l’élever.</p>\r\n<p>Et lorsque deux êtres se quittent après une union vécue dans la conscience, ils devraient laisser derrière eux :</p>\r\n<ul>\r\n<li>plus de lumière,</li>\r\n<li>plus de respect,</li>\r\n<li>plus de vérité,</li>\r\n<li>et non des blessures supplémentaires.</li>\r\n</ul>\r\n<p><strong><a href=\"http://www.gntoma.com\">www.gntoma.com</a></strong></p></div>\r\n                            ', NULL, 9, '2026-05-06 22:48:17', '2026-05-06 22:49:24'),
(13, 3, 'Page 10', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">10/12 — L’Amour Conscient<div><br></div><div>L’amour conscient n’est pas une dépendance émotionnelle.</div><div>C’est une rencontre entre deux êtres éveillés. ✨</div><div><br></div><div>Beaucoup confondent amour et attachement.</div><div><br></div><div>L’attachement dit :</div><div><br></div><div>“J’ai besoin de toi pour être complet.”</div><div><br></div><div>“Ne me quitte pas.”</div><div><br></div><div>“Rassure mes peurs.”</div><div><br></div><div><br></div><div>L’amour conscient dit plutôt :</div><div><br></div><div>“Je choisis de marcher avec toi.”</div><div><br></div><div>“Je respecte ta liberté.”</div><div><br></div><div>“Je veux voir ton âme grandir.”</div><div><br></div><div><br></div><div>La sexualité sacrée devient dangereuse lorsqu’elle est utilisée pour :</div><div><br></div><div>combler un vide intérieur,</div><div><br></div><div>manipuler émotionnellement,</div><div><br></div><div>obtenir de la validation,</div><div><br></div><div>ou fuir la solitude.</div><div><br></div><div><br></div><div>Car aucune relation ne peut guérir complètement une personne qui refuse de se rencontrer elle-même.</div><div><br></div><div>Avant d’aimer profondément quelqu’un, il faut apprendre à habiter sa propre présence.</div><div><br></div><div>Certaines personnes recherchent constamment l’intensité émotionnelle parce qu’elles confondent chaos et passion. Pourtant, une connexion saine apporte souvent quelque chose de plus calme :</div><div><br></div><div>la sécurité intérieure,</div><div><br></div><div>la confiance,</div><div><br></div><div>la stabilité émotionnelle,</div><div><br></div><div>la paix.</div><div><br></div><div><br></div><div>Le véritable amour n’étouffe pas.</div><div>Il agrandit l’être. 🔥</div><div><br></div><div>Dans une relation consciente :</div><div><br></div><div>les deux personnes restent honnêtes,</div><div><br></div><div>les limites sont respectées,</div><div><br></div><div>les blessures peuvent être exprimées,</div><div><br></div><div>et chacun reste responsable de son évolution intérieure.</div><div><br></div><div><br></div><div>L’amour sacré ne signifie pas perfection.</div><div>Il signifie conscience.</div><div><br></div><div>Deux êtres peuvent encore avoir des peurs, des blessures ou des différences… mais ils choisissent de ne pas transformer cela en guerre émotionnelle.</div><div><br></div><div>Ils apprennent :</div><div><br></div><div>à communiquer,</div><div><br></div><div>à écouter,</div><div><br></div><div>à ralentir,</div><div><br></div><div>à comprendre avant de réagir.</div><div><br></div><div><br></div><div>Car une relation profonde n’est pas construite uniquement sur l’attraction physique.</div><div>Elle est construite sur la qualité de présence entre deux âmes.</div><div><br></div><div>Et parfois, le plus grand acte d’amour n’est pas de posséder quelqu’un…</div><div>mais de préserver la lumière qu’il porte. 🌙</div><div><br></div><div>www.gntoma.com</div></div>\r\n                            ', NULL, 10, '2026-05-06 22:49:29', '2026-05-06 22:50:01'),
(14, 3, 'Page 11', '\r\n                                    <div class=\"content-block text-gray-600 leading-relaxed\" contenteditable=\"true\" placeholder=\"Commencez à écrire ici...\">11/12 — La Transmutation de l’Énergie<div><br></div><div>L’énergie sexuelle est l’une des forces les plus puissantes de l’être humain. 🔥</div><div><br></div><div>Elle peut créer la vie.</div><div>Elle peut détruire une personne.</div><div>Ou elle peut transformer totalement une conscience.</div><div><br></div><div>Tout dépend de la manière dont elle est utilisée.</div><div><br></div><div>La sexualité sacrée enseigne qu’il est possible de transformer cette énergie en :</div><div><br></div><div>créativité,</div><div><br></div><div>clarté mentale,</div><div><br></div><div>charisme,</div><div><br></div><div>discipline,</div><div><br></div><div>inspiration,</div><div><br></div><div>puissance intérieure.</div><div><br></div><div><br></div><div>C’est ce qu’on appelle parfois la transmutation.</div><div><br></div><div>Lorsqu’une personne cesse de gaspiller constamment son énergie dans des excès, des compulsions ou des relations destructrices, cette force commence à nourrir d’autres dimensions de sa vie.</div><div><br></div><div>Beaucoup de grands créateurs, artistes, penseurs ou leaders ont compris intuitivement cette réalité : l’énergie intime influence directement :</div><div><br></div><div>le regard,</div><div><br></div><div>la motivation,</div><div><br></div><div>la voix,</div><div><br></div><div>la présence,</div><div><br></div><div>et le rayonnement personnel.</div><div><br></div><div><br></div><div>Un être aligné énergétiquement dégage souvent quelque chose de magnétique sans même essayer.</div><div><br></div><div>La sexualité sacrée ne demande pas forcément l’abstinence totale.</div><div>Elle demande surtout la conscience.</div><div><br></div><div>Car le problème n’est pas le désir.</div><div>Le problème est la perte de maîtrise.</div><div><br></div><div>Quand l’énergie dirige l’être humain aveuglément, il devient esclave de ses impulsions. Mais lorsque la conscience guide l’énergie, cette même force devient un moteur d’évolution intérieure. ✨</div><div><br></div><div>Cela demande :</div><div><br></div><div>de la présence,</div><div><br></div><div>de la discipline,</div><div><br></div><div>de l’écoute de soi,</div><div><br></div><div>et une certaine capacité à résister aux plaisirs immédiats qui vident l’âme sur le long terme.</div><div><br></div><div><br></div><div>La transmutation commence souvent dans le silence.</div><div><br></div><div>Dans les moments où l’on choisit :</div><div><br></div><div>de respirer au lieu de réagir,</div><div><br></div><div>de créer au lieu de se disperser,</div><div><br></div><div>d’élever son esprit au lieu de nourrir ses compulsions.</div><div><br></div><div><br></div><div>Alors l’énergie change de direction.</div><div><br></div><div>Elle ne descend plus seulement vers le plaisir physique.</div><div>Elle commence aussi à monter vers :</div><div><br></div><div>la vision,</div><div><br></div><div>la sagesse,</div><div><br></div><div>la créativité,</div><div><br></div><div>et l’expansion intérieure.</div><div><br></div><div><br></div><div>Et peu à peu, l’être humain cesse de chercher uniquement des sensations…</div><div><br></div><div>Il commence à chercher la vibration qui nourrit réellement son âme. 🌙</div><div><br></div><div>www.gntoma.com</div></div>\r\n                            ', NULL, 11, '2026-05-06 22:50:51', '2026-05-06 22:51:53'),
(15, 3, 'Page 12', NULL, NULL, 12, '2026-05-06 22:51:57', '2026-05-06 22:51:57');

-- --------------------------------------------------------

--
-- Structure de la table `journal_readers`
--

CREATE TABLE `journal_readers` (
  `id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL,
  `user_code` varchar(20) NOT NULL,
  `first_access_at` timestamp NULL DEFAULT current_timestamp(),
  `last_access_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `access_count` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `journal_views`
--

CREATE TABLE `journal_views` (
  `id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL,
  `viewer_code` varchar(10) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) DEFAULT NULL COMMENT 'ID de la conversation',
  `sender_user_code` varchar(10) NOT NULL,
  `recipient_user_code` varchar(10) DEFAULT NULL,
  `content` text NOT NULL,
  `is_bulk` tinyint(1) DEFAULT 0 COMMENT 'Message groupe',
  `bulk_filter_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Criteres du filtre' CHECK (json_valid(`bulk_filter_criteria`)),
  `has_attachment` tinyint(1) DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(50) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  `credits_consumed` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Date d''expiration (21 jours apres envoi)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Messages envoyes et recus';

-- --------------------------------------------------------

--
-- Structure de la table `message_credits`
--

CREATE TABLE `message_credits` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL COMMENT 'Code utilisateur',
  `total_credits` int(11) DEFAULT 0,
  `used_credits` int(11) DEFAULT 0,
  `remaining_credits` int(11) DEFAULT 0,
  `last_purchase_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Credits messages';

--
-- Déchargement des données de la table `message_credits`
--

INSERT INTO `message_credits` (`id`, `user_code`, `total_credits`, `used_credits`, `remaining_credits`, `last_purchase_at`, `created_at`, `updated_at`) VALUES
(1, 'A2', 100, 0, 100, NULL, '2026-05-06 13:46:37', '2026-05-06 13:46:37'),
(17, 'A3', 100, 0, 100, NULL, '2026-05-06 14:45:25', '2026-05-06 14:45:25'),
(40, 'A1', 100, 0, 100, NULL, '2026-05-06 22:54:42', '2026-05-06 22:54:42');

-- --------------------------------------------------------

--
-- Structure de la table `message_credit_purchases`
--

CREATE TABLE `message_credit_purchases` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `credits_amount` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `is_gift` tinyint(1) DEFAULT 0,
  `recipient_user_code` varchar(10) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `purchased_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique achats credits';

-- --------------------------------------------------------

--
-- Structure de la table `message_filters`
--

CREATE TABLE `message_filters` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `filter_type` enum('keyword','sender','priority') NOT NULL,
  `filter_value` varchar(255) NOT NULL,
  `action` enum('priority','hide','notify') DEFAULT 'priority',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Filtres messages';

-- --------------------------------------------------------

--
-- Structure de la table `message_notifications`
--

CREATE TABLE `message_notifications` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `message_id` int(11) DEFAULT NULL,
  `type` enum('new_message','bulk_message','blocked','access_request','follow_request') NOT NULL DEFAULT 'new_message',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications messages';

-- --------------------------------------------------------

--
-- Structure de la table `message_threads`
--

CREATE TABLE `message_threads` (
  `id` int(11) NOT NULL,
  `participant_1` varchar(10) NOT NULL,
  `participant_2` varchar(10) NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_message_preview` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conversations';

-- --------------------------------------------------------

--
-- Structure de la table `payment_history`
--

CREATE TABLE `payment_history` (
  `id` int(11) NOT NULL,
  `sender_code` varchar(20) NOT NULL COMMENT 'Code utilisateur qui a paye',
  `recipient_code` varchar(20) NOT NULL COMMENT 'Code utilisateur qui a recu les jours',
  `recipient_name` varchar(255) NOT NULL COMMENT 'Nom du destinataire',
  `days_added` int(11) NOT NULL COMMENT 'Nombre de jours ajoutes',
  `amount_paid` decimal(10,2) NOT NULL COMMENT 'Montant paye en USD',
  `reference` varchar(100) NOT NULL COMMENT 'Reference transaction FlexPay',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date du paiement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des prolongations';

--
-- Déchargement des données de la table `payment_history`
--

INSERT INTO `payment_history` (`id`, `sender_code`, `recipient_code`, `recipient_name`, `days_added`, `amount_paid`, `reference`, `created_at`) VALUES
(1, 'A3', 'A3', 'Ntoma', 60, 2.00, 'GNT-A3-1778086311-69fb71a7b4d26', '2026-05-06 18:52:29'),
(2, 'A2', 'A2', 'Celenia', 60, 2.00, 'GNT-A2-1778169962-69fcb86a6b0ff', '2026-05-07 18:07:00');

-- --------------------------------------------------------

--
-- Structure de la table `payment_sessions`
--

CREATE TABLE `payment_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `days_to_add` int(11) NOT NULL,
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `payment_sessions`
--

INSERT INTO `payment_sessions` (`id`, `session_id`, `user_code`, `amount`, `days_to_add`, `status`, `reference`, `created_at`) VALUES
(1, 'ce4d892aaf2da3f42b4b25315c92846d_1778079768', 'A3', 2.00, 60, 'pending', 'GNT-A3-1778079768-69fb581862bee', '2026-05-06 15:02:48'),
(2, 'ce4d892aaf2da3f42b4b25315c92846d_1778086311', 'A3', 2.00, 60, 'pending', 'GNT-A3-1778086311-69fb71a7b4d26', '2026-05-06 16:51:51'),
(3, '326fa6e9bbf5482d3276219a79b38a48_1778169962', 'A2', 2.00, 60, 'pending', 'GNT-A2-1778169962-69fcb86a6b0ff', '2026-05-07 16:06:02');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `commune` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'RDC',
  `bio` text DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `sub_status` enum('trial','active','expired') DEFAULT 'trial',
  `sub_expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `profile_visibility` enum('public','friends','private') DEFAULT 'public',
  `access_request_credits` int(11) UNSIGNED NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `user_code`, `name`, `first_name`, `last_name`, `gender`, `birth_date`, `city`, `commune`, `country`, `bio`, `email`, `phone`, `password`, `sub_status`, `sub_expires_at`, `created_at`, `otp_code`, `otp_expires_at`, `profile_pic`, `profile_visibility`, `access_request_credits`) VALUES
(1, 'A1', 'Gladis', NULL, NULL, NULL, NULL, NULL, NULL, 'RDC', NULL, 'precieuxmwatha@gmail.com', NULL, '$2y$12$WlvW1rTs18MjKSQHpN7YKum5780l5q/Tllxizp.IDHBwooq32qToC', 'expired', '2026-05-04 13:34:36', '2026-05-02 13:34:36', NULL, NULL, NULL, 'public', 100),
(2, 'A2', 'Celenia', NULL, NULL, NULL, NULL, NULL, NULL, 'RDC', NULL, 'sethmwatha@gmail.com', NULL, '$2y$12$y3JGFJ.XTrR8TUiZvL7ZfeNB0J2GKYQ9/WWYfKefk5owlLiMLg1M2', 'active', '2026-07-07 13:46:37', '2026-05-06 13:46:37', NULL, NULL, 'uploads/profiles/a2_image1.jpeg', 'public', 100),
(3, 'A3', 'Ntoma', NULL, NULL, NULL, NULL, NULL, NULL, 'RDC', NULL, 'kandalaseth@gmail.com', NULL, '$2y$12$li7bA99kLYNxcVpNRlnn5.5jOYDbJHH0GSOvbpz0DR39o5IjqnqGC', 'active', '2026-07-07 14:45:25', '2026-05-06 14:45:25', NULL, NULL, NULL, 'public', 100);

-- --------------------------------------------------------

--
-- Structure de la table `user_blocks`
--

CREATE TABLE `user_blocks` (
  `id` int(11) NOT NULL,
  `blocker_user_code` varchar(10) NOT NULL,
  `blocked_user_code` varchar(10) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blocages utilisateurs';

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `access_requests`
--
ALTER TABLE `access_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request_number` (`request_number`),
  ADD KEY `idx_journal_id` (`journal_id`),
  ADD KEY `idx_requester` (`requester_user_code`),
  ADD KEY `idx_author` (`author_user_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_journal_requester_status` (`journal_id`,`requester_user_code`,`status`),
  ADD KEY `idx_author_status_created` (`author_user_code`,`status`,`created_at`);

--
-- Index pour la table `access_request_counters`
--
ALTER TABLE `access_request_counters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `journal_id` (`journal_id`);

--
-- Index pour la table `author_follows`
--
ALTER TABLE `author_follows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_author_follows` (`follower_user_code`,`followed_user_code`),
  ADD KEY `idx_author_follows_follower` (`follower_user_code`),
  ADD KEY `idx_author_follows_followed` (`followed_user_code`),
  ADD KEY `idx_author_follows_date` (`followed_at`);

--
-- Index pour la table `author_ranking`
--
ALTER TABLE `author_ranking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_month` (`user_code`,`month`),
  ADD KEY `position` (`position`),
  ADD KEY `score` (`score`);

--
-- Index pour la table `follow_requests`
--
ALTER TABLE `follow_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_follow_requests_number` (`request_number`),
  ADD KEY `idx_follow_requests_requester` (`requester_user_code`),
  ADD KEY `idx_follow_requests_followed` (`followed_user_code`),
  ADD KEY `idx_follow_requests_status` (`status`),
  ADD KEY `idx_follow_requests_created` (`created_at`),
  ADD KEY `idx_followed_status_created` (`followed_user_code`,`status`,`created_at`);

--
-- Index pour la table `follow_request_counters`
--
ALTER TABLE `follow_request_counters`
  ADD PRIMARY KEY (`followed_user_code`);

--
-- Index pour la table `geo_cities`
--
ALTER TABLE `geo_cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_geo_cities_country_name` (`country_code`,`name`),
  ADD KEY `idx_geo_cities_population` (`population`);

--
-- Index pour la table `geo_communes`
--
ALTER TABLE `geo_communes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_geo_communes_city_name` (`city_id`,`name`);

--
-- Index pour la table `geo_countries`
--
ALTER TABLE `geo_countries`
  ADD PRIMARY KEY (`code`);

--
-- Index pour la table `journals`
--
ALTER TABLE `journals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_code` (`user_code`),
  ADD KEY `idx_keywords` (`keywords`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_user_status` (`user_code`,`status`);

--
-- Index pour la table `journal_comments`
--
ALTER TABLE `journal_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_journal_id` (`journal_id`),
  ADD KEY `idx_user_code` (`user_code`),
  ADD KEY `idx_author_code` (`author_user_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Index pour la table `journal_pages`
--
ALTER TABLE `journal_pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_id` (`journal_id`),
  ADD KEY `page_order` (`page_order`);

--
-- Index pour la table `journal_readers`
--
ALTER TABLE `journal_readers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reader` (`journal_id`,`user_code`);

--
-- Index pour la table `journal_views`
--
ALTER TABLE `journal_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_id` (`journal_id`),
  ADD KEY `viewer_code` (`viewer_code`),
  ADD KEY `viewed_at` (`viewed_at`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread` (`thread_id`),
  ADD KEY `idx_sender` (`sender_user_code`),
  ADD KEY `idx_recipient` (`recipient_user_code`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_thread_created` (`thread_id`,`created_at`),
  ADD KEY `idx_keywords` (`keywords`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Index pour la table `message_credits`
--
ALTER TABLE `message_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_code` (`user_code`);

--
-- Index pour la table `message_credit_purchases`
--
ALTER TABLE `message_credit_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_code` (`user_code`),
  ADD KEY `idx_recipient` (`recipient_user_code`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `message_filters`
--
ALTER TABLE `message_filters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_code` (`user_code`),
  ADD KEY `idx_filter_type` (`filter_type`);

--
-- Index pour la table `message_notifications`
--
ALTER TABLE `message_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_code` (`user_code`),
  ADD KEY `idx_message` (`message_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_user_unread` (`user_code`,`is_read`,`created_at`);

--
-- Index pour la table `message_threads`
--
ALTER TABLE `message_threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_participants` (`participant_1`,`participant_2`),
  ADD KEY `idx_participant_1` (`participant_1`),
  ADD KEY `idx_participant_2` (`participant_2`),
  ADD KEY `idx_last_message` (`last_message_at`);

--
-- Index pour la table `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender` (`sender_code`),
  ADD KEY `idx_recipient` (`recipient_code`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `payment_sessions`
--
ALTER TABLE `payment_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `payment_sessions_ibfk_1` (`user_code`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_code` (`user_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_block` (`blocker_user_code`,`blocked_user_code`),
  ADD KEY `idx_blocker` (`blocker_user_code`),
  ADD KEY `idx_blocked` (`blocked_user_code`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `access_requests`
--
ALTER TABLE `access_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `access_request_counters`
--
ALTER TABLE `access_request_counters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `author_follows`
--
ALTER TABLE `author_follows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `author_ranking`
--
ALTER TABLE `author_ranking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `follow_requests`
--
ALTER TABLE `follow_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `geo_cities`
--
ALTER TABLE `geo_cities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT pour la table `geo_communes`
--
ALTER TABLE `geo_communes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT pour la table `journals`
--
ALTER TABLE `journals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `journal_comments`
--
ALTER TABLE `journal_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `journal_pages`
--
ALTER TABLE `journal_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `journal_readers`
--
ALTER TABLE `journal_readers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `journal_views`
--
ALTER TABLE `journal_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `message_credits`
--
ALTER TABLE `message_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT pour la table `message_credit_purchases`
--
ALTER TABLE `message_credit_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `message_filters`
--
ALTER TABLE `message_filters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `message_notifications`
--
ALTER TABLE `message_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `message_threads`
--
ALTER TABLE `message_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `payment_sessions`
--
ALTER TABLE `payment_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `user_blocks`
--
ALTER TABLE `user_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `access_requests`
--
ALTER TABLE `access_requests`
  ADD CONSTRAINT `fk_access_requests_journal` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `access_request_counters`
--
ALTER TABLE `access_request_counters`
  ADD CONSTRAINT `fk_counter_journal` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `author_ranking`
--
ALTER TABLE `author_ranking`
  ADD CONSTRAINT `author_ranking_ibfk_1` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`);

--
-- Contraintes pour la table `journals`
--
ALTER TABLE `journals`
  ADD CONSTRAINT `journals_ibfk_1` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`);

--
-- Contraintes pour la table `journal_comments`
--
ALTER TABLE `journal_comments`
  ADD CONSTRAINT `fk_comments_journal` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `journal_comments` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `journal_pages`
--
ALTER TABLE `journal_pages`
  ADD CONSTRAINT `journal_pages_ibfk_1` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `journal_readers`
--
ALTER TABLE `journal_readers`
  ADD CONSTRAINT `fk_readers_journal` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `journal_views`
--
ALTER TABLE `journal_views`
  ADD CONSTRAINT `journal_views_ibfk_1` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `journal_views_ibfk_2` FOREIGN KEY (`viewer_code`) REFERENCES `users` (`user_code`) ON DELETE SET NULL;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_recipient` FOREIGN KEY (`recipient_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `message_credits`
--
ALTER TABLE `message_credits`
  ADD CONSTRAINT `fk_credits_user` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE;

--
-- Contraintes pour la table `message_credit_purchases`
--
ALTER TABLE `message_credit_purchases`
  ADD CONSTRAINT `fk_purchases_recipient` FOREIGN KEY (`recipient_user_code`) REFERENCES `users` (`user_code`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchases_user` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE;

--
-- Contraintes pour la table `message_filters`
--
ALTER TABLE `message_filters`
  ADD CONSTRAINT `fk_filter_user` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE;

--
-- Contraintes pour la table `message_notifications`
--
ALTER TABLE `message_notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE;

--
-- Contraintes pour la table `message_threads`
--
ALTER TABLE `message_threads`
  ADD CONSTRAINT `fk_thread_p1` FOREIGN KEY (`participant_1`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_thread_p2` FOREIGN KEY (`participant_2`) REFERENCES `users` (`user_code`) ON DELETE CASCADE;

--
-- Contraintes pour la table `payment_sessions`
--
ALTER TABLE `payment_sessions`
  ADD CONSTRAINT `payment_sessions_ibfk_1` FOREIGN KEY (`user_code`) REFERENCES `users` (`user_code`);

--
-- Contraintes pour la table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `fk_blocked` FOREIGN KEY (`blocked_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blocker` FOREIGN KEY (`blocker_user_code`) REFERENCES `users` (`user_code`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
