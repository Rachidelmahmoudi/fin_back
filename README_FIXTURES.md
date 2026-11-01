# Données de test — FinPharma

## ⚠️ Jamais en production
Ce fichier crée un jeu de données FICTIF (mot de passe unique en clair,
montants de test) et **purge toute la base existante** avant de charger.
À utiliser uniquement sur ta base de développement locale.

## Installation

1. Place `src/DataFixtures/AppFixtures.php` dans ton projet (même chemin).
2. Vérifie que `doctrine/doctrine-fixtures-bundle` est bien installé (déjà
   dans `composer.json` en `require-dev` du zip que je t'ai donné) :
   ```bash
   composer install
   ```
3. Charge les fixtures :
   ```bash
   bin/console doctrine:fixtures:load
   ```
   Il va demander confirmation (`Careful, database will be purged`) —
   c'est normal, tape `yes`.

Un résumé des comptes créés s'affiche à la fin du chargement.

## Comptes créés

Mot de passe **identique pour tous** : `Test1234!`

| Email | Rôle | Pour tester |
|---|---|---|
| `admin@finpharma.test` | ROLE_ADMIN | Le back-office `/admin` |
| `patient@finpharma.test` | ROLE_USER | Un utilisateur "normal", sans fonctionnalité pro |
| `pharmacien@finpharma.test` | ROLE_PHARMACIST | Sa pharmacie est déjà **active et de garde** (visible tout de suite dans l'app), avec 20 DH de solde wallet |
| `pharmacien.attente@finpharma.test` | ROLE_USER | Sa pharmacie est **en attente** — sers-t'en pour tester l'approbation dans `/admin/pharmacies` |
| `medecin@finpharma.test` | ROLE_DOCTOR | Fiche déjà **publiée et vérifiée** |
| `medecin.attente@finpharma.test` | ROLE_USER | Fiche **en attente** — teste l'approbation dans `/admin/{type}` |
| `laboratoire@finpharma.test` | ROLE_LAB_OWNER | Labo publié, avec 2 analyses |
| `dechiffreur@finpharma.test` | ROLE_DECODER | Une demande de déchiffrement est déjà **publiée** et prête à être acceptée (Assistant IA -> Demandes disponibles) |

## Ce qui est prêt à tester

- **Pharmacies de garde** : une pharmacie à Marrakech est marquée de garde dès le chargement (pas besoin d'attendre le cron `app:pharmacy:recalculate-availability`).
- **Déchiffrement** : connecte-toi en `dechiffreur@finpharma.test`, tu verras une demande déjà disponible à accepter.
- **Recharge wallet** : une demande de virement de 50 DH est en attente pour `pharmacien@finpharma.test` — connecte-toi en `admin` pour la confirmer/rejeter dans `/admin/wallet/recharges`.
- **Modération admin** : une pharmacie et un médecin sont en attente, prêts à être approuvés ou rejetés.
- **Médicaments** : Doliprane 1000mg et Advil 400mg, avec DCI/dosage/indications renseignés.

## Ce qui n'est PAS couvert

- Pas de laboratoire pharmaceutique / Espace Laboratoire (cette fonctionnalité n'est pas dans le zip backend final — voir l'historique de la conversation).
- La photo d'ordonnance de test est une image générée à la volée (`placehold.co`) — nécessite une connexion internet pour s'afficher, ce n'est pas un vrai fichier uploadé.
- Les horaires d'ouverture ne couvrent que le lundi (`dayOfWeek = 1`) à titre d'exemple — ajoute les autres jours toi-même si besoin pour un test plus complet.

## Recharger après modification

Si tu modifies le fichier de fixtures, relance simplement :
```bash
bin/console doctrine:fixtures:load
```
Ça repurge et recharge tout — aucune donnée que tu aurais créée manuellement dans l'app ne survivra à un rechargement.
