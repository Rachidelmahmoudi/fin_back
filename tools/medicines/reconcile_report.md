# Rapport de réconciliation — médicaments

- Excel guide : **7024** lignes (dont 59 dosages convertis en date par Excel -> à revoir)
- PDF guide : **3104** lignes avec EAN13 ; 3097 déjà dans l'Excel ; **7** uniquement dans le PDF
- medicament.ma : **5380** fiches (aucune n'a d'EAN13)
- Prix Excel ÷ 100 (centimes -> DH). Prix identiques Excel/PDF : 1134 ; différents (éditions différentes) : 1963 ; la valeur Excel est conservée.

## Rapprochement scraping -> guide
- match brand : 49
- match brand+dose : 107
- match brand+dose+ean_double_au_guide : 24
- match brand+dose+forme : 1
- match brand+dose+forme+ean_double_au_guide : 3
- match brand+dose+pack : 1942
- match brand+dose+pack+ean_double_au_guide : 628
- match brand+dose+pack+forme : 45
- match brand+dose+pack+forme+ean_double_au_guide : 4
- match brand+dose+pack+forme+prix : 1
- match brand+dose+pack+prix : 88
- match brand+dose+pack+prix+ean_double_au_guide : 18
- match brand+dose+prix : 14
- match brand+dose+prix+ean_double_au_guide : 1
- match brand+ean_double_au_guide : 5
- match brand+forme : 1
- match brand+prix : 1
- scrape ambigu : 43
- scrape sans correspondance : 2405

## Résultat : **9479** médicaments canoniques
- avec EAN13 : 7031 ; sans EAN13 (scraping seul) : 2448
- avec données guide ET scraping : 3674
- à revoir (needsReview) : 148
- clés matchKey en doublon parmi les sans-EAN : 49

## Complétude des champs (sur 9479)
- dciName : 9371 (98 %)
- dosageValue : 8850 (93 %)
- form : 9461 (99 %)
- presentation : 9472 (99 %)
- manufacturer : 9472 (99 %)
- therapeuticClass : 8989 (94 %)
- ppvCnss : 6981 (73 %)
- priceCommercial : 5692 (60 %)
- indications : 5272 (55 %)
- atcCode : 5541 (58 %)

## Exemples de rapprochements ambigus (non fusionnés)
- ACLAV 100 MG / 12,5 ML, Poudre pour suspension buvable -> ['6118000091691', '6118000091790', '6118010023989']
- ACLAV 500 MG / 50 MG, Poudre pour préparation injectable -> ['6118000091677', '6118000092162', '6118000092179', '6118010000133']
- ASPEGIC 500 MG / 5 ML, Poudre injectable -> ['6118000061090', '6118000061595', '6118010000683']
- ASPRO 500 MG, Comprimé effervescent -> ['6118000150015', '6118000170365']
- BI-TILDIEM LP 120 MG, Comprimé enrobé à libération prolongée -> ['6118001080960', '6118001180080']
- BIOTIC PLUS 500 MG / 62.5 MG, Sachet -> ['6118000190141', '6118000190158']
- CARDIJECT 250 MG / 20 ML, Solution injectable lyophilisée -> ['6118001151011', '6118001300891']
- CO VEZAR 300 MG / 12.5 MG, Comprimé -> ['6118000181101', '6118000181118']
- CO VEZAR 300 MG / 25 MG, Comprimé -> ['6118000181101', '6118000181118']
- CO-IRVEL 300 MG / 25 MG, Comprimé pelliculé -> ['6118000071983', '6118000072768']
- COAVACOR 300 MG / 12.5 MG, Comprimé pelliculé -> ['6118000121244', '6118000121251']
- COAVACOR 300 MG / 25 MG, Comprimé pelliculé -> ['6118000121244', '6118000121251']
- DI-INDO 25 MG, Comprimé dispersible -> ['6118000031307', '6118000032366']
- DI-INDO 25 MG, Comprimé effervescent -> ['6118000031307', '6118000032366']
- EXFORGE HCT 5 MG / 160 MG / 12.5 MG, Comprimé -> ['6118001030972', '6118001030989']