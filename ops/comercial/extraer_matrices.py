"""Extract explicit salary/price matrices without recalculating source workbooks.

Ambiguous blocks remain in the coverage report; no contractual validity is inferred.
"""
import datetime
import io
import json
import re
import sys
import zipfile
from pathlib import Path

import openpyxl
from preparar_sabana import normal, number

MONTHS = dict(zip(('ENERO FEBRERO MARZO ABRIL MAYO JUNIO JULIO AGOSTO SEPTIEMBRE OCTUBRE NOVIEMBRE DICIEMBRE').split(), range(1, 13)))


def reference_date(value):
    if isinstance(value, (datetime.datetime, datetime.date)):
        return value.strftime('%Y-%m-%d')
    text = normal(value)
    match = re.search(r'\b(\d{1,2})[-/](\d{1,2})[-/](20\d{2})\b', text)
    if match:
        day, month, year = map(int, match.groups())
    else:
        match = re.search(r'\bP0?(1[0-2]|[1-9])[- ]?(202[0-9]|2[0-9])\b', text)
        if match:
            month, year = map(int, match.groups())
            year = year + 2000 if year < 100 else year
            day = 1
        else:
            match = re.search(r'\b(' + '|'.join(MONTHS) + r')\s+(20\d{2})\b', text)
            if not match:
                return None
            month, year, day = MONTHS[match[1]], int(match[2]), 1
    try:
        return datetime.date(year, month, day).isoformat()
    except ValueError:
        return None


def extract_sheet(rows, raw_rows, title, price_labels=None):
    """Accept only adjacent label/value columns and a salary-anchored heading.

    All columns are scanned; an HHEE calculation to the right of the base-price
    column is never treated as the base tariff. Dates come from this block only.
    """
    accepted, issues = [], []
    extended = price_labels is not None
    price_labels = price_labels or {'PRECIO VENTA': None, 'PRECIO DE VENTA': None}
    def val(r, c):
        return rows[r - 1][c - 1].value if 0 < r <= len(rows) and 0 < c <= len(rows[r - 1]) else None
    for row in rows:
        for label in row:
            if normal(label.value) not in price_labels:
                continue
            pr, col = label.row, label.column
            reason = None
            start = next((r for r in range(pr - 1, max(0, pr - 65), -1) if normal(val(r, col)) == 'SUELDO BASE'), None)
            value_col = col + 1
            if start and any(normal(val(r, col + 1)) == '%' and normal(val(r, col + 2)) in ('VALOR $', 'VALOR ($)') for r in range(max(1, start - 5), start)):
                value_col = col + 2
            price = number(val(pr, value_col))
            if price is None or price < 0:
                reason = 'Precio sin valor numérico guardado.'
            elif start is None:
                reason = 'No se reconoce el bloque de remuneraciones.'
            if reason:
                issues.append(dict(hoja=title, celda=label.coordinate, motivo=reason))
                continue
            headings = [(r, val(r, col)) for r in range(max(1, start - 5), start) if isinstance(val(r, col), str)]
            cargo_candidates = [(r, text) for r, text in headings if normal(text) not in ('VALOR', 'VALOR $', 'CARGO', 'CARGO DIA', 'CONCEPTO', 'REMUNERACIONES')
                                and not reference_date(text) and not re.search(r'TARIFA|COTIZACI|PROFORMA|^P\d|^20\d\d|HHEE|SUELDO|VALOR', normal(text))]
            if not cargo_candidates:
                issues.append(dict(hoja=title, celda=label.coordinate, motivo='Cargo no identificado en el encabezado del bloque.'))
                continue
            cr, cargo = cargo_candidates[-1]
            if any(re.search(r'CON ERROR|NO USAR', normal(text)) for _, text in headings):
                issues.append(dict(hoja=title, celda=label.coordinate, motivo='Bloque marcado con error o NO USAR.'))
                continue
            periods = [(r, text) for r, text in headings if r < cr and reference_date(text)]
            date = reference_date(periods[-1][1]) if periods else None
            period = str(periods[-1][1]) if periods else 'Período por confirmar'
            # Never inherit the sheet year: a P626 sheet may contain January baselines.
            components = []
            for r in range(start, pr + 1):
                concept = val(r, col)
                if not isinstance(concept, str) or concept.startswith('='):
                    continue
                cell = rows[r - 1][value_col - 1]
                raw = raw_rows[r - 1][value_col - 1].value
                components.append(dict(concepto=concept.strip(), valor=cell.value, celda=f'{openpyxl.utils.get_column_letter(value_col)}{r}',
                                       formula=raw if isinstance(raw, str) and raw.startswith('=') else None))
            bad = [x['celda'] for x in components if isinstance(x['valor'], str) and x['valor'].startswith('#')]
            if bad:
                issues.append(dict(hoja=title, celda=label.coordinate, motivo='Errores guardados en el bloque: ' + ', '.join(bad)))
                continue
            unit = price_labels[normal(label.value)] or ('día' if re.search(r'\b(POR DIA|DIARIO|DIARIA)\b', normal(cargo)) else None)
            accepted.append(dict(cargo=cargo.strip(), precio=price, fecha_referencia=date, periodo=period,
                                 hoja=title, celda=rows[pr - 1][value_col - 1].coordinate, conceptos=components,
                                 unidad=unit, etiqueta_precio=label.coordinate))
            if extended:
                context = [str(text).strip() for r, text in headings if r < cr and not reference_date(text)]
                accepted[-1]['variante'] = ' · '.join([str(label.value).strip()] + context)[:255]
    return accepted, issues


