-- ============================================================
-- MIGRATION 001 : Système unifié de demandes d'accès et suivi
-- GNTOMA — idempotente
-- ============================================================

-- 1. Crédits de demandes d'accès sur l'utilisateur
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS access_request_credits INT NOT NULL DEFAULT 100
    COMMENT 'Crédits pour envoyer des demandes d accès à des journaux payants';

-- 2. Compteur de demandes d'accès par journal (numéros D1, D2...)
CREATE TABLE IF NOT EXISTS access_request_counters (
    journal_id INT NOT NULL,
    last_request_number INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (journal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Génère les numéros de demande d accès D1, D2... par journal';

-- 3. Demandes d'accès aux journaux payants
CREATE TABLE IF NOT EXISTS access_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(20) NOT NULL,
    journal_id INT NOT NULL,
    requester_user_code VARCHAR(50) NOT NULL,
    author_user_code VARCHAR(50) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    message TEXT,
    response_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_journal_id (journal_id),
    INDEX idx_requester (requester_user_code),
    INDEX idx_author (author_user_code),
    INDEX idx_status (status),
    INDEX idx_request_number (request_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Demandes d accès aux journaux payants (système D1, D2...)';

-- 4. Lecteurs approuvés d'un journal
CREATE TABLE IF NOT EXISTS journal_readers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_id INT NOT NULL,
    user_code VARCHAR(50) NOT NULL,
    access_count INT NOT NULL DEFAULT 1,
    last_access_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reader (journal_id, user_code),
    INDEX idx_journal (journal_id),
    INDEX idx_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Lecteurs ayant obtenu l accès à un journal payant';

-- 5. Compteur de demandes de suivi par auteur (numéros F1, F2...)
CREATE TABLE IF NOT EXISTS follow_request_counters (
    followed_user_code VARCHAR(50) NOT NULL,
    last_request_number INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (followed_user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Génère les numéros de demande de suivi F1, F2... par auteur';

-- 6. Demandes de suivi d'auteur
CREATE TABLE IF NOT EXISTS follow_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(20) NOT NULL,
    requester_user_code VARCHAR(50) NOT NULL,
    followed_user_code VARCHAR(50) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    message TEXT,
    response_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_requester (requester_user_code),
    INDEX idx_followed (followed_user_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Demandes de suivi d auteur (système F1, F2...)';

-- 7. Relations de suivi actives
CREATE TABLE IF NOT EXISTS author_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_user_code VARCHAR(50) NOT NULL,
    followed_user_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_user_code, followed_user_code),
    INDEX idx_follower (follower_user_code),
    INDEX idx_followed (followed_user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Relations de suivi entre auteurs actives';

-- 8. Crédits de messages
CREATE TABLE IF NOT EXISTS message_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(50) NOT NULL,
    total_credits INT NOT NULL DEFAULT 100,
    used_credits INT NOT NULL DEFAULT 0,
    remaining_credits INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Crédits pour envoyer des messages (simples et en masse)';

-- 9. Notifications de demandes
CREATE TABLE IF NOT EXISTS message_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(50) NOT NULL,
    message_id INT NOT NULL,
    type ENUM('message', 'access_request', 'follow_request', 'comment') NOT NULL DEFAULT 'message',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_code),
    INDEX idx_type (type),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT 'Notifications pour messages, demandes d accès et demandes de suivi';
