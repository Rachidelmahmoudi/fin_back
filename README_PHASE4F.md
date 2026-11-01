# FinPharma — Phase 4f : Connexion/inscription, formulaires câblés, pages d'erreur

## Contenu de ce lot

```
public/js/api-client.js   <- client JS vanilla (login/register/logout/refresh, fetch authentifié)

src/Controller/v1/AuthPageController.php   (/connexion, /inscription)
templates/v1/auth/{login,register}.html.twig

templates/v1/establishment/create.html.twig   <- soumission réellement câblée (fetch -> API)
templates/components/header.html.twig         <- état connecté/déconnecté dynamique

templates/bundles/TwigBundle/Exception/{error404,error500}.html.twig
```

## Ce qui est réellement câblé maintenant

- **Connexion/inscription** : `login.html.twig`/`register.html.twig` appellent `FinPharmaApi.login()`/
  `.register()`, qui parlent aux endpoints existants (`POST /api/v1/auth/login` géré par Lexik,
  `POST /api/v1/auth/register`, Phase 4b) et stockent les tokens.
- **Formulaire médecin/laboratoire** : soumet réellement vers `POST /api/v1/establishments/doctors`
  ou `/laboratories` (Phase 4c), avec le JWT en en-tête. Redirige vers `/connexion` si l'utilisateur
  n'est pas connecté (le formulaire nécessite `ROLE_USER`, cf. sécurité API).
- **Header dynamique** : affiche "Connexion" ou "Déconnexion" selon la présence d'un token, sans
  rechargement serveur (vérifié côté client au chargement de chaque page).

## Ce qui reste explicitement NON câblé — et pourquoi

1. **Formulaire pharmacie** : j'ai vérifié en écrivant ce lot — **il n'existe aucun endpoint
   `POST /api/v1/pharmacies`** dans tout ce qu'on a construit (Phase 4b n'a créé qu'un `PATCH`
   pour éditer une pharmacie existante, jamais de création). Plutôt que d'inventer un faux
   comportement, le formulaire pharmacie affiche un message explicite ("en cours de finalisation")
   et n'envoie rien. Il faut soit ajouter cet endpoint (POST + modération, sur le modèle
   d'`EstablishmentController` API), soit clarifier si l'ajout de pharmacie doit suivre exactement
   le même workflow DRAFT→PENDING_REVIEW que Doctor/Laboratory (probable, mais pas tranché).
2. **Upload de photo** : les 3 formulaires ont un champ fichier, mais aucun endpoint d'upload
   n'existe (mentionné comme manquant depuis la Phase 4c). Le JS ignore ce champ plutôt que de
   faire semblant de l'envoyer.
3. **Stockage des tokens en `localStorage`** : choix pragmatique documenté dans
   `api-client.js` — vulnérable en théorie à un XSS qui volerait le refresh token. Pour un compte
   pharmacien/médecin qui gère de l'argent (wallet), un cookie `httpOnly` serait plus sûr à terme.
   Je ne l'ai pas fait maintenant car ça demande un vrai flux CSRF + configuration cookie côté
   Symfony que je n'ai pas voulu improviser sans validation.
4. **CSRF** : les formulaires HTML n'ont pas de token CSRF Symfony — comme la soumission passe
   par `fetch()` en JSON vers l'API (protégée par JWT, pas par cookie de session), le CSRF
   classique ne s'applique pas de la même façon ici. Si un jour ces mêmes formulaires passent par
   une session web classique plutôt que l'API JWT, il faudra ajouter la protection CSRF standard
   Symfony (`{{ csrf_token() }}`).

## Pages d'erreur — rendues volontairement autonomes

`error404.html.twig`/`error500.html.twig` **n'étendent pas `base.html.twig`**. Si l'erreur vient
d'un service cassé (base de données down, par exemple), hériter du layout complet — qui appelle
plusieurs routes/services dans le header (villes actives, état de connexion...) — risquerait de
provoquer une seconde erreur qui masquerait la première. CSS minimal inline, contenu en dur (pas
de `trans()` non plus, pour la même raison de robustesse).

## Mise en route sur ton vrai projet

```bash
# aucune nouvelle dépendance composer

# copier public/js/, src/Controller/v1/AuthPageController.php, templates/ (fusionner)
bin/console lint:twig templates/
```

## Prochaine étape proposée

Dans l'ordre le plus logique compte tenu des trous identifiés :
1. **Ajouter `POST /api/v1/pharmacies`** (création + modération, même patron que Doctor/Laboratory)
   pour débloquer le dernier formulaire.
2. **Endpoint d'upload de photo** (S3/local + génération d'URL), partagé par les 3 formulaires
   d'établissement et par la fiche pharmacie.
3. **Application mobile Ionic** — toujours pas commencée, a un vrai Figma contrairement au web.

Dis-moi ce que tu veux prioriser.