def source_context(path):
    name = Path(path).stem
    prefix = re.split(r'\s+-\s+(?:TARIFA|COTIZACI)', name, maxsplit=1, flags=re.I)[0].strip()
    # Preserve the actual source designation. It is not a guessed master ID.
    center = prefix if len(prefix) <= 255 else None
    modes = set(re.findall(r'\b(EST|SUB)\b', normal(prefix)))
    mode = next(iter(modes)) if len(modes) == 1 else None
    if not modes:
        if '/2-EST/' in path:
            mode = 'EST'
        elif '/1-SUB/' in path or '/3-CCU SUB P626/' in path:
            mode = 'SUB'
    names = ['DHL', 'CCU', 'MAERSK', 'AGROSUPER', 'SODIMAC', 'UNIMARC', 'TOTTUS', 'MEDTRONIC', 'PULMAHUE', 'SKECHERS', 'LA POLAR', 'COMERCIAL MK', 'COMERCIAL SAAB', 'HAMBURGO', 'SELECTA', 'LAF', 'MM GROUP', 'IDL', 'AMERINODE', 'GRUPO AXO']
    matches = [n for n in names if re.match(r'^' + re.escape(n) + r'\b', normal(prefix))]
    customer = matches[0] if len(matches) == 1 else 'Por homologar'
    canonical = {'MAERSK': 'Maersk', 'AGROSUPER': 'Agrosuper', 'SODIMAC': 'Sodimac', 'UNIMARC': 'Unimarc', 'TOTTUS': 'Tottus', 'MEDTRONIC': 'Medtronic', 'PULMAHUE': 'Pulmahue', 'SKECHERS': 'Skechers', 'LA POLAR': 'La Polar', 'COMERCIAL MK': 'Comercial MK', 'COMERCIAL SAAB': 'Comercial SAAB', 'HAMBURGO': 'Hamburgo', 'SELECTA': 'Selecta', 'MM GROUP': 'MM Group', 'AMERINODE': 'Amerinode', 'GRUPO AXO': 'Grupo Axo'}
    customer = canonical.get(customer, customer)
    return customer, center, mode


def extend(root, base):
    inventory = json.loads((root / 'inventory.json').read_text(encoding='utf-8'))
    sources = {s['clave']: s for s in base['fuentes']}
    seen = {(r['fuente_clave'], r['hoja'], r['celda']) for r in base['tarifas']}
    coverage, new = [], []
    for item in inventory:
        if item['archive'] == 'IDL.zip':
            continue
        key = item['archive'] + '/' + item['path']
        source = sources[key]
        report = dict(fuente=key, nuevas=0, bloques_omitidos=[])
        if source['estado'] == 'excluida' or 'MODELO NUEVO' in normal(item['path']):
            report['motivo'] = 'Excluido o plantilla sin cliente.'
            coverage.append(report)
            continue
        with zipfile.ZipFile(root / 'sources' / item['archive']) as archive:
            content = archive.read(item['path'])
        values = openpyxl.load_workbook(io.BytesIO(content), read_only=True, data_only=True)
        formulas = openpyxl.load_workbook(io.BytesIO(content), read_only=True, data_only=False)
        customer, center, mode = source_context(item['path'])
        for sheet in values:
            # Exclude personnel tables; only tariff matrices are in scope.
            if re.search(r'LIBRO.*REM|MAESTRO.*EMPLEAD', normal(sheet.title)):
                continue
            meta = next(s for s in item['sheets'] if s['name'] == sheet.title)
            if not any(normal(c[1]) in ('PRECIO VENTA', 'PRECIO DE VENTA') for row in meta['top_rows'] for c in row):
                continue
            extracted, issues = extract_sheet(list(sheet.iter_rows()), list(formulas[sheet.title].iter_rows()), sheet.title)
            report['bloques_omitidos'].extend(issues)
            for record in extracted:
                label = record.pop('etiqueta_precio')
                identity = (key, sheet.title, record['celda'])
                if identity in seen or (key, sheet.title, label) in seen:
                    continue
                seen.add(identity)
                new.append(dict(fuente_clave=key, cliente=customer, centro=center, modalidad=mode,
                                categoria=None, variante='Base', moneda='CLP', **record,
                                observaciones='Matriz de remuneraciones: precio guardado y conceptos originales, sin recalcular. Cliente/centro según denominación del archivo; homologación, unidad y vigencia contractual pendientes. Fecha tomada únicamente del encabezado del bloque; un mes se representa con el día 1.'))
                report['nuevas'] += 1
        values.close()
        formulas.close()
        if report['nuevas']:
            source['estado'] = 'parcial'
            source['observaciones'] = 'Extraídas matrices con sueldo base, cargo y precio de venta explícitos. Cobertura parcial: tablas por filas, tramos, producción y bloques ambiguos requieren revisión. Valores guardados sin recalcular; no acredita vigencia.'
        elif source['estado'] == 'pendiente':
            source['observaciones'] = 'Evaluado: sin matrices adicionales de cargo/sueldo base/precio de venta reconocidas con seguridad. Revisar formato específico.'
        coverage.append(report)
        print(json.dumps({'archivo': item['path'], 'nuevas': report['nuevas']}, ensure_ascii=True), flush=True)
    base['tarifas'].extend(new)
    return base, dict(nuevas=len(new), fuentes_con_nuevas=sum(x['nuevas'] > 0 for x in coverage), fuentes=coverage)


if __name__ == '__main__':
    root = Path(sys.argv[1])
    base = json.loads((root / 'lote-sabana.json').read_text(encoding='utf-8'))
    batch, report = extend(root, base)
    (root / 'lote-sabana-ampliado.json').write_text(json.dumps(batch, ensure_ascii=False, default=str), encoding='utf-8')
    (root / 'cobertura-matrices.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({k: v for k, v in report.items() if k != 'fuentes'}))
