# FinPharma — Phase 4c : Establishment (Doctor / Laboratory) — "Ajouter mon établissement"

## Contenu de ce lot

```
src/Entity/
  EstablishmentStatus.php   <- enum + interface ModeratableEstablishment (partagée Doctor/Laboratory)
  Specialty.php
  Doctor.php, DoctorOpeningHour.php
  Laboratory.php, LabTest.php, LaboratoryOpeningHour.php

src/Repository/
  DoctorRepository.php, DoctorOpeningHourRepository.php
  LaboratoryRepository.php, LaboratoryOpeningHourRepository.php
  LabTestRepository.php, SpecialtyRepository.php

src/Service/Establishment/EstablishmentModerationService.php
  <- workflow commun DRAFT -> PENDING_REVIEW -> PUBLISHED/REJECTED/SUSPENDED

src/ApiResource/DoctorResource.php, LaboratoryResource.php   (lecture publique, DTO-first)
src/State/Doctor{Collection,Item}Provider.php, DoctorResourceMapper.php
src/State/Laboratory{Collection,Item}Provider.php, LaboratoryResourceMapper.php

src/Controller/Api/
  EstablishmentController.php   <- POST création (soumission déchiffreur -> modération)
  ModerationController.php      <- back-office admin (pending/approve/reject)

config/packages/security.yaml   (mis à jour : routes /establishments, /admin)
migrations/Version20260908_Phase4c.sql
```

## Décision d'architecture centrale de ce lot

**Pas d'héritage Doctrine entre Pharmacy/Doctor/Laboratory.** L'Addendum C
suggérait une structure `Establishment` commune (mapped superclass ou
single-table inheritance). Je m'en suis délibérément écarté : `Pharmacy`
existe déjà comme entité autonome depuis la Phase 4a, avec 18k+ lignes
prévues à l'import. Introduire une hiérarchie d'héritage Doctrine après
coup sur une table déjà en production serait une migration risquée (Single
Table Inheritance ajoute une colonne discriminante et complique tous les
index existants ; Joined Table Inheritance change la clé primaire logique)
pour un bénéfice de code assez faible.

**La cohérence de workflow vient de la composition, pas de l'héritage** :
`Doctor` et `Laboratory` implémentent l'interface `ModeratableEstablishment`
(getId/getStatus/setStatus/getOwner/markVerified/isVerified), et
`EstablishmentModerationService` s'appuie uniquement sur cette interface —
un seul service gère la modération des deux types, sans dupliquer la
logique, sans toucher à `Pharmacy`. C'est le même résultat pratique que
visait l'Addendum C (formulaire + modération communs), obtenu autrement.

`Pharmacy` garde son propre `PharmacyStatus` (PENDING/ACTIVE/SUSPENDED,
Phase 4a) plutôt que d'être migrée vers `EstablishmentStatus`
(DRAFT/PENDING_REVIEW/PUBLISHED/REJECTED/SUSPENDED, Phase 4c) — les deux
enums ne sont pas interchangeables et ce n'est pas nécessaire pour
atteindre l'objectif fonctionnel demandé.

## Ce qui a été vérifié pendant l'écriture

- `EstablishmentModerationService::reject()` appelle
  `setRejectionReason()` via `method_exists()` plutôt qu'en le mettant
  dans l'interface `ModeratableEstablishment` — car ce n'est pas commun à
  Pharmacy si elle implémentait un jour l'interface, et ça évite de forcer
  Pharmacy à avoir cette méthode si elle n'implémente pas l'interface.
- `ModerationController::pending()` fait de même pour `getName()` — présent
  sur Doctor et Laboratory mais pas dans l'interface (choix identique).
- Le rôle attribué à l'approbation (`ROLE_DOCTOR`/`ROLE_LAB_OWNER`) est
  bien celui déjà déclaré dans `role_hierarchy` de `security.yaml`
  (Phase 4b) — pas de nouveau rôle inventé sans mise à jour de la hiérarchie.

## Ce qui manque encore pour un "Ajouter mon établissement" complet

- **`DoctorPatchProcessor`/`LaboratoryPatchProcessor`** (édition après
  publication, republication directe confirmée §Addendum 2) — même
  pattern que `PharmacyPatchProcessor` (Phase 4b), pas encore dupliqué ici
  pour éviter de gonfler ce lot ; à faire en Phase 4c-bis si tu valides
  cette base d'abord.
- **Upload de photos réel** — `photoUrls` est un simple `json` d'URLs déjà
  hébergées ; le endpoint d'upload (stockage S3/local + génération d'URL)
  n'est pas construit, hors périmètre "modèle de données + workflow".
- **Notification à l'owner en cas de rejet** — `rejectionReason` est stocké
  et lisible via l'API, mais l'envoi d'email/push n'est pas câblé.
- **Web (`EstablishmentController` Twig, `/ajouter-mon-etablissement`)** —
  ce lot ne couvre que l'API ; le formulaire multi-étapes web/mobile
  lui-même n'est pas construit (dépend du design system déjà extrait du
  Figma, à faire quand on passera aux écrans).

## Mise en route sur ton vrai projet

```bash
# aucune nouvelle dépendance composer par rapport à la Phase 4b

# copier src/, config/, migrations/ (fusionner avec 4a+4b), puis :
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
bin/console lint:container
```

## Prochaine étape proposée

Avec Pharmacies, Médicaments, Médecins et Laboratoires tous modélisés et
exposés en API, le socle backend "annuaire de santé" (§1-8 du cahier des
charges) est complet. Les chantiers naturels suivants :

1. **SEO/Sitemap** (§9-13, priorité 6 du cahier des charges) —
   `SeoService`, JSON-LD, sitemap.xml par type de contenu.
2. **Premiers écrans** (Twig web ou Ionic mobile) sur le design system déjà
   extrait du Figma — pour avoir quelque chose de visible/démontrable.
3. **Wallet réel** — brancher un vrai provider de paiement marocain sur le
   point d'intégration déjà marqué dans `WalletController::credit()`.

Dis-moi laquelle de ces pistes tu veux prioriser.
