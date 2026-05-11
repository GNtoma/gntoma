-- ============================================================
-- MIGRATION 002 : Évolutions UI/UX — Pas de changement de schéma
-- GNTOMA — traçabilité des évolutions applicatives
-- ============================================================
--
-- Cette migration couvre les évolutions suivantes (pure UI/PHP) :
-- 1. Badge pop-up pulsant sur bouton "Demandes" du dashboard
-- 2. Bouton "Discuter" visible sur toutes les pages de demande d'accès
-- 3. Fusion boutons Messages / En masse → un seul bouton Messages
-- 4. Bouton Retour après envoi de demande d'accès
-- 5. Notification SYSTEM envoyée à l'auteur lors d'une nouvelle demande
--
-- Aucun changement de structure n'est requis pour ces évolutions.
-- La migration 001 fournit déjà toutes les tables nécessaires.
--
-- ============================================================

-- ============================================================
-- Sécurisation : utilisateur SYSTEM pour les notifications auto
-- ============================================================

-- L'application envoie désormais des notifications automatiques avec
-- sender_user_code = 'SYSTEM'. Si une contrainte de clé étrangère existe
-- sur messages.sender_user_code, créer l'utilisateur factice ci-dessous.

INSERT IGNORE INTO users (user_code, first_name, last_name, email, phone, password, city, country, is_admin, created_at)
VALUES (
    'SYSTEM',
    'Système',
    'GNTOMA',
    'system@gntoma.local',
    '0000000000',
    '$2y$10$system.placeholder.hash.not.used.for.auth',
    'Système',
    'GNTOMA',
    0,
    NOW()
);
