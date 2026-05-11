# PROJECT MEMORY - GNTOMA

Ce fichier est la memoire continue du projet. Il sert de reference unique pour conserver:

- l'identite produit
- les conventions
- l'architecture
- les decisions prises
- l'etat technique actuel
- les prochaines priorites

Il doit etre mis a jour apres chaque changement significatif.

## 1) Vision produit (canonique)

GNTOMA est une plateforme editoriale premium mobile-first qui melange:

- journal personnel
- publication premium
- relation auteur/lecteur
- messagerie sociale
- geolocalisation intelligente
- vente de contenus
- demandes de prestations intellectuelles/documentaires

Objectif: ne pas seulement fonctionner, mais etre ressenti (calme, elegance, profondeur).

## 2) Sources de verite

- Conventions globales: `GNTOMA_CONVENTIONS.md`
- Vue projet et installation: `README.md`
- Structure base de donnees: `___sc3mwse0880_jm.sql`

## 3) Architecture actuelle

Racine principale:

- `index.php`: landing + login + reset password
- `journal/`: coeur SaaS (auth, dashboard, journaux, messages, paiements)
- `carnetdeloyer/`: module metier annexe
- `pharmacie/`: module metier annexe
- `phpmail/`: dependance PHPMailer
- `uploads/`, `images/`: assets runtime/media

Notes:

- style volontairement "flat files"
- stack principale: PHP + MySQL + HTMX + Tailwind/Bootstrap selon ecrans

## 4) Schema metier DB (resume)

Tables pivots:

