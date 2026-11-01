#!/usr/bin/env python3
"""
FinPharma — tools/medicines/build_canonical.py

Normalise les 3 sources de médicaments vers UN schéma canonique, puis les
réconcilie (un médicament réel = une seule ligne) :

  1. Guide ANAM/CNSS Excel   (--xlsx)   : 7 024 lignes, EAN13, prix en CENTIMES
  2. Guide ANAM PDF          (--pdf)    : ~3 100 lignes, EAN13, prix en DIRHAMS
  3. medicament.ma (scraping) (--json-dir) : ~5 400 fiches, SANS EAN13

Sortie : medicines_canonical.json (+ reconcile_report.md).
Ce fichier est lu par `php bin/console app:import:medicines:canonical`.

Dépendances : pip install openpyxl pdfplumber
"""
import argparse, collections, datetime, glob, json, re, sys, unicodedata

# ------------------------------------------------------------------ utilitaires

def norm(s):
    s = unicodedata.normalize('NFKD', str(s or '').replace('Μ', 'M').replace('µ', 'M').replace('μ', 'M'))
    s = ''.join(c for c in s if not unicodedata.combining(c)).upper()
    s = re.sub(r"[^A-Z0-9%/.+ ]", ' ', s)
    return re.sub(r'\s+', ' ', s).strip()

def clean(s):
    s = re.sub(r'\s+', ' ', str(s)).strip() if s is not None else None
    return s or None

UNITS = r'(MG|G|KG|MCG|MICROGRAMME|ML|L|UI|IU|%|MEQ|MMOL|DOSE|DOSES|CP|GBQ|MBQ|KBQ)'
NUM = r'\d+(?:[.,]\d+)?'
DOSE_RE = re.compile(r'(' + NUM + r')\s*' + UNITS + r'?\b', re.I)

def parse_dose(raw):
    """'200 MG' / '1 / 125 MG / MG' / '0.1 %' -> (premier nombre, première unité, texte brut)."""
    if raw is None:
        return None, None, None
    raw = clean(raw)
    if not raw:
        return None, None, None
    n = re.search(NUM, raw)
    u = re.search(r'(?<![A-Za-z])' + UNITS + r'(?![A-Za-z])', norm(raw).replace('%', ' % '), re.I)
    value = n.group(0).replace(',', '.') if n else None
    unit = u.group(1).upper() if u else None
    if unit is None and '%' in raw:
        unit = '%'
    return value, unit, raw

def pack_key(presentation):
    """Nombre d'unités du conditionnement : '1 BOITE 30 COMPRIME' -> 30 ; 'Boîte de 28' -> 28."""
    p = norm(presentation)
    m = re.search(r'(?:BOITE|FLACON|TUBE|POCHE|SACHET|PLAQUETTE|PILULIER|BLISTER|AMPOULE|CARTOUCHE|SERINGUE)S?\s*(?:DE|D)?\s*(\d+(?:[.,]\d+)?)', p)
    if m:
        return m.group(1).replace(',', '.')
    m = re.search(r'\b(\d+(?:[.,]\d+)?)\b', p)
    return m.group(1).replace(',', '.') if m else None

