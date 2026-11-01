# Médicaments : normalisation des 3 sources

| Source | Fichier | Particularités |
|---|---|---|
| Guide ANAM/CNSS (Excel) | `Medication_liste1.xlsx` | 7 024 lignes, EAN13, **prix en centimes**, taux de remboursement. Quelques dosages convertis en date par Excel. |
| Guide ANAM/CNSS (PDF) | `Guide_des_medicaments_rembourses_dci.pdf` | ~3 100 lignes avec EAN13, prix en dirhams, ajoute `PBR (PH)`. |
| medicament.ma (scraping) | `API/public/data/medications/*.json` | 5 380 fiches, **sans EAN13**, prix en dirhams, textes (indications, ATC, tableau...). |

## Chaîne
1. `python3 tools/medicines/build_canonical.py --xlsx ... --pdf ... --json-dir ...`
   normalise (unités, prix en DH, dosage/forme/conditionnement), réconcilie, écrit
   `medicines_canonical.json` et `reconcile_report.md`.
2. `php bin/console doctrine:migrations:migrate`
3. `php bin/console app:import:medicines:canonical data/medicines/medicines_canonical.json --dry-run`
   puis sans `--dry-run`. L'import est idempotent.

## Règles de réconciliation
- Excel + PDF : jointure exacte par EAN13 (Excel = référence ; le PDF ajoute `phBrCnss`).
- Scraping -> guide : marque + dosage + conditionnement, puis marque + dosage, puis marque seule
  (si une seule ligne du guide), avec départage par forme puis par prix. Jamais de rapprochement
  ambigu : la fiche reste séparée et `needsReview` est levé.
- Un même produit listé sous 2 EAN13 dans le guide reçoit la fiche scraping sur les deux.
- Priorités par champ : DCI / dosage / forme / classe / P-G / prix CNSS = guide ; textes, ATC,
  tableau, statut, prix public commercial = medicament.ma ; présentation et fabricant = medicament.ma
  sinon guide.