- `users`: comptes, abonnement, OTP, profil, et **`access_request_credits`** (credits pour envoyer des demandes d'acces aux journaux `paid`)
- `journals`: univers editoriaux
- `journal_pages`: contenu par pages
- `journal_comments`: commentaires et reponses
- `access_requests`: demandes d'acces aux journaux payants (`paid`)
- `follow_requests` + `author_follows`: demande pour suivre un auteur, puis lien de suivi actif
- `messages` + `message_threads`: messagerie (credits messages a l'envoi)
- `message_credits`: solde d'envoi de messages par utilisateur
- `payment_sessions` + `payment_history`: paiement abonnement
- `geo_*`: referentiels geographiques

Regles identite:

- User code: `A1`, `A2`, ...
- Journal: `A1J1`, `A1J2`, ...

### Statuts `journals.status` (regles canoniques produit)

- **`private`**: le journal n'est visible **que par son auteur** (pas de lecture publique, pas de flux "demande d'acces" pour les autres).
- **`public`**: visible par **tous**; **aucune** validation d'acces par l'auteur.
- **`paid`**: le journal est **public au sens "decouvrable / exposé"** (comme un offre editoriale), mais **chaque lecteur** doit **contacter l'auteur** et passer par **demande d'acces** (messagerie + validation) avant lecture du contenu protege.

### Demandes d'acces journal (`access_requests`)

- Apres **activation / approbation** par l'auteur, le lecteur a un **acces au contenu indefini**.
- **Une seule demande `pending` a la fois** par couple (lecteur, journal): deja applique dans `journal_access_request.php` (message d'erreur si une pending existe deja).
- Chaque nouvelle demande (hors cas deja traite) consomme **`access_request_credits`** sur le compte du demandeur (aligne code + produit).

### Credits (messages et demandes journaux payants)

- **Messages**: solde dans `message_credits` (`remaining_credits`), consomme a chaque envoi (voir `message_send_process.php`).
- **Demandes d'acces aux journaux `paid`**: compteur `users.access_request_credits`, decremente lors de la creation d'une demande dans `access_requests` (`journal_access_request.php`).

### Suivi d'auteur vs acces a un journal

- Les deux mecanismes **coexistent toujours**: `follow_requests` / `author_follows` ne remplacent pas `access_requests`.
- **Suivre un auteur** contribue notamment a: le faire remonter parmi les **auteurs privilegies en recherche par mots-cles**; permettre de **valoriser** les auteurs avec beaucoup de suiveurs.
- **Comptage des suiveurs**: seules les personnes dont le **temps de suivi est encore valide** sont comptees; quand le temps d'un suiveur est ecoule, le nombre de suiveurs pour cet auteur **diminue** (suiveurs actifs uniquement).

## 5) Decisions actives

- Priorite UX emotionnelle premium (respiration visuelle, fluidite, mobile-first)
- Conserver architecture plate tant qu'elle reste maintenable
- Privilegier HTMX pour interactions partielles
- Garder les pages critiques robustes aux erreurs (fallbacks + logs)
- Traiter l'acces aux journaux verrouilles comme une experience immersive (et pas un simple formulaire)
- Integrer un flux "demande documentaire" avec budget/delai/qualite/categorie pro
- Toujours clarifier les choix structurants avant implementation (pas de supposition critique)
- Regle validee: apres acceptation d'une demande d'acces journal, l'acces est permanent (paiement gere hors site entre lecteurs et auteur)
- Statuts journal: `private` = auteur seul; `public` = ouvert sans validation; `paid` = visible comme offre mais acces contenu via demande + auteur
- Suivi auteur: privilegier en recherche par mot-cle + gratification liee au volume de suiveurs **actifs** (duree de suivi a respecter)
- Acces journal payant: une pending par lecteur/journal; acces indefini une fois approuve
- Credits: **messages** (`message_credits`) + **demandes d'acces journaux payants** (`users.access_request_credits`) — aucun autre usage produit n'est documente pour l'instant

## 6) Etat de reference (aujourd'hui)

- Projet local: `C:\Users\USER\Documents\seth\gntoma`
- Production: `https://gntoma.com`
- Git: depot initialise localement; remote GitHub a ajouter sur le compte lie a **precieuxmwatha@gmail.com** (voir `README.md` section Git et GitHub)
- Base structure (reference): `sc3mwse0880_jm.sql` (fichier dump non versionne par defaut dans `.gitignore`)
- Conventions formalisees dans `GNTOMA_CONVENTIONS.md`

## 7) Regles de mise a jour de cette memoire

A chaque intervention importante, mettre a jour au minimum:

1. "Dernieres modifications"
2. "Decisions actives" (si impact)
3. "Prochaines actions"
4. "Risques/points d'attention"

## 8) Dernieres modifications

- [2026-05-11] Creation du fichier memoire continue (`PROJECT_MEMORY.md`).
- [2026-05-11] Ajout du document de conventions globales (`GNTOMA_CONVENTIONS.md`).
- [2026-05-11] Liaison de la documentation conventions dans `README.md`.
- [2026-05-11] Analyse de la structure DB via `___sc3mwse0880_jm.sql`.
- [2026-05-11] Extension des conventions: economie creative, demandes documentaires, experience journal verrouille premium.
- [2026-05-11] Ajout de la regle de clarification explicite: poser des questions avant tout choix important.
- [2026-05-11] UX recherche: ajout d'une modale premium "Demander l'acces" pour journaux payants.
- [2026-05-11] Demandes d'acces: affichage social enrichi (photo, identite visuelle demandeur) dans `journal_requests_list.php`.
- [2026-05-11] Conversations demande d'acces: propagation d'un contexte `access_request` entre demandes et messagerie.
- [2026-05-11] Chat: ajout d'un rafraichissement semi temps reel via HTMX (`message_chat_partial.php`, polling 4s).
- [2026-05-11] Decision produit figee: acces journal permanent apres approbation auteur.
- [2026-05-11] Verification technique flux acces: `journal_request_approve.php` (action `approve`) met a jour `access_requests`, insere ou met a jour `journal_readers`, recalcule `journals.reader_count`. `journal_view.php` ouvre le contenu payant si le lecteur est dans `journal_readers` ou si sa derniere demande pour ce journal est `approved`. Messagerie: liens `message_send.php` avec `context=access_request` pour auteur et demandeur.
- [2026-05-11] Clarification produit: statuts journal — `private` visible auteur seul; `public` pour tous sans validation; `paid` public comme offre mais acces contenu via contact auteur + demande d'acces.
- [2026-05-11] Regles sociales: `follow_requests`/`author_follows` coexistent avec `access_requests`; suivi auteur = boost recherche par mot-cle + gratification; compteur suiveurs = uniquement suiveurs avec temps encore valide (schema actuel incomplet pour cette duree). Confirmation code: une seule `access_requests` pending par lecteur/journal dans `journal_access_request.php`.
- [2026-05-11] Clarification credits: les credits servent a l'**envoi de messages** (`message_credits`) et a l'**envoi de demandes d'acces** pour journaux **payants** (`users.access_request_credits` + `access_requests`).
- [2026-05-11] Git: ajout `.gitignore`, externalisation BDD via `journal/config.local.php` (non versionne) + `config.local.php.example`, `git init` + premier commit; instructions GitHub dans `README.md` (compte precieuxmwatha@gmail.com). URLs: local `C:\Users\USER\Documents\seth\gntoma`, prod `https://gntoma.com`.

## 9) Risques / points d'attention

- Secrets/config potentiellement en clair dans des fichiers PHP de prod.
- Dump SQL contient des donnees reelles (a anonymiser pour environnements dev/partage).
- Ne jamais committer `journal/config.local.php` ni le dump SQL complet avec donnees reelles.
- **Ecart schema / produit (suiveurs actifs)**: la table `author_follows` dans `sc3mwse0880_jm.sql` n'a pas de champ de **fin de validite** du suivi (`expires_at` ou lie a l'abonnement du follower). La regle "ne compter que les suiveurs avec du temps restant" demande une **evolution BDD + logique** (cron ou calcul a la volee). **Independamment**, les **credits** au sens GNTOMA actuel servent aux **messages** et aux **demandes d'acces journaux payants** (pas confondre avec cette regle de comptage des suiveurs tant que la source du "temps suiveur" n'est pas figee).

## 10) Prochaines actions recommandees

1. Creer le depot sur GitHub (compte precieuxmwatha@gmail.com), `git remote add origin`, `git push -u origin main`.
2. Externaliser secrets **SMTP / Flexpay** comme pour la BDD (fichiers locaux ou variables d'environnement).
3. Creer `DB_SCHEMA.md` pour map rapide tables -> pages PHP.
4. Concevoir le spec fonctionnel du module "journal verrouille + demande d'acces + discussion + proposition prix".
5. Concevoir le spec fonctionnel du module "demandes documentaires" (workflow complet demande -> echanges -> livraison).
6. Specifier et implementer la **duree de suivi auteur** (source de verite) + requetes / migration pour `author_follows` et recherche privilegiee.
