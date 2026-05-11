# GNTOMA - Conventions metier, conventions visuelles et logique profonde

## Introduction

GNTOMA ne se limite pas a un simple site web. Le projet cherche a creer une sensation:
un espace vivant, elegant, artistique, et respirant.

Les piliers centraux:

- beaute visuelle
- fluidite
- simplicite technique
- identite humaine
- profondeur editoriale
- organisation claire
- experience emotionnelle

Le projet melange volontairement:

- reseau social
- journal personnel
- plateforme editoriale
- identite numerique
- publication premium
- geolocalisation intelligente
- vente de contenus
- demandes de prestations intellectuelles

## 1) Convention philosophique principale

### Le contenu doit paraitre precieux

Chaque contenu doit sembler important, propre, elegant, serieux et immersif.

### Beaute avant surcharge

Interface minimaliste, respirante, lumineuse, fluide et emotionnelle.
Pas de chaos visuel, pas d'effets inutiles.

### Sensation d'espace

- espacements genereux
- padding confortable
- coins arrondis
- ombres douces
- animations lentes
- transitions fluides

## 2) Convention visuelle globale

### Identite premium moderne

L'utilisateur doit ressentir qualite, stabilite, intelligence et modernite.

### Couleurs

Palette privilegiee:

- noir profond
- gris doux
- blanc casse
- bleu moderne
- violet leger
- degrades subtils

Eviter les couleurs agressives.

### Degrades

Degrades doux et elegants (jamais criards), par exemple:

```css
background: linear-gradient(135deg, #0f172a, #1e293b);
```

### Transparence / glassmorphism

Utiliser avec moderation (`backdrop-filter`, transparence legere, effet verre).

## 3) Convention des animations

Les animations doivent etre douces, naturelles et presque cinematographiques.

Recommandations:

- fade-in progressif
- slide leger
- hover elegant (legere elevation + ombre)
- fonds animes tres subtils

Interdit:

- animations brutales
- mouvements rapides distrayants

## 4) Convention des fonds

Les arriere-plans participent a l'emotion et a l'atmosphere.

Motifs recommandes:

- grilles futuristes tres legeres
- halos lumineux bleus/violets/cyan
- bruit subtil (noise)
- degrades mouvants lents
- particules/etoiles avec parcimonie

Eviter:

- motifs repetitifs visibles
- fonds agressifs
- surcharge visuelle

## 5) Convention mobile-first

Smartphone prioritaire:

- boutons larges
- navigation tactile
- textes lisibles
- animations optimisees

Performance mobile obligatoire:

- rendu rapide
- animations GPU-friendly
- images compressees

## 6) Convention structurelle

Architecture volontairement plate:

- acces immediat aux fichiers
- maintenance rapide
- simplicite de deploiement
- complexite mentale reduite

Exceptions de dossiers seulement si utile (`images`, `uploads`, `journal`, `assets`, etc.).

## 7) Convention des identifiants

Utilisateurs:

- `A1`, `A2`, `A3`, ...

Journaux:

- `A1J1`, `A1J2`, `A2J1`, ...

Pages:

- `A1J1P1` ou slug propre.

## 8) Convention metier des journaux

Un journal est un univers editorial (pas un simple post), pouvant contenir:

- reflexions
- cours
- recits
- documents premium
- archives
- contenus prives

Types d'acces (alignes sur `journals.status` en base):

- **`public`**: contenu lisible par **tous**; **aucune** validation par l'auteur.
- **`private`**: visible **uniquement par l'auteur** du journal (brouillon / espace personnel).
- **`paid`**: journal **expose comme une offre** (decouvrable), mais chaque lecteur doit **contacter l'auteur** et passer par une **demande d'acces** (echange + validation) avant d'acceder au contenu protege.

## 9) Convention relationnelle

Controle humain central:

- l'auteur accepte/refuse
- l'auteur discute
- l'auteur retire un acces

Le chat sert a la relation et a la validation sociale.

Suivi d'auteur (`follow_requests` / `author_follows`) et acces journal (`access_requests`) **coexistent**: suivre un auteur influence notamment la **recherche par mots-cles** (mise en avant des auteurs suivis) et la **valorisation** des auteurs tres suivis. Le **nombre de suiveurs** reflete uniquement les suivis **encore dans leur periode de validite** (les suivis expires ne comptent plus).

## 10) Convention geographique

GeoNames + HTMX autocomplete pour:

