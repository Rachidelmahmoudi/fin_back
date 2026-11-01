# FinPharma — Phase 4d : SEO technique + premiers écrans web

## Contenu de ce lot

```
src/Service/Seo/
  SeoMeta.php               <- DTO unique consommé par templates/components/seo/head.html.twig
  SeoService.php            <- forHome(), forPharmacyList(), forPharmacyShow()
  StructuredDataService.php <- JSON-LD : Organization, Pharmacy, Physician, MedicalLaboratory, Product, BreadcrumbList, FAQPage

src/Controller/v1/
  HomeController.php, PharmacyController.php (list/duty/open/show)
  SitemapController.php (index + sitemap_pharmacies/medicines/doctors/laboratories/blog)
  RobotsController.php

templates/
  base.html.twig, components/{header,footer,breadcrumbs,card_pharmacy,seo/head}.html.twig
  v1/home/index.html.twig
  v1/pharmacy/{list,show}.html.twig

translations/messages.{fr,ar}.yaml

public/css/app.css   <- design desktop réel (voir note ci-dessous)

+ Wallet (petit ajout demandé) :
src/Entity/WalletRechargeRequest.php (+ enums WalletRechargeMethod/Status)
src/Repository/WalletRechargeRequestRepository.php
WalletController.php complété : declareRecharge / pendingRecharges / confirmRecharge / rejectRecharge
migrations/Version20260908_Phase4c_bis.sql
```

## Important — il n'y avait pas de maquette Figma pour le site web

Le fichier Figma FinPharma ne contient que des écrans mobiles (frames
iPhone). Le cahier des charges donne la charte (couleurs, Poppins, menu,
CTA, split liste/carte) mais pas de mise en page desktop précise. `public/
css/app.css` est donc une **proposition de design que j'ai construite**,
pas une retranscription d'une maquette — en respectant la charte de marque
mais en prenant de vraies décisions de layout (hero pleine largeur en
dégradé, cards de service qui chevauchent le hero, footer sombre) plutôt
qu'un template SaaS générique. Si tu obtiens un jour une maquette web
dédiée, ce fichier CSS est ce qu'il faudra réviser en premier.

**Aperçu publié** : un aperçu HTML autonome (3 écrans, avec de vraies
pharmacies de Marrakech tirées de ton `pharmacies.json`) a été publié
séparément pour que tu puisses voir le rendu sans avoir besoin de faire
tourner Symfony — voir le lien plus haut dans la conversation.

## Décisions notables

1. **`SeoService` centralise absolument tout** (§10) : title, description,
   canonical, hreflang, OG, JSON-LD. Un seul point d'entrée par type de
   page (`forHome`, `forPharmacyList`, `forPharmacyShow`), jamais construit
   dans un controller ou un template.

2. **hreflang FR/AR uniquement**, généré à partir de la route courante
   plutôt que codé en dur — conforme à la décision confirmée (EN retiré).

3. **`StructuredDataService::pharmacy()` omet `openingHoursSpecification`
   si aucun horaire n'existe** plutôt que d'inventer une plage horaire par
   défaut — un schema JSON-LD incomplet mais honnête est préférable à des
   données inventées (risque de pénalité qualité Google, et surtout ce
   serait juste faux).

4. **Sitemap médicaments exclut `needsReview=true`** (§Addendum §B) — une
   fiche dont le rapprochement de sources est incertain n'est pas indexée
   tant qu'elle n'a pas été validée manuellement en back-office.

5. **La carte (`listing-page__map`) est un simple point d'ancrage HTML**
   avec les données pharmacies en `data-*`, pas encore de vraie
   intégration Leaflet/MapLibre — le script `pharmacy-map.js` référencé
   dans les templates n'existe pas encore. Volontaire : le choix de la
   librairie de carte (Leaflet + tuiles OSM gratuites vs Mapbox/Google Maps
   payant) est une décision produit/coût que je n'ai pas voulue prendre à
   ta place. Dis-moi ta préférence et je câble la vraie carte au prochain
   lot.

6. **Wallet — recharge manuelle tracée.** `WalletRechargeRequest` ne
   déclenche aucun crédit automatique : l'utilisateur déclare "j'ai envoyé
   X DH par PayPal/virement, référence Y", ça remonte dans une file admin
   (`GET /api/v1/admin/wallet/recharge-requests`), et l'admin confirme
   manuellement après avoir vérifié le reçu reçu sur WhatsApp (le montant
   confirmé peut différer de l'annoncé). C'est la traçabilité minimale pour
   qu'un vrai humain gère ça sans perdre le fil, sans construire de
   véritable intégration de paiement (conforme à ta décision).

## Ce qui manque encore pour ces écrans

- **Carte interactive réelle** (cf. point 5 ci-dessus — en attente de ta
  préférence Leaflet/OSM vs Mapbox/Google Maps).
- **Écrans médecin/laboratoire/médicament/blog** — seul Pharmacie (priorité
  2 du cahier des charges) a des templates complets pour l'instant.
- **`SeoService::forPharmacyList` avec FAQ** — le schema `FAQPage` existe
  dans `StructuredDataService` mais n'est pas encore branché sur une vraie
  FAQ éditoriale par ville (§11 : "FAQ lorsque pertinent").
- **CSS compilé/optimisé** — `app.css` est un fichier unique lisible, pas
  encore passé par un build (PurgeCSS, minification) — à faire au moment
  de la mise en prod.

## Mise en route sur ton vrai projet

```bash
# aucune nouvelle dépendance composer

# copier src/, templates/, translations/, public/, migrations/ (fusionner avec 4a+4b+4c)
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
bin/console lint:container
bin/console lint:twig templates/
bin/console lint:yaml translations/
```

## Prochaine étape proposée

Dis-moi ta préférence sur la carte (Leaflet/OSM gratuit vs Mapbox/Google
payant), et je peux soit câbler la vraie carte + JS pour ces écrans, soit
enchaîner sur les écrans Médecins/Laboratoires/Médicaments qui suivent le
même patron que Pharmacie maintenant que le socle (`SeoService`, layout,
composants) est posé.
