# GNTOMA — Carte rapide BDD → code PHP

Référence schéma : export `sc3mwse0880_jm.sql` (non versionné par défaut). Les chemins ci-dessous sont relatifs au dossier `journal/` sauf mention contraire.

## Utilisateurs, abonnement, blocages

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `users` | Comptes, abonnement (`sub_*`), profil, `access_request_credits` | `auth_*`, `dashboard_6.php`, `profile_edit.php`, `security_check.php` |
| `user_blocks` | Blocages entre utilisateurs | Rechercher `user_blocks` dans `journal/` |
| `message_credits` | Solde crédits messages | `message_send_process.php`, `message_bulk_process.php`, achats crédits |
| `message_credit_purchases` | Historique achats crédits | Scripts paiement / crédits messages |

## Journaux, pages, audience

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `journals` | Métadonnées journal (statut `public` / `private` / `paid`, prix, devise) | `journal_create_*`, `journal_edit_*`, `journal_view.php`, `search_code.php` |
| `journal_pages` | Contenu des pages | `page_*`, `journal_view.php` |
| `journal_readers` | Lecteurs autorisés (après approbation) | `journal_request_approve.php`, `journal_view.php` |
| `journal_comments` | Commentaires sur un journal | `journal_comment_add.php`, `journal_view.php` |
| `journal_views` | Compteur / historique de vues | `search_code.php` (agrégations) ; insertion vue à confirmer selon version |

## Demandes d’accès (journaux payants)

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `access_requests` | Demandes lecteur → auteur | `journal_access_request.php`, `journal_request_approve.php`, `journal_requests_list.php`, `journal_view.php` |
| `access_request_counters` | Numérotation des demandes par journal | `journal_access_request.php` |

## Suivi d’auteurs

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `follow_requests` | Demandes pour suivre un auteur | `journal_access_request.php` (flux unifié), listes / approbations associées |
| `follow_request_counters` | Compteurs de demandes de suivi | `journal_access_request.php` |
| `author_follows` | Relation suiveur → auteur | `journal_access_request.php`, `follow_requests_list.php`, `dashboard_b_6.php`, `following_feed.php` |

## Messagerie

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `message_threads` | Conversations (paire de participants) | `message_send_process.php`, `message_chat.php`, `messages_list.php` |
| `messages` | Messages (dont `expires_at` 21 jours) | `message_send_process.php`, `message_bulk_process.php`, `message_chat*.php`, `cron_purge_expired_messages.php` |
| `message_notifications` | Notifications (y compris types accès / suivi) | `message_send_process.php`, `message_bulk_process.php`, `journal_access_request.php`, `journal_request_approve.php` |
| `message_filters` | Filtres utilisateur | `messages_filters.php` |

## Paiements plateforme (FlexPay)

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `payment_sessions` | Sessions de paiement en cours | `payment_session_handler.php`, `payment_init_11.php`, `payment_verify_12.php` |
| `payment_history` | Historique des paiements | Vérifications / rapports paiement |

## Géolocalisation (profil / recherche)

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `geo_countries`, `geo_cities`, `geo_communes` | Référentiel géo | Inscription, édition profil, filtres messagerie de masse |

## Classement auteurs

| Table | Rôle | Fichiers PHP (indicatif) |
|-------|------|---------------------------|
| `author_ranking` | Scores / positions (schéma présent) | Aucun fichier `ranking_*.php` dans ce dépôt ; à relier au job de classement configuré sur l’hébergement si présent. |

---

Pour trouver rapidement les usages d’une table : `rg "nom_table" journal/`.
