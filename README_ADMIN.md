# FinPharma — Back-office admin (`/admin`)

## Ce que c'est

Interface web Twig, dans le même projet Symfony que le site public, sous
`/admin`, protégée par `ROLE_ADMIN`. Couvre exactement ce qui existait déjà
en API JSON (`ModerationController`, `WalletController` — Phases 4b/4c/4g)
mais avec une vraie interface humaine par-dessus, pour le "petit
back-office admin" demandé pour la recharge wallet manuelle et la
modération d'établissements.

## Décision d'architecture importante : auth par session, pas JWT

Jusqu'ici, **tout était stateless** (`session.enabled: false` dans
`framework.yaml`, cohérent avec l'API JWT). Le back-office casse cette
hypothèse : c'est du personnel interne qui navigue dans un navigateur, pas
un client API — l'auth par session Symfony classique (`form_login`) est le
bon outil ici, pas le JWT.

J'ai donc **réactivé les sessions globalement** (`session.enabled: true`),
mais ça ne change rien au comportement de l'API : chaque firewall API a
`stateless: true` explicite (Phase 4b), qui désactive la session pour CE
firewall précis indépendamment du réglage global. Un nouveau firewall
`admin` (pattern `^/admin`) utilise `form_login` + `logout`, complètement
séparé du firewall `api`.

## Contenu

```
src/Controller/Admin/
  AdminAuthController.php        (/admin/login, /admin/logout)
  AdminDashboardController.php   (/admin — compteurs)
  AdminEstablishmentController.php (pharmacies + doctors/laboratories génériques)
  AdminWalletController.php      (/admin/wallet/recharges)

src/Service/Admin/AdminSidebarCountsService.php  (compteurs partagés pour les badges sidebar)

templates/admin/
  base.html.twig    (layout autonome, PAS un extends du site public — même
                      logique que error404/500.html.twig, Phase 4f)
  login.html.twig
  dashboard.html.twig
  pharmacies.html.twig, doctors.html.twig, laboratories.html.twig
  wallet_recharges.html.twig

public/css/admin.css  (séparé de app.css)

config/packages/framework.yaml modifié (session.enabled: true)
config/packages/security.yaml modifié (firewall admin + access_control)
```

## Aucune duplication de logique métier

- L'approbation/rejet d'établissement réutilise `EstablishmentModerationService`
  telle quelle (Phase 4c) — le controller admin ne fait qu'appeler la même
  méthode que `ModerationController` (API).
- La confirmation de recharge réutilise `WalletService::credit()` telle
  quelle (Phase 4b) — même verrou pessimiste, même garantie de non-négatif.
- Pharmacy (qui n'implémente pas `ModeratableEstablishment`, cf. Phase 4g)
  est traitée séparément dans `AdminEstablishmentController`, cohérent avec
  ce choix déjà pris côté API.

## Mise en route

```bash
# aucune nouvelle dépendance composer (session Symfony déjà incluse dans
# symfony/security-bundle et symfony/framework-bundle, déjà présents)

# créer un premier compte admin manuellement (aucun formulaire
# d'inscription admin n'existe volontairement — un admin ne doit pas
# pouvoir s'auto-créer) :
bin/console doctrine:query:sql "UPDATE app_user SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'ton-email@exemple.com'"
```

## Ce qui manque encore

- **Pas de formulaire "créer un admin"** (volontaire, cf. ci-dessus) — la
  première promotion en `ROLE_ADMIN` se fait en base directement.
- **Pas de vue "détail" par établissement** — juste la liste + actions
  inline. Si le volume grandit, une page détail par établissement
  (photos, historique) deviendra utile.
- **Pas de recherche/filtre** dans les tables — pas nécessaire tant que le
  volume de demandes en attente reste faible.
