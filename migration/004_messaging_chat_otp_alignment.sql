-- ============================================================
-- MIGRATION 004 : Messagerie (fil de conversation) + OTP reset mot de passe
-- GNTOMA — à appliquer sur les bases **existantes** (production déjà en ligne).
-- Serveur cible : MariaDB 10.5.2+ / 11.x (IF NOT EXISTS colonnes et index).
-- Référence dump complet : sc3mwse0880_jm.sql (réimport = schéma à jour sans cette migration).
-- Idempotente : ré-exécution sans erreur si déjà appliquée.
-- ============================================================

-- 1) Utilisateurs : colonnes OTP (mot de passe oublié → auth_forgot_8.php / auth_reset_9.php)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `otp_code` varchar(6) DEFAULT NULL;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `otp_expires_at` datetime DEFAULT NULL;

-- 2) Messages : index pour WHERE thread_id = ? ORDER BY created_at ASC (message_chat_queries.php)
ALTER TABLE `messages`
  ADD KEY IF NOT EXISTS `idx_thread_created` (`thread_id`, `created_at`);
