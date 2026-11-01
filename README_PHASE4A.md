# FinPharma — Phase 4a : Entités, migration, imports

## Contenu de ce lot

```
src/Entity/
  City.php, Pharmacy.php, PharmacyOpeningHour.php, PharmacyDutyPeriod.php
  Dci.php, Medicine.php, MedicineSourceRecord.php (+ enums MedicineSource, MedicineTableau, PrincepsOrGeneric)
  EmergencyNumber.php
  User.php (version minimale — complétée en Phase 4b avec JWT/rôles/wallet)
src/Repository/
  CityRepository.php, PharmacyRepository.php, MedicineRepository.php
src/Service/Import/
  SlugHelper.php, GeoUrlParser.php
  PharmacyImportService.php   <- dédup nom+géoloc, validé sur les 18 055 lignes réelles
  MedicineImportService.php   <- fusion 2 sources, validé sur 588 + 7024 lignes réelles
src/Command/
  ImportPharmaciesCommand.php               (app:import:pharmacies)
  ImportMedicinesFromMedicamentMaCommand.php (app:import:medicines:medicament-ma)
  ImportMedicinesFromCnssGuideCommand.php    (app:import:medicines:cnss-guide)
migrations/
  Version20260908_Phase4a.sql   <- schéma PostgreSQL + pré-liste villes + numéros utiles
```

## Pourquoi ce code n'a pas été exécuté ici

Cet environnement n'a pas accès à Packagist (seuls npm/pip/cargo/GitHub sont
autorisés en sortie réseau), donc `composer install` et l'exécution réelle
d'un projet Symfony n'y sont pas possibles. Ce code est donc livré prêt à
copier dans ton projet Symfony réel, **et sa logique métier (dédup pharmacies,
matching médicaments) a été validée en la reproduisant en Python directement
sur tes fichiers réels** — voir les résultats ci-dessous.

## Validation effectuée sur les données réelles

**Dédup pharmacies** (sur `pharmacies.json`, 18 055 lignes) : 5 174 groupes de
lignes partageant ville + nom normalisé identique, soit 6 128 lignes qui
seraient fusionnées plutôt que dupliquées (ex: "Pharmacie Houch" à Agadir
apparaît 2 fois sous 2 références différentes : `REF235779` et `REF54505`).
Le service PHP ajoute en plus un contrôle de distance géographique
(30m) pour éviter de fusionner deux pharmacies homonymes dans des quartiers
différents de la même ville.

**Matching médicaments** (588 lignes `medications_A.json` vs 7 024 lignes
CNSS) : la stratégie initialement documentée (similarité sur le nom complet)
s'est révélée inefficace — **0% de correspondance fiable** — parce que le nom
`medicament.ma` combine marque+dosage+forme ("ABIRATERONE GT 250 MG,
Comprimé") alors que le nom CNSS n'est que la marque commerciale ("GLUCOR").
Corrigé en extrayant la marque (tokens avant le premier chiffre) et en
comparant marque+dosage exact : **213 correspondances fiables (36%)**, 65
lignes avec marque trouvée mais dosage différent (`needsReview=true`), 310
sans correspondance CNSS — cohérent, puisque tous les médicaments ne sont pas
remboursables (ex. ABSTRAL/fentanyl, Tableau B, non trouvé dans le guide CNSS,
ce qui est normal et pas une erreur de matching).
`src/Service/Import/MedicineImportService.php` reflète cette version corrigée.

## Mise en route sur ton vrai projet

```bash
composer require symfony/orm-pack api-platform/core phpoffice/phpspreadsheet doctrine/migrations-bundle

# copier src/ et migrations/ dans ton projet, puis :
bin/console doctrine:database:create
bin/console doctrine:migrations:diff   # génère la vraie migration Doctrine à partir des entités
# comparer avec migrations/Version20260908_Phase4a.sql fourni ici, puis :
bin/console doctrine:migrations:migrate

bin/console app:import:pharmacies var/import/pharmacies.json
bin/console app:import:medicines:medicament-ma var/import/medications_A.json
# répéter pour medications_B.json ... medications_Z.json quand disponibles
bin/console app:import:medicines:cnss-guide var/import/Medication_liste1.xlsx
```

## Ce qui reste pour Phase 4a (à traiter ensuite si tu valides cette base)

- `PharmacyAvailabilityService` (calcul isOpenNow/isOnDutyNow, cron 15 min) — pas encore écrit, dépend de vraies données d'horaires qu'on n'a pas encore.
- Un `MedicineReconcileCommand` séparé n'est pas nécessaire : le rapprochement se fait déjà au fil de l'import (`findMatch` appelé dans les deux services), contrairement à ce qui était suggéré dans l'addendum précédent (passe de reconciliation séparée) — plus simple et plus prévisible ainsi.
- Doctor/Laboratory/Establishment (workflow "Ajouter mon établissement") → prévu pour Phase 4b avec l'authentification.

## Prochaine étape proposée

**Phase 4b — API Platform + sécurité** (JWT, refresh token, rôles, ApiResource pour Pharmacy/Medicine, endpoint autocomplete médicament, Wallet/PrescriptionRequest avec le flux de débit à la validation confirmé) — dis-moi si tu veux que j'enchaîne directement dessus.
