# GNT - Plateforme de Journaux Intelligents

## Identite produit et conventions

Les conventions metier, visuelles et emotionnelles de reference du projet sont documentees dans `GNTOMA_CONVENTIONS.md`.
Ce document sert de source de verite pour maintenir la coherence UX, technique et editoriale de GNTOMA.

La memoire continue du projet (etat, decisions, historique recent, priorites) est maintenue dans `PROJECT_MEMORY.md`.

SaaS platform pour créer, gérer et monétiser des journaux numériques.

## Dépôt, local et production

| Environnement | Emplacement |
|----------------|-------------|
| Développement local | `C:\Users\USER\Documents\seth\gntoma` |
| Site en ligne | [https://gntoma.com](https://gntoma.com) |
| Historique Git (GitHub) | Créez le dépôt sur le compte associé à **precieuxmwatha@gmail.com**, puis `git remote add origin …` et `git push` (voir section ci-dessous). |

## Configuration base de données

1. Copier `journal/config.local.php.example` vers `journal/config.local.php`.
2. Renseigner hôte, nom de base, utilisateur et mot de passe MySQL.
3. Sur l’hébergement (ex. gntoma.com), déposer le même `config.local.php` **hors** contrôle Git.

Alternative : variables d’environnement `GNTOMA_DB_HOST`, `GNTOMA_DB_NAME`, `GNTOMA_DB_USER`, `GNTOMA_DB_PASSWORD`.

## Structure du Projet

```
/
├── index.php (redirection vers journal/)
├── phpmail/ (bibliothèque PHPMailer)
│   └── src/
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
├── journal/ (tous les fichiers PHP)
│   ├── db.php
│   ├── schema.sql
│   ├── email_send_code.php
│   ├── auth_login.php
│   ├── auth_register.php
│   ├── auth_otp.php
│   ├── auth_logout.php
│   ├── security_check.php
│   ├── index.php
│   ├── journal_create.php
│   ├── journal_view.php
│   ├── journal_search.php
│   ├── page_add.php
│   ├── page_view.php
│   ├── access_approve.php
│   ├── access_reject.php
│   ├── chat_view.php
│   ├── payment_init.php
│   ├── payment_validate.php
│   ├── payment_cancel.php
│   ├── payment_success.php
│   ├── ranking_calculate.php
│   └── ranking_view.php
└── images/ (toutes les images)
```

## Installation

### 1. Configuration de la Base de Données

```sql
CREATE DATABASE gnt_platform;
```

Importer le schema:
```bash
mysql -u root -p gnt_platform < journal/schema.sql
```

### 2. Configuration de la Connexion

Modifier `journal/db.php` avec vos credentials:
```php
$host = 'localhost';
$dbname = 'gnt_platform';
$username = 'root';
$password = '';
```

### 3. Configuration Flexpay

Modifier `journal/payment_init.php`:
```php
$authorization = "VOTRE_TOKEN_FLEXPAY";
$merchant = "DGB";
```

Modifier les URLs selon votre domaine:
```php
"callback_url" => "https://votresite.com/journal/payment_cancel.php",
"approve_url" => "https://votresite.com/journal/payment_validate.php",
"cancel_url" => "https://votresite.com/journal/payment_cancel.php",
"decline_url" => "https://votresite.com/journal/payment_cancel.php",
"home_url" => "https://votresite.com"
```

### 4. Configuration Email (PHPMailer)

Modifier `journal/email_send_code.php` avec vos credentials SMTP:
```php
$mail->Host       = 'smtp.gmail.com'; // ou votre serveur SMTP
$mail->Username   = 'hello@gntoma.com';
$mail->Password   = 'VOTRE_MOT_DE_PASSE_SMTP'; // Utiliser mot de passe d'application Gmail
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;
```

Pour Gmail, activer l'authentification en 2 étapes et générer un mot de passe d'application.

### 5. Permissions

Assurez-vous que le dossier `images/` est writable:
```bash
chmod 755 images
```

### 6. Cron Job pour le Classement

Ajouter un cron job mensuel pour calculer le classement:
```bash
0 0 1 * * curl http://votresite.com/journal/ranking_calculate.php
```

## Fonctionnalités

### Authentification
- Inscription avec email et mot de passe
- Email de bienvenue avec identifiant utilisateur (A1, A2...)
- Connexion avec ID (A1, A2...) ou email vérifié
- OTP email optionnel (5 chiffres, 5 minutes)
- 48 heures d'essai gratuit

### Journaux
- Création de journaux multiples (A1J1, A1J2...)
- Pages avec texte et image optionnelle
- Types d'accès: public, privé, payant
- Système de demande d'accès

### Messagerie
- Conversations entre utilisateurs
- Création automatique lors d'une demande d'accès

### Abonnement
- 48h gratuit après inscription
- Plans: 2 USD/30 jours ou 3 USD/60 jours
- Blocage si abonnement expiré

### Paiement Flexpay
- Redirection vers Flexpay
- Validation via approve_url
- Sécurité par session

### Classement
- Calcul mensuel: vues, demandes, acceptations
- Score pondéré
- Positionnement des auteurs

## Sécurité

- Vérification de session sur chaque page
- Vérification de l'abonnement actif
- Contrôle d'accès aux contenus
- Requêtes SQL préparées
- Protection des uploads
- password_hash pour les mots de passe

## Règles Strictes Respectées

✅ Structure plate (pas de sous-dossiers)
✅ Nommage des fichiers avec préfixes
✅ IDs utilisateurs auto-incrémentés (A1, A2...)
✅ IDs journaux formatés (A1J1, A1J2...)
✅ Images nommées (A1_image_1.jpg)
✅ Technologie: PHP, MySQL, HTML, Tailwind CSS, Animate.css
✅ Sécurité obligatoire sur toutes les pages

## Déploiement

1. Uploader les fichiers (sans `journal/config.local.php` depuis Git : le créer sur le serveur).
2. Copier `journal/config.local.php.example` → `journal/config.local.php` sur le serveur avec les identifiants MySQL de production.
3. Configurer Flexpay avec vos credentials
4. Configurer PHPMailer avec vos credentials SMTP
5. Tester l'inscription et l'envoi d'email
6. Tester le paiement
7. Configurer le cron job pour le classement

## Git et GitHub

Sur la machine locale, après `git init` et le premier commit :

1. Sur [GitHub](https://github.com), connectez-vous avec le compte lié à **precieuxmwatha@gmail.com**.
2. **New repository** → nom conseillé : `gntoma` → créez le dépôt (privé recommandé tant que le code évolue).
3. Dans le dossier du projet :

```bash
git remote add origin https://github.com/VOTRE_UTILISATEUR/gntoma.git
git branch -M main
git push -u origin main
```

Avec [GitHub CLI](https://cli.github.com/) (`gh`), si vous êtes déjà authentifié (`gh auth login`) :

```bash
gh repo create gntoma --private --source=. --remote=origin --push
```

L’**historique** du projet = commits sur `main` (ou branches `feature/*`). Les **Issues** et **Pull Requests** sur GitHub servent au suivi collaboratif.

## Support

Pour toute question, consultez la documentation du projet.
