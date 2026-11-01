# FinPharma — Backend (Symfony / API Platform)

Plateforme de santé au Maroc : annuaire de pharmacies, médecins,
laboratoires et médicaments, avec API pour l'application mobile Ionic,
back-office admin, wallet, déchiffrement d'ordonnance et Ask IA.

## ⚠️ État du projet — à lire avant tout

**Ce code n'a jamais été exécuté.** Il a été écrit dans un environnement
sans PHP ni accès à Packagist (uniquement GitHub/npm/PyPI accessibles),
donc aucun `composer install`, aucune migration, aucun test réel n'a pu
être lancé. Chaque fichier a été vérifié manuellement (cohérence des
signatures, des imports, des noms de champs entre entités/repositories/
services/controllers) au fur et à mesure de l'écriture, mais **rien ne
remplace un vrai `bin/console lint:container` + une suite de tests** avant
mise en production. Voir chaque README de phase pour le détail des
vérifications faites et des incertitudes qui restent.

**Squelette de projet complet, pas juste du code à coller dans un projet
vide** : `composer.json`, `bin/console`, `public/index.php`,
`src/Kernel.php`, toute la config (`config/bundles.php`,
`config/services.yaml`, `config/routes.yaml`, `config/packages/*.yaml`),
`.env`, `.gitignore` — un `composer install` doit suffire à démarrer
(après config du `.env.local`, voir plus bas). **Note** : `symfony/flex`
a été volontairement retiré de `composer.json` — toute la configuration a
été écrite à la main plutôt que par des recipes Flex, garder Flex aurait
risqué des conflits au premier `composer install` (Flex essaierait
d'installer des recipes pour des fichiers de config qui existent déjà).
Idem, `composer.json` a été validé comme JSON syntaxiquement correct et
chaque `use Symfony\Component\X` du code a été recoupé avec les
dépendances déclarées (`symfony/string` et `symfony/http-client`
manquaient initialement — ajoutés).

## Démarrage rapide

```bash
composer install
cp .env .env.local   # puis éditer .env.local avec de vrais secrets (APP_SECRET, DATABASE_URL, JWT_PASSPHRASE...)
# Clés JWT (API mobile/web) :
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

bin/console doctrine:database:create
bin/console doctrine:migrations:migrate   # applique migrations/*.sql dans l'ordre chronologique du nom de fichier

bin/console lint:container
bin/console lint:twig templates/
bin/console lint:yaml translations/ config/

symfony server:start   # ou tout serveur PHP pointant vers public/
```

Créer un premier compte admin (voir `README_ADMIN.md`) :
```bash
bin/console doctrine:query:sql "UPDATE app_user SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'ton-email@exemple.com'"
```

## Ce que couvre le projet

- **Annuaire public** : pharmacies (liste/garde/ouvertes/fiche), médecins
  (par ville/spécialité), laboratoires, médicaments — web (Twig) + API
  (API Platform, consommée par l'app mobile).
- **SEO technique complet** : title/meta/canonical/hreflang FR-AR/OG/
  JSON-LD (Organization, Pharmacy, Physician, MedicalLaboratory, Product,
  BreadcrumbList), sitemap.xml par type de contenu, robots.txt.
- **Sécurité API** : JWT (Lexik) + refresh token rotatif, rôles, Voters
  (jamais d'autorisation basée sur un ID client), rate limiting.
- **Ajouter mon établissement** : formulaire + workflow de modération pour
  pharmacie/médecin/laboratoire (statuts distincts pour Pharmacy vs
  Doctor/Laboratory — voir Phase 4c/4g pour la justification).
- **Wallet** : recharge manuelle uniquement (PayPal/virement + reçu
  WhatsApp + validation admin), pas de gateway de paiement automatique.
- **Déchiffrement d'ordonnance** : marketplace pharmacien↔déchiffreur,
  attribution atomique (aucune double-acceptation possible), autocomplete
  médicament pour la saisie du résultat.
- **Ask IA** : abstraction `AiProviderInterface` remplaçable, Q&A OUI/NON
  sur un médicament, disclaimer non contournable imposé en post-traitement.
- **Upload de photo** : endpoint générique, stockage local abstrait derrière
  une interface remplaçable (S3 plus tard sans toucher les appelants).
- **Back-office admin** (`/admin`) : modération d'établissements, validation
  des recharges wallet — auth par session, séparée du JWT de l'API.

## Index des README de phase (ordre chronologique de construction)

| Fichier | Contenu |
|---|---|
| `README_PHASE4A.md` | Entités de base, migrations, imports pharmacies/médicaments |
| `README_PHASE4B.md` | API Platform, sécurité JWT, Wallet, déchiffrement d'ordonnance |
| `README_PHASE4C.md` | Doctor/Laboratory, workflow de modération |
| `README_PHASE4D.md` | SEO, premiers écrans web, recharge wallet manuelle |
| `README_PHASE4E.md` | Tous les écrans web restants, carte Leaflet/OSM |
| `README_PHASE4F.md` | Login/register web, formulaires câblés, pages d'erreur |
| `README_ADMIN.md` | Back-office admin (`/admin`) |

Chaque fichier documente les décisions prises, ce qui a été vérifié, et ce
qui reste ouvert à cette étape — à lire dans l'ordre pour comprendre
l'évolution du projet.

## Ce qui reste (connu, non fait)

- Blog (aucune donnée éditoriale, entité jamais modélisée).
- Compression/redimensionnement des photos uploadées.
- Notifications push (déchiffreur notifié d'une nouvelle demande).
- Recherche/filtres avancés dans le back-office admin.
- Tests automatisés (aucun test n'a pu être écrit/exécuté dans cet
  environnement).

## Structure

```
config/            Configuration Symfony (bundles, sécurité, Doctrine, API Platform...)
migrations/         Migrations SQL PostgreSQL, une par lot de travail
public/             Front controller, assets (CSS, JS, images, uploads)
src/
  ApiResource/       DTO API Platform (Pharmacy, Medicine, Doctor, Laboratory)
  Command/           Commandes console (import, recalcul disponibilité)
  Controller/
    v1/               Web public (Twig)
    Api/               API JSON (mobile + web)
    Admin/             Back-office admin
  Entity/             Entités Doctrine
  Repository/         Repositories Doctrine
  Security/           Auth JWT, Voters, rate limiting
  Service/            Logique métier (Seo, Wallet, Establishment, Ai, Storage, Import...)
  State/              Providers/Processors API Platform
templates/
  v1/, components/     Site public
  admin/                Back-office
  bundles/TwigBundle/   Pages d'erreur
translations/        FR/AR
```