- uniformiser les villes
- eviter les doublons
- rechercher precisement

Stockage geographique:

- ville
- pays
- latitude
- longitude

## 11) Convention economique

Modele freemium:

- essai gratuit 48h
- abonnement (ex: 2 USD / 30 jours, 3 USD / 60 jours)

Paiement (ex: Flexpay):

- redirection securisee
- webhook/validation
- verification automatique

**Paiements traites en ligne sur GNTOMA** (flux existant): prolongation d'abonnement / temps d'acces plateforme, achat de packs de **credits messages**.

**Journaux payants**: le **prix affiche** est en **USD**. Le reglement entre auteur et lecteur pour ouvrir le contenu se fait en pratique **par accord direct** (messagerie, Mobile Money, etc.), distinct du paiement FlexPay des credits / abonnement.

### Credits (consommation cote utilisateur)

Les **credits messages** servent a:

- **1 credit** par **message** envoye en conversation (`message_send_process.php`);
- **50 credits** par **campagne d'envoi de masse** (un tarif fixe par campagne, independamment du nombre de destinataires filtres), via `message_bulk_process.php`.

Les **credits demande d'acces** (`users.access_request_credits`) servent a:

- **l'envoi d'une demande d'acces** vers un journal **payant** (`paid`), decremente lors de la creation d'une ligne dans `access_requests` (`journal_access_request.php`).

### Duree de vie des messages

- Les messages ont une date d'expiration **21 jours** apres envoi (`expires_at`). La suppression effective apres cette date doit etre assuree par une tache automatisee (cron) cote serveur si souhaitee.

## 12) Convention d'acces aux journaux verrouilles (feature centrale)

Le systeme de demande d'acces est un axe central de GNTOMA.

Quand un lecteur ouvre un journal verrouille, l'experience doit rester premium:

- apercu floute elegant
- couverture mise en valeur
- halo lumineux subtil
- effet verre
- animations lentes
- sensation de contenu precieux

La demande d'acces doit permettre:

- message personnalise du lecteur
- proposition financiere optionnelle
- discussion avec le proprietaire
- attente de validation/refus

Le proprietaire peut:

- accepter
- refuser
- demander plus d'informations
- fixer un prix
- accorder un acces temporaire ou permanent

Apres paiement valide:

- animation lumineuse douce
- de-verrouillage progressif
- disparition du flou
- ouverture cinematographique

## 13) Convention prestations documentaires

GNTOMA integre un canal de demandes documentaires/professionnelles.

Demandes cibles (exemples):

- contrats
- rapports
- CV
- memoires
- cours
- analyses
- documents administratifs

Le demandeur peut preciser:

- budget
- delai
- niveau de qualite
- style visuel
- categorie professionnelle

Le professionnel peut poser des questions avant production.

La livraison doit etre percue comme premium (cartes elegantes, effet verre, profondeur, micro-animations).

## 14) Convention securite

Securite obligatoire:

- PDO prepare
- validation stricte
- CSRF
- nettoyage des entrees
- upload securise
- sessions protegees

## 15) Convention frontend

Stack front privilegiee:

- HTMX pour interactions partielles fluides
- Alpine.js pour etats legers (menus, modales, micro-interactions)
- Bootstrap 5 si utile pour responsive et vitesse d'integration

## 16) Convention performance

Fluidite obligatoire meme avec des effets visuels.

- images WebP recommandees
- compression intelligente
- lazy loading media

## 17) Convention communication et clarification

Regle de collaboration:

- poser des questions quand une information manque
- ne pas supposer un detail fonctionnel critique
- clarifier les choix de style s'il existe plusieurs options fortes
- confirmer les contraintes techniques impactantes (paiement, legal, delais, data)

Objectif: garantir une execution creativement juste et techniquement fiable.

## 18) Convention emotionnelle

GNTOMA doit degager:

- calme
- profondeur
- elegance
- intelligence
- immersion

L'interface doit respirer avec une hierarchie claire et des transitions propres.

## 19) Convention finale

Le coeur de GNTOMA repose sur:

1. Beaute visuelle
2. Fluidite emotionnelle
3. Simplicite technique
4. Publication organisee
5. Identite numerique durable
6. Controle humain des acces
7. Geolocalisation intelligente
8. Experience premium mobile-first

GNTOMA ne cherche pas seulement a fonctionner. Le projet doit etre ressenti.
