# FinPharma — Phase 4e : Tous les écrans du site + carte Leaflet/OSM réelle

## Contenu de ce lot

```
public/js/map.js   <- Leaflet + tuiles OpenStreetMap, vraie implémentation
                       (liste synchronisée avec la carte, clic marker <-> clic card)

src/Controller/v1/
  DoctorController.php      (/medecins, /medecins/ville/{v}, /medecins/specialite/{s}[/{v}], fiche)
  LaboratoryController.php  (/laboratoires[/{ville}], fiche)
  MedicineController.php    (/medicaments, fiche)
  ServiceController.php     (/services, /services/trouver-un-medicament, /services/assistant-pharmaceutique)
  EstablishmentController.php (web) (/ajouter-mon-etablissement + 3 formulaires)
  BlogController.php (stub — voir note plus bas)
  ContactController.php

src/Service/Seo/SeoService.php complété :
  forDoctorList/Show, forLaboratoryList/Show, forMedicineList/Show, forSimplePage (fallback générique)

templates/
  components/{card_doctor,card_laboratory,card_medicine,filters_sidebar,pagination}.html.twig
  v1/doctor/{list,show}.html.twig
  v1/laboratory/{list,show}.html.twig
  v1/medicine/{list,show}.html.twig
  v1/service/{index,find-medicine,assistant}.html.twig
  v1/establishment/{choose,create}.html.twig
  v1/blog/index.html.twig, v1/contact/index.html.twig

public/css/app.css étendu (sidebar filtres, entity-card, medicine-card, static-page, pagination, pins Leaflet)
translations/messages.{fr,ar}.yaml complétées
```

Avec ce lot, **toutes les pages listées à la §9 du cahier des charges ont un
controller + template réel**, à l'exception du Blog (voir note dédiée).

## La carte — patron inspiré de ansonika.com/findoctor

Tu as pointé ce site en référence. J'ai repris ce qui s'y transposait bien :
- **Sidebar de filtres à gauche** (Trier par / Spécialité) plutôt que des
  filtres uniquement en haut — présent sur pharmacies/médecins/laboratoires.
- **Actions rapides sur chaque card** : "Voir sur la carte" (centre + ouvre
  le popup du marker correspondant) et "Itinéraire" (lien Google Maps direct)
  — visibles dans `card_pharmacy.html.twig`/`card_doctor.html.twig`.
- **Liste ↔ carte synchronisées dans les deux sens** : cliquer une card met
  en évidence son marker et centre la carte ; cliquer un marker surligne la
  card correspondante et scrolle vers elle (`public/js/map.js`).

Ce que je n'ai **pas** repris de ce site : leurs badges de notation/avis
(pas de système de reviews dans le cahier des charges FinPharma), et leur
bouton "Book now" (pas de prise de RDV en ligne demandée — le cahier des
charges mentionne juste "prix de consultation" affiché, pas de réservation).

## `public/js/map.js` — détails techniques

- Chargé en `defer`, et Leaflet lui-même (CSS+JS, CDN cdnjs) n'est chargé
  **que si** un `[data-map]` existe sur la page — jamais sur home,
  médicaments ou blog (§34, performance).
- `data-map="list"` lit les données pharmacies/médecins/laboratoires depuis
  un attribut `data-pharmacies` (JSON), généré côté Twig avec les champs
  utiles (id, nom, adresse, lat/lng, url, statut garde) — pas d'appel AJAX
  supplémentaire, tout est déjà dans le HTML rendu côté serveur (bon pour
  le SEO : le contenu existe même sans JS, seule la carte visuelle a besoin
  du script).
- `data-map="detail"` : un seul marker, pour les fiches pharmacie/médecin/
  laboratoire.
- Si aucune coordonnée n'est disponible, affiche un message plutôt qu'une
  carte vide/cassée (`data-empty-label`).

## Note importante — Blog

Je n'ai **pas** modélisé l'entité Article/BlogCategory dans ce lot. Le
cahier des charges liste le blog comme partie de l'architecture SEO
(§12), mais aucune donnée éditoriale n'existe, et un vrai blog mérite son
propre modèle (catégories, tags, auteur, Schema.org Article, §12) plutôt
qu'être ajouté à la va-vite pour "cocher la case". `/blog` existe déjà
(route + template état "à venir") pour que rien ne soit un lien mort dans
le menu ou le sitemap, mais le contenu réel reste un chantier à part.

## Formulaires "Ajouter mon établissement" (web)

Les 3 formulaires (`v1/establishment/create.html.twig`, un seul template
paramétré par `type`) sont rendus mais **la soumission POST n'est pas
câblée** (`action="#"`) — ils appellent visuellement à quoi ressemblera le
parcours, mais la vraie soumission doit passer par les endpoints API déjà
construits en Phase 4b/4c (`POST /api/v1/establishments/doctors`, etc.),
ce qui nécessite un peu de JS fetch() ou un pont Symfony Form -> API que
je n'ai pas voulu improviser sans savoir si tu préfères une vraie session
web connectée ou un simple appel JS avec le JWT déjà en localStorage côté
espace pharmacien. Dis-moi ta préférence et je câble la vraie soumission.

## Aperçu publié — mis à jour

Le lien publié précédemment a été **republié avec le même lien** (5 onglets
maintenant : Accueil, Listing avec vraie carte Leaflet interactive sur les
coordonnées réelles de tes pharmacies de Marrakech, Fiche pharmacie,
Médecins, Médicaments).

## Mise en route sur ton vrai projet

```bash
# aucune nouvelle dépendance composer (Leaflet est chargé côté client via CDN, pas de package PHP)

# copier public/, src/, templates/, translations/ (fusionner avec 4a→4d)
bin/console lint:twig templates/
bin/console lint:yaml translations/
bin/console lint:container
```

## Ce qu'il reste, par ordre de priorité probable

1. **Câbler la vraie soumission des formulaires établissement** (cf. note
   ci-dessus — attend ta préférence sur l'architecture session web vs JWT).
2. **Modéliser le Blog** si le contenu éditorial devient une priorité.
3. **Application mobile Ionic** — n'a pas encore été commencée ; c'est un
   chantier séparé qui consommera les mêmes API (Phase 4b/4c) mais avec le
   design déjà extrait du Figma mobile (contrairement au web, il existe un
   vrai Figma pour ça).
4. **Ask IA, notification de stock, analyses médicales avancées** —
   fonctionnalités "priorité 8" du cahier des charges, pas encore abordées.

Dis-moi ce que tu veux prioriser ensuite.