def brand_of(text):
    """Nom de marque sans dosage ni étiquettes : 'FENTANYL MYLAN 100 µG / 2 ML, Solution [P] [SS]' -> 'FENTANYL MYLAN'."""
    t = str(text or '').split(',')[0]
    t = re.sub(r'\[[^\]]*\]', ' ', t)
    t = norm(t)
    t = re.sub(r'\s+' + NUM + r'(\s*' + UNITS + r')?(\s*[/+]\s*' + NUM + r'?(\s*' + UNITS + r')?)*(\s*\bPOUR\b.*)?$', '', t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t

def money(v, centimes=False):
    if v is None or v == '':
        return None
    try:
        x = float(str(v).replace(' ', '').replace(',', '.').replace('dhs', '').strip())
    except ValueError:
        return None
    return round(x / 100, 2) if centimes else round(x, 2)

def rate(v):
    try:
        return round(float(v), 3)
    except (TypeError, ValueError):
        return None

# ------------------------------------------------------------------ adaptateurs

def read_xlsx(path):
    import openpyxl
    wb = openpyxl.load_workbook(path, read_only=True)
    rows = list(wb.active.iter_rows(values_only=True))
    head = rows[0]
    out, date_cells = [], 0
    for r in rows[1:]:
        d = dict(zip(head, r))
        if not d.get('Code'):
            continue
        ean = re.sub(r'\D', '', str(d['Code']))
        flags = []
        dv = d.get('Dosage1')
        if isinstance(dv, (datetime.datetime, datetime.date)):
            # Excel a converti un dosage du type "5/10" en date : valeur irrécupérable de façon fiable.
            date_cells += 1
            dv = None
            flags.append('dosage_converti_en_date_par_excel')
        value, unit, raw = parse_dose(dv if dv is None else str(dv))
        out.append({
            'source': 'cnss_guide_xlsx', 'externalKey': ean, 'ean13': ean,
            'brand': clean(d.get('Nom')), 'dci': clean(d.get('DCI1')),
            'dosageValue': value, 'dosageUnit': clean(d.get('Unite Dosage1')) or unit,
            'form': clean(d.get('Forme')), 'presentation': clean(d.get('Présentation')),
            'manufacturer': clean(d.get('Laboratoire')),
            # PRIX EN CENTIMES dans ce fichier -> dirhams
            'ppvCnss': money(d.get('PPV'), True), 'phCnss': money(d.get('PH'), True), 'ppvBrCnss': money(d.get('PPV - BR'), True),
            'princepsOrGeneric': d.get('Princeps Generique') if d.get('Princeps Generique') in ('P', 'G') else None,
            'therapeuticClass': clean(d.get('Classe Thérapeutique')),
            'reimbursementRate': rate(d.get('TAUX Remboursement')), 'exonerationRate': rate(d.get('Taux Exoneration')),
            'exonerationEligible': {'Oui': True, 'Non': False}.get(clean(d.get('Droit à l\'exonération'))),
            'flags': flags,
        })
    return out, date_cells

def read_pdf(path):
    import pdfplumber
    cols = [('ean', 25, 120), ('brand', 120, 228), ('dci', 228, 338), ('formdose', 338, 463), ('pres', 463, 582),
            ('ppv', 582, 655), ('ppv_br', 655, 732), ('ph', 732, 802), ('ph_br', 802, 870), ('class', 870, 995), ('pg', 995, 1070)]
    price = re.compile(r'^\d{1,3}(?:\s?\d{3})*,\d{2}$')
    raw_rows = []
    with pdfplumber.open(path) as pdf:
        for pn, page in enumerate(pdf.pages, 1):
            k = page.width / 1152
            words = [w for w in page.extract_words(x_tolerance=1.5) if 165 * k < w['top'] < 765 * k]
            col = lambda w: next((n for n, a, b in cols if a <= (w['x0'] + w['x1']) / 2 / k < b), None)
            anchors = sorted([w for w in words if col(w) == 'ppv' and price.match(w['text'])], key=lambda w: w['top'])
            if not anchors:
                continue
            ys = [(a['top'] + a['bottom']) / 2 for a in anchors]
            recs = [collections.defaultdict(list) for _ in anchors]
            for w in words:
                c = col(w)
                if not c:
                    continue
                yc = (w['top'] + w['bottom']) / 2
                i = min(range(len(ys)), key=lambda j: abs(ys[j] - yc))
                recs[i][c].append((round(yc / 3), w['x0'], w['text']))
            for r in recs:
                rec = {c: ' '.join(t[2] for t in sorted(ws)) for c, ws in r.items()}
                rec['page'] = pn
                raw_rows.append(rec)
    out = []
    for r in raw_rows:
        ean = (r.get('ean') or '').strip()
        if not re.fullmatch(r'\d{13}', ean):
            continue  # en-tête de page ou ligne sans EAN (exclue : pas d'identifiant fiable)
        fd = r.get('formdose', '')
        dci = clean(r.get('dci'))
        if dci and dci.endswith(' à'):           # le « à » de « à 12.5 MG » a débordé dans la colonne DCI
            dci, fd = dci[:-2].strip(), fd
        form, dose_raw = (fd.split(' à ', 1) + [None])[:2] if ' à ' in fd else (fd, None)
        if dose_raw is None:
            m = re.search(NUM + r'.*$', fd)
            if m:
                dose_raw, form = m.group(0), fd[:m.start()].strip()
        value, unit, raw = parse_dose(dose_raw)
        out.append({
            'source': 'cnss_guide_pdf', 'externalKey': ean, 'ean13': ean,
            'brand': clean(r.get('brand')), 'dci': dci, 'dosageValue': value, 'dosageUnit': unit,
            'form': clean(form), 'presentation': clean(r.get('pres')),
            'ppvCnss': money(r.get('ppv')), 'ppvBrCnss': money(r.get('ppv_br')),
            'phCnss': money(r.get('ph')), 'phBrCnss': money(r.get('ph_br')),
            'princepsOrGeneric': r.get('pg') if r.get('pg') in ('P', 'G') else None,
            'therapeuticClass': clean(r.get('class')), 'flags': [],
        })
    return out

def read_json_dir(folder):
    out = []
    for f in sorted(glob.glob(folder.rstrip('/') + '/medications_*.json')):
        d = json.load(open(f, encoding='utf-8'))
        out += d if isinstance(d, list) else list(d.values())[0]
    rows = []
    for s in out:
        raw_name = s['name']
        value, unit, raw = parse_dose((s.get('dosage') or '').split('|')[0] or None)
        if value is None:
            m = DOSE_RE.search(raw_name.split(',')[0])
            if m:
                value, unit, _ = parse_dose(m.group(0))
        name_form = clean(raw_name.split(',', 1)[1]) if ',' in raw_name else None
        name_form = re.sub(r'\s*\[[^\]]*\]', '', name_form or '').strip() or None
        rows.append({
            'source': 'medicament_ma', 'externalKey': s['url'], 'ean13': None,
            'displayName': clean(raw_name), 'brand': brand_of(raw_name), 'dci': clean((s.get('composition') or '').split('|')[0]),
            'dosageValue': value, 'dosageUnit': unit, 'form': name_form, 'presentation': clean(s.get('presentation')),
            'manufacturer': clean(s.get('manufacturer')), 'therapeuticClass': clean(s.get('therapeuticClass')),
            'atcCode': clean(s.get('Code ATC')), 'tableau': clean(s.get('Tableau')) if clean(s.get('Tableau')) in ('A', 'B') else None,
            'status': clean(s.get('status')), 'priceCommercial': money(s.get('price')), 'priceHospitalCommercial': money(s.get('hospitalPrice')),
            'indications': clean(s.get('indications')), 'contraindications': clean(s.get('Contres-indication(s)')),
            'precautions': clean(s.get('Mises en garde')),
            'princepsOrGeneric': 'P' if s.get('Princeps') else None,
            'sourceUrl': s['url'], 'flags': [],
        })
    return rows

# ------------------------------------------------------------------ réconciliation

def fix_ref(r):
    r['brandKey'] = brand_of(r['brand'] or r.get('displayName') or '')
    r['packKey'] = pack_key(r.get('presentation'))
    r['doseKey'] = (f"{float(r['dosageValue']):g}" if r.get('dosageValue') else None)
    return r

def form_family(f):
    w = norm(f or '').split()
    return w[0] if w else ''

def signature(g):
    r = g['guide'] or g['pdf']
    return (r.get('ppvCnss'), r.get('phCnss'), r['doseKey'], r['packKey'], form_family(r.get('form')), r['brandKey'])

def tiebreak(s, hits, guide):
    """Plusieurs lignes du guide pour la même clé : départage par forme galénique, puis par prix public."""
    why = ''
    by_form = [e for e in hits if form_family((guide[e]['guide'] or guide[e]['pdf']).get('form')) == form_family(s.get('form'))]
    if by_form and len(by_form) < len(hits):
        hits, why = by_form, '+forme'
    if len(hits) > 1 and s.get('priceCommercial'):
        near = [e for e in hits if (guide[e]['guide'] or guide[e]['pdf']).get('ppvCnss')
                and abs((guide[e]['guide'] or guide[e]['pdf'])['ppvCnss'] - s['priceCommercial']) <= 0.02 * s['priceCommercial']]
        if near and len(near) < len(hits):
            hits, why = near, why + '+prix'
    return hits, why

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--xlsx', required=True); ap.add_argument('--pdf', required=True); ap.add_argument('--json-dir', required=True)
    ap.add_argument('--out', default='medicines_canonical.json'); ap.add_argument('--report', default='reconcile_report.md')
    a = ap.parse_args()

    xl, date_cells = read_xlsx(a.xlsx)
    pdf = read_pdf(a.pdf)
    sc = read_json_dir(a.json_dir)
    for r in xl + pdf + sc:
        fix_ref(r)

    # --- 1. Guide : xlsx (référence) + pdf, jointure EXACTE par EAN13
    guide = collections.OrderedDict()
    for r in xl:
        guide[r['ean13']] = {'guide': r, 'pdf': None, 'scrape': None, 'conf': None}
    pdf_only = 0
    for r in pdf:
        if r['ean13'] in guide:
            guide[r['ean13']]['pdf'] = r
        else:
            guide[r['ean13']] = {'guide': None, 'pdf': r, 'scrape': None, 'conf': None}; pdf_only += 1

    # --- 2. Scraping -> guide, par clés de plus en plus larges (jamais de rapprochement ambigu)
    idx = {k: collections.defaultdict(list) for k in ('bdp', 'bd', 'b')}
    for e, g in guide.items():
        r = g['guide'] or g['pdf']
        if r['brandKey']:
            idx['bdp'][(r['brandKey'], r['doseKey'], r['packKey'])].append(e)
            idx['bd'][(r['brandKey'], r['doseKey'])].append(e)
            idx['b'][r['brandKey']].append(e)
    stats = collections.Counter(); unmatched, ambiguous = [], []
    taken = {}
    for s in sc:
        cand, tier = [], None
        for t, key in (('brand+dose+pack', ('bdp', (s['brandKey'], s['doseKey'], s['packKey']))),
                       ('brand+dose', ('bd', (s['brandKey'], s['doseKey']))),
                       ('brand', ('b', s['brandKey']))):
            if not s['brandKey'] or (t != 'brand' and s['doseKey'] is None):
                continue
            hits = [e for e in idx[key[0]].get(key[1], []) if e not in taken]
            if t == 'brand' and s['doseKey']:
                # marque seule : accepté uniquement si le guide n'a qu'UNE ligne pour cette marque
                hits = hits if len(idx['b'].get(s['brandKey'], [])) == 1 else []
            if len(hits) > 1:
                hits, why = tiebreak(s, hits, guide)
                t = t + why
            if len(hits) > 1 and len({signature(guide[e]) for e in hits}) == 1:
                hits, t = hits, t + '+ean_double_au_guide'   # même produit listé sous 2 EAN13 : la fiche est rattachée aux deux
                cand, tier = hits, t; break
            if len(hits) == 1:
                cand, tier = hits, t; break
            if len(hits) > 1:
                ambiguous.append((s['displayName'], hits[:4])); tier = 'ambiguous'; break
        if cand:
            for e in cand:
                taken[e] = s['externalKey']
                guide[e]['scrape'] = s; guide[e]['conf'] = tier
            stats['match ' + tier] += 1
        else:
            stats['scrape sans correspondance' if tier != 'ambiguous' else 'scrape ambigu'] += 1
            unmatched.append((s, tier))

    # --- 3. Fusion champ par champ (priorités fixes, documentées dans le rapport)
    def pick(*vals):
        for v in vals:
            if v not in (None, '', []):
                return v
    records = []
    for e, g in guide.items():
        x, p, s = g['guide'], g['pdf'], g['scrape']
        base = x or p
        reasons = list(base.get('flags', []))
        notes = ['meme_produit_sous_plusieurs_ean13'] if g['conf'] and 'ean_double' in g['conf'] else []
        if g['conf'] in ('brand',):
            reasons.append('rapprochement_scraping_par_marque_seule')
        if s and base['doseKey'] and s['doseKey'] and base['doseKey'] != s['doseKey']:
            reasons.append('dosage_different_entre_sources')
        rec = {
            'ean13Code': e, 'matchKey': None,
            'name': pick(s and s['displayName'], ' '.join(filter(None, [base['brand'], base.get('dosageValue') and f"{base['dosageValue']} {base.get('dosageUnit') or ''}".strip(), base.get('form')]))),
            'brandName': base['brand'],
            'dciName': pick(base.get('dci'), s and s['dci']),
            'dosageValue': pick(base.get('dosageValue'), s and s['dosageValue']),
            'dosageUnit': pick(base.get('dosageUnit'), s and s['dosageUnit']),
            'form': pick(base.get('form'), s and s['form']),
            'presentation': pick(s and s['presentation'], base.get('presentation')),
            'packQuantity': pick(base['packKey'] and float(base['packKey']) and int(float(base['packKey'])), None),
            'manufacturer': pick(s and s['manufacturer'], base.get('manufacturer')),
            'therapeuticClass': pick(base.get('therapeuticClass'), s and s['therapeuticClass']),
            'atcCode': s and s.get('atcCode'), 'tableau': s and s.get('tableau'), 'status': s and s.get('status'),
            'princepsOrGeneric': pick(base.get('princepsOrGeneric'), s and s.get('princepsOrGeneric')),
            'indications': s and s.get('indications'), 'contraindications': s and s.get('contraindications'), 'precautions': s and s.get('precautions'),
            'priceCommercial': s and s.get('priceCommercial'), 'priceHospitalCommercial': s and s.get('priceHospitalCommercial'),
            'ppvCnss': base.get('ppvCnss'), 'phCnss': base.get('phCnss'), 'ppvBrCnss': base.get('ppvBrCnss'),
            'phBrCnss': pick(p and p.get('phBrCnss'), x and x.get('phBrCnss')),
            'isCnssReimbursed': True,
            'reimbursementRate': x and x.get('reimbursementRate'), 'exonerationRate': x and x.get('exonerationRate'),
            'exonerationEligible': x and x.get('exonerationEligible'),
            'sourceUrl': s and s['sourceUrl'],
            'matchConfidence': 'ean13' if not s else g['conf'],
            'needsReview': bool(reasons), 'reviewReasons': reasons, 'notes': notes,
            'sources': [{'source': r['source'], 'externalKey': r['externalKey'] + ('#' + e if (r is s and notes) else '')} for r in (x, p, s) if r],
        }
        records.append(rec)
    for s, tier in unmatched:
        reasons = ['scraping_ambigu_avec_plusieurs_lignes_du_guide'] if tier == 'ambiguous' else []
        records.append({
            'ean13Code': None, 'name': s['displayName'], 'brandName': s['brand'], 'dciName': s['dci'],
            'dosageValue': s['dosageValue'], 'dosageUnit': s['dosageUnit'], 'form': s['form'], 'presentation': s['presentation'],
            'packQuantity': int(float(s['packKey'])) if s['packKey'] else None,
            'manufacturer': s['manufacturer'], 'therapeuticClass': s['therapeuticClass'], 'atcCode': s['atcCode'], 'tableau': s['tableau'],
            'status': s['status'], 'princepsOrGeneric': s['princepsOrGeneric'], 'indications': s['indications'],
            'contraindications': s['contraindications'], 'precautions': s['precautions'],
            'priceCommercial': s['priceCommercial'], 'priceHospitalCommercial': s['priceHospitalCommercial'],
            'ppvCnss': None, 'phCnss': None, 'ppvBrCnss': None, 'phBrCnss': None, 'isCnssReimbursed': False,
            'reimbursementRate': None, 'exonerationRate': None, 'exonerationEligible': None, 'sourceUrl': s['sourceUrl'],
            'matchConfidence': 'aucun', 'needsReview': bool(reasons), 'reviewReasons': reasons,
            'sources': [{'source': 'medicament_ma', 'externalKey': s['externalKey']}],
        })
    # clé de dédoublonnage stable (utilisée par l'import quand il n'y a pas d'EAN13)
    for r in records:
        r['matchKey'] = '|'.join([brand_of(r['brandName'] or r['name']), r['dosageValue'] and f"{float(r['dosageValue']):g}" or '',
                                  (r['dosageUnit'] or '').upper(), norm(r['form'] or ''), str(r['packQuantity'] or '')]).strip('|')
    json.dump(records, open(a.out, 'w', encoding='utf-8'), ensure_ascii=False)

    # --- rapport
    dup = collections.Counter(r['matchKey'] for r in records if not r['ean13Code'])
    comp = collections.Counter()
    for r in records:
        for k in ('dciName', 'dosageValue', 'form', 'presentation', 'manufacturer', 'therapeuticClass', 'ppvCnss', 'priceCommercial', 'indications', 'atcCode'):
            if r.get(k) not in (None, ''):
                comp[k] += 1
    # écarts de prix xlsx vs pdf
    same = diff = 0
    for g in guide.values():
        if g['guide'] and g['pdf']:
            if g['guide']['ppvCnss'] == g['pdf']['ppvCnss']: same += 1
            else: diff += 1
    n = len(records)
    L = [f"# Rapport de réconciliation — médicaments\n",
         f"- Excel guide : **{len(xl)}** lignes (dont {date_cells} dosages convertis en date par Excel -> à revoir)",
         f"- PDF guide : **{len(pdf)}** lignes avec EAN13 ; {len(pdf) - pdf_only} déjà dans l'Excel ; **{pdf_only}** uniquement dans le PDF",
         f"- medicament.ma : **{len(sc)}** fiches (aucune n'a d'EAN13)",
         f"- Prix Excel ÷ 100 (centimes -> DH). Prix identiques Excel/PDF : {same} ; différents (éditions différentes) : {diff} ; la valeur Excel est conservée.\n",
         "## Rapprochement scraping -> guide"] + [f"- {k} : {v}" for k, v in sorted(stats.items())] + [
         f"\n## Résultat : **{n}** médicaments canoniques",
         f"- avec EAN13 : {sum(1 for r in records if r['ean13Code'])} ; sans EAN13 (scraping seul) : {sum(1 for r in records if not r['ean13Code'])}",
         f"- avec données guide ET scraping : {sum(1 for r in records if len(r['sources']) > 1 and any(s['source']=='medicament_ma' for s in r['sources']))}",
         f"- à revoir (needsReview) : {sum(1 for r in records if r['needsReview'])}",
         f"- clés matchKey en doublon parmi les sans-EAN : {sum(1 for v in dup.values() if v > 1)}\n",
         "## Complétude des champs (sur " + str(n) + ")"] + [f"- {k} : {v} ({100*v//n} %)" for k, v in comp.items()] + [
         "\n## Exemples de rapprochements ambigus (non fusionnés)"] + [f"- {a_} -> {b_}" for a_, b_ in ambiguous[:15]]
    open(a.report, 'w', encoding='utf-8').write('\n'.join(L))
    print('\n'.join(L))

if __name__ == '__main__':
    main()
