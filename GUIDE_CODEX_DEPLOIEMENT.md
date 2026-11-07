# Travailler avec Codex depuis n'importe quel ordinateur

Ce dépôt contient le code PHP/MySQL de GNTOMA. GitHub est la source de code ; le serveur web héberge l'application et les bases MySQL, qui ne sont pas stockées dans Git.

## Accès depuis un autre ordinateur

1. Ouvrir https://github.com/GNtoma/gntoma et travailler avec Codex sur une branche dédiée, ou ouvrir le dépôt dans GitHub Codespaces.
2. Utiliser une branche `feature/nom-du-changement` pour les modifications.
3. Enregistrer les changements (commit), pousser la branche, puis ouvrir une pull request.
4. Relire et valider les changements avant de fusionner dans `main`.

## Organisation cible

- `main` : code de référence, **pas de déploiement automatique en production**.
- `feature/*` : changements préparés avec Codex.
- `staging` : branche future réservée au déploiement sur un sous-domaine de test, une fois l'hébergement configuré.
- Site de test : sous-domaine et répertoire distincts du site public ; nom exact à choisir dans l'hébergeur.
- Production : https://gntoma.com ; déploiement volontaire après validation.

## Prérequis avant toute automatisation de déploiement

1. Vérifier l'hébergeur : accès SSH/SFTP ou FTP, chemins réels, versions PHP et extensions.
2. Créer le sous-domaine de test avec une **base MySQL de test séparée** et des identifiants séparés.
3. Placer les secrets exclusivement sur le serveur ou dans les GitHub Actions Secrets (ne jamais les committer).
4. Corriger tout secret ou jeton de secours codé en dur ; renouveler les secrets qui auraient été exposés.
5. Préserver les répertoires `uploads/` et les fichiers `config.local.php`, `secrets.local.php`, `.env`. Ne jamais les écraser ni les effacer.
6. Ne pas rejouer les dumps SQL ou anciennes migrations de production durant un déploiement.
7. Ajouter et tester **après ces vérifications** une action de déploiement de `staging` vers le serveur de test seulement.
8. Contrôler les tests fonctionnels, paiements en mode test et connexion à la base de test avant toute activation en production.

## Vérifier le code PHP avant de déployer

Depuis un terminal disposant de PHP :

```bash
php -v
find . -type f -name '*.php' -not -path './vendor/*' -not -path './uploads/*' -print0 | xargs -0 -n1 php -l
```

Cette commande utilise `find` et `xargs` sur Linux (par exemple Codespaces). Sous Windows, exécuter la vérification depuis un environnement compatible.

## Limites

GitHub Pages n'exécute ni PHP ni MySQL. Pour tester GNTOMA en ligne, il faut un hébergement PHP avec une base MySQL et une configuration de test distincte.

**Statut : préparation du flux GitHub/Codex. Déploiement automatique non installé ni activé.**
