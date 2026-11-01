# FinPharma — Phase 4b : API Platform, sécurité, Wallet, Déchiffrement d'ordonnance

## Contenu de ce lot

```
src/Entity/
  RefreshToken.php, Wallet.php, WalletTransaction.php (+ enum WalletTransactionType)
  PrescriptionRequest.php (+ enum PrescriptionRequestStatus), PrescriptionResult.php
  User.php complété (password, rôles, decoderPoints)

src/Repository/
  RefreshTokenRepository.php, WalletRepository.php, WalletTransactionRepository.php
  PrescriptionRequestRepository.php, PrescriptionResultRepository.php

src/Service/Wallet/WalletService.php
  Seul point d'entrée autorisé à modifier un solde (verrou pessimiste DB).

src/Service/Prescription/
  PrescriptionMatchingService.php   <- attribution atomique (UPDATE conditionnel)
  PrescriptionAlreadyAcceptedException.php

src/Service/Search/PharmacySearchService.php + PharmacySearchResult.php
src/Service/Availability/PharmacyAvailabilityService.php

src/ApiResource/
  PharmacyResource.php, MedicineResource.php   (DTO-first, API Platform)

src/State/
  Pharmacy{Collection,Item}Provider.php, PharmacyPatchProcessor.php, PharmacyResourceMapper.php
  Medicine{Collection,Item}Provider.php, MedicineResourceMapper.php

src/Security/
  AuthService.php, LoginSuccessHandler.php, RateLimiterSubscriber.php
  Voter/PharmacyVoter.php, Voter/PrescriptionRequestVoter.php

src/Controller/Api/
  AuthController.php, WalletController.php, PrescriptionController.php
  MedicineAutocompleteController.php   <- endpoint demandé pour le déchiffreur

src/Command/RecalculatePharmacyAvailabilityCommand.php

config/packages/
  security.yaml, lexik_jwt_authentication.yaml, rate_limiter.yaml

migrations/Version20260908_Phase4b.sql
```

## Pourquoi ce code n'a toujours pas été exécuté ici

Mêmes contraintes qu'en Phase 4a (pas de PHP, pas d'accès Packagist dans ce
sandbox). J'ai en revanche **vérifié manuellement chaque appel entre
fichiers** contre les entités réelles de la Phase 4a (getters, noms de
champs, types de retour) au fur et à mesure de l'écriture — deux
incohérences ont été trouvées et corrigées pendant cette passe :
- `PrescriptionRequest` n'avait pas de `getImageUrl()` — ajouté.
- Le calcul d'horaires dans `PharmacyAvailabilityService` comparait des
  `DateTimeInterface` avec des strings — corrigé pour formater d'abord, et
  la logique de garde a été simplifiée pour réutiliser
  `PharmacyDutyPeriod::isActiveAt()` qui existait déjà plutôt que de la
  dupliquer.

Cela dit, un scaffold de cette taille (35+ fichiers, wiring transverse
Security/State/Service) **doit être compilé et testé dans un vrai
environnement Symfony avant toute mise en production** — la vérification
manuelle réduit le risque d'erreur bête mais ne remplace pas
`bin/console lint:container` + une suite de tests fonctionnels.

## Points d'architecture à connaître avant de l'intégrer

1. **Débit wallet.** Le flux implémente exactement la décision confirmée :
   publication = vérif solde seulement, acceptation = `HOLD`, validation =
   `RELEASE` + `DEBIT` du montant réel. Aucun `REFUND` n'est nécessaire sur
   expiration/annulation car aucun débit n'a jamais eu lieu avant validation
   — seul un `RELEASE` du hold suffit.

2. **Concurrence d'acceptation.** `PrescriptionMatchingService::accept()`
   n'utilise PAS `SELECT ... FOR UPDATE` mais un `UPDATE ... WHERE
   status = 'PUBLISHED'` exécuté en DBAL brut. C'est délibéré : il n'y a pas
   de fenêtre lecture-puis-écriture à protéger, donc pas besoin d'un verrou
   explicite — l'atomicité au niveau ligne du SGBD suffit et c'est plus
   simple à raisonner.

3. **`WalletService` est le seul endroit qui touche `Wallet::balance`.**
   Les setters sont préfixés `_internal` et documentés comme tels ;
   n'importe quel autre appelant doit passer par `credit()`/`hold()`/
   `release()`/`debitFromResult()`. La contrainte SQL
   `chk_wallet_balance_non_negative` est une deuxième ligne de défense.

4. **`MedicineAutocompleteController`** fait un `LIKE 'préfixe%'` simple
   pour l'instant (suffisant à l'échelle actuelle du catalogue). Le
   commentaire dans le fichier documente le point d'évolution vers
   Meilisearch/Typesense/pg_trgm si le volume ou la tolérance aux fautes de
   frappe l'exigent plus tard — pas fait maintenant pour ne pas ajouter une
   dépendance non demandée.

5. **`security.yaml` référence des services non encore écrits dans ce
   lot** : `App\Security\LoginSuccessHandler` (fourni ✓), mais aussi
   `lexik_jwt_authentication.handler.authentication_failure` (fourni par le
   bundle lui-même, rien à écrire). Vérifier au moment de l'intégration que
   ton `security.yaml` existant (s'il y en a déjà un dans ton vrai projet)
   ne définit pas déjà un firewall conflictuel sur `^/api`.

## Ce qui n'est PAS dans ce lot (volontairement, ou reporté)

- **Paiement réel du wallet.** `WalletController::credit()` est protégé
  `ROLE_ADMIN` (rechargement manuel back-office) — conforme à la consigne
  §27 de ne pas implémenter un vrai provider de paiement sans validation
  préalable. Le point d'intégration est clairement marqué dans le fichier.
- **Establishment/Doctor/Laboratory** (workflow "Ajouter mon
  établissement", §Addendum C) — toujours prévu pour une Phase 4c dédiée,
  après validation de ce lot.
- **`PharmacyService` (M2M services genre "livraison", "garde de nuit")**
  — mentionné dans l'Addendum C mais pas encore modélisé ; le mapper
  `PharmacyResourceMapper` retourne un tableau vide en attendant, avec un
  TODO explicite dans le code.
- **Ask IA, SEO/Sitemap, Twig, mobile Ionic** — pas commencés, hors
  périmètre de ce qui a été demandé jusqu'ici (priorités 2-3 du cahier des
  charges : pharmacies + médicaments, puis leur API).

## Mise en route sur ton vrai projet

```bash
composer require lexik/jwt-authentication-bundle symfony/rate-limiter symfony/uid

# Génération des clés JWT (RS256)
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
# Ajouter JWT_PASSPHRASE, JWT_SECRET_KEY, JWT_PUBLIC_KEY dans .env.local

# copier src/, config/, migrations/ dans ton projet (fusionner avec Phase 4a), puis :
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
bin/console lint:container   # vérifie le wiring des nouveaux services

bin/console app:pharmacy:recalculate-availability   # à planifier ensuite toutes les 15 min
```

## Prochaine étape proposée

**Phase 4c — Establishment / Doctor / Laboratory** (workflow "Ajouter mon
établissement" complet : formulaire multi-étapes, modération, attribution
de rôle à la publication — §Addendum C), qui vient combler la dernière
pièce du socle backend avant d'attaquer le SEO/sitemap ou les premiers
écrans Twig/Ionic sur le design system déjà extrait. Dis-moi si tu veux
que j'enchaîne dessus, ou si tu préfères qu'on bascule sur autre chose en
premier (SEO, ou les premiers écrans mobiles).
