-- GNTOMA 003 : Anciennes notifications SYSTEM sans thread_id (demandes d'accès)
-- Avant correction applicative, ces lignes gonflaient le compteur "non lus" sans conversation associée.
-- Exécution optionnelle sur la base de production (une fois) :

UPDATE messages m
SET m.is_read = 1, m.read_at = COALESCE(m.read_at, NOW())
WHERE m.thread_id IS NULL
  AND m.is_read = 0
  AND m.sender_user_code = 'SYSTEM';
