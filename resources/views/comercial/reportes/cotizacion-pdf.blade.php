<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $cotizacion->numero }}</title>
    <style>
        @page { margin: 10mm 9mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.2pt;
            line-height: 1.25;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 4px 5px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        th {
            background: #eef2f7;
            color: #374151;
            font-size: 7.2pt;
            text-transform: uppercase;
        }

        .no-border td, .no-border th { border: 0; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .strong { font-weight: 700; }
        .amount { color: #0f1b4c; font-weight: 700; white-space: nowrap; }
        .section { margin-top: 8px; page-break-inside: avoid; }
        .section-title {
            margin: 0 0 4px;
            padding: 5px 7px;
            background: #0f1b4c;
            color: #fff;
            font-size: 8.5pt;
            font-weight: 700;
        }

        .header td { padding: 0 0 8px; }
        .brand { color: #ff6b35; font-size: 22pt; font-weight: 800; letter-spacing: .2px; }
        .doc-title { color: #0f1b4c; font-size: 15pt; font-weight: 800; text-align: right; }
        .doc-meta { text-align: right; color: #4b5563; font-size: 8pt; }
        .info-label { color: #6b7280; font-size: 6.8pt; font-weight: 700; text-transform: uppercase; }
        .info-value { color: #111827; font-size: 8pt; font-weight: 700; }
        .metric-label { color: #6b7280; font-size: 6.8pt; font-weight: 700; text-transform: uppercase; }
        .metric-value { color: #0f1b4c; font-size: 10pt; font-weight: 800; }
        .group-row td {
            background: #f3f4f6;
            color: #374151;
            font-size: 7.1pt;
            font-weight: 800;
            text-transform: uppercase;
        }
        .total-row td {
            background: #eef2ff;
            font-weight: 800;
        }
        .price-box td {
            background: #0f1b4c;
            color: #fff;
            border-color: #0f1b4c;
            padding: 7px 8px;
        }
        .price-box .price { font-size: 15pt; font-weight: 800; text-align: right; }
        .note {
            margin-top: 8px;
            padding: 7px;
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
            font-size: 7.4pt;
        }
        .footer {
            margin-top: 10px;
            padding-top: 7px;
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 6.8pt;
            text-align: center;
        }
        .watermark {
            position: fixed;
            top: 42%;
            left: 10%;
            color: rgba(0, 0, 0, .06);
            font-size: 64pt;
            font-weight: 800;
            transform: rotate(-35deg);
            z-index: -1;
        }
    </style>
</head>
<body>
@php
    $resumen = $datos_calculo['resumen_excel'] ?? [];
    $horas = $datos_calculo['horas'] ?? [];
    $detallesCalculo = collect($datos_calculo['detalles'] ?? $detalles->toArray());
    $money = fn($value) => '$' . number_format((float) ($value ?? 0), 0, ',', '.');
    $pct = fn($value) => $value === null ? '—' : number_format((float) $value, 2, ',', '.') . '%';
    $get = fn($item, $key) => is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
    $detail = fn($needle) => $detallesCalculo->first(fn($row) => str_contains(mb_strtolower((string) $get($row, 'concepto')), mb_strtolower($needle)));
    $detailValue = fn($needle) => (float) ($get($detail($needle), 'valor') ?? 0);
    $formula = function ($needle, $fallback = '') use ($detail, $get) {
        $row = $detail($needle);
        if (! $row) return $fallback;
        $formula = $get($row, 'formula');
        return is_array($formula) ? ($formula['descripcion'] ?? $fallback) : $fallback;
    };
    $basePct = function ($needle) use ($detail, $get, $money, $pct) {
        $row = $detail($needle);
        if (! $row) return '';
        $parts = [];
        if ((float) ($get($row, 'valor_base') ?? 0) > 0) $parts[] = 'Base ' . $money($get($row, 'valor_base'));
        if ($get($row, 'porcentaje') !== null) $parts[] = $pct($get($row, 'porcentaje'));
        return implode(' · ', $parts);
    };
    $margenPct = (float) ($datos_calculo['margen_porcentaje'] ?? 0);
    $costoBrutoHhee = (float) ($resumen['costoBrutoHhee'] ?? (($resumen['totalImponible'] ?? 0) + (float) $cotizacion->total_cotizaciones));
    $margenHhee = (float) ($resumen['margenHhee'] ?? ($costoBrutoHhee * ($margenPct / 100)));
    $precioVentaHhee = (float) ($resumen['precioVentaHhee'] ?? ($costoBrutoHhee + $margenHhee));
    $lineas = [
        ['Haberes', 'Gratificación legal', $basePct('Gratificación') ?: '25% con tope legal', null, $resumen['gratificacion'] ?? $detailValue('Gratificación'), false],
        ['Haberes', 'Total imponible', 'Sueldo base + bonos + gratificación', null, $resumen['totalImponible'] ?? 0, true],
        ['Haberes', 'Total no imponible', 'Movilización + colación', null, $resumen['totalNoImponible'] ?? 0, false],
        ['Haberes', 'Total haberes', 'Total imponible + no imponible', null, $resumen['totalHaberes'] ?? $cotizacion->total_remuneraciones, true],
        ['Descuentos', 'Imposiciones', 'Descuento trabajador', null, $resumen['imposiciones'] ?? 0, false],
        ['Descuentos', 'Alcance líquido', 'Haberes - imposiciones - IU', null, $resumen['alcanceLiquido'] ?? 0, true],
        ['Descuentos', 'Renta tributable', 'Total imponible - imposiciones', null, $resumen['rentaTributable'] ?? 0, false],
        ['Descuentos', 'Impuesto Único (IU)', 'Factor y rebaja del mantenedor', null, $resumen['impuestoUnico'] ?? 0, false],
        ['Cotizaciones', 'REFPREV', $basePct('REFPREV'), null, $resumen['refprev'] ?? $detailValue('REFPREV'), false],
        ['Cotizaciones', 'SIS', $basePct('SIS'), null, $resumen['sis'] ?? $detailValue('SIS'), false],
        ['Cotizaciones', 'Mutual Seguridad I.S.T.', $basePct('Mutual'), null, $resumen['mutual'] ?? $detailValue('Mutual'), false],
        ['Cotizaciones', 'Seguro Cesantía', $basePct('Cesantía'), null, $resumen['seguroCesantia'] ?? $detailValue('Cesantía'), false],
        ['Cotizaciones', 'Total cotizaciones (ISES)', 'REFPREV + SIS + Mutual + Cesantía', null, $cotizacion->total_cotizaciones, true],
        ['Provisiones', 'Provisión Vacaciones', $formula('Vacaciones', $basePct('Vacaciones')), null, $resumen['provisionVacaciones'] ?? $detailValue('Vacaciones'), false],
        ['Provisiones', 'Provisión Indemnizaciones', $formula('Indemnizaciones', 'Aplica en SUB'), null, $resumen['provisionIndemnizaciones'] ?? $detailValue('Indemnizaciones'), false],
        ['Provisiones', 'Total provisiones', 'Vacaciones + indemnizaciones', null, $cotizacion->total_provisiones, true],
        ['Gastos', 'Seguro Accidentes Personales', 'Valor ingresado', null, $detailValue('Accidentes'), false],
        ['Gastos', 'Otros Seguros / Gastos', 'Valor ingresado', null, $detailValue('Otros Gastos'), false],
        ['Gastos', 'Otros Beneficios / Aguinaldos', 'Valor ingresado', null, $detailValue('Otros Beneficios'), false],
        ['Gastos', 'Gastos Administración', $basePct('Administración'), null, $resumen['gastosAdministracion'] ?? $detailValue('Administración'), false],
        ['Gastos', 'Total gastos operacionales', 'Uniformes + casino + seguros + beneficios + administración', null, $cotizacion->total_gastos, true],
        ['Precio', 'Costo bruto normal', 'Haberes + ISES + provisiones + gastos', null, $resumen['costoBruto'] ?? $cotizacion->subtotal, true],
        ['Precio', 'Margen operacional normal', 'Margen comercial', $datos_calculo['margen_porcentaje'] ?? null, $resumen['margen'] ?? $cotizacion->margen, false],
        ['Precio', 'Precio venta normal', 'Costo bruto normal + margen', null, $resumen['precioVenta'] ?? $cotizacion->precio_venta, true],
        ['HHEE', 'Costo bruto HHEE', 'Total imponible + cotizaciones empresa', null, $costoBrutoHhee, true],
        ['HHEE', 'Margen operacional HHEE', 'Margen sobre base HHEE', $margenPct, $margenHhee, false],
        ['HHEE', 'Precio venta HHEE', 'Base columna HHEE', null, $precioVentaHhee, true],
        ['Horas', 'Hora normal', 'Precio venta / horas mensuales', null, $horas['normal'] ?? ($resumen['horaNormal'] ?? 0), false],
        ['Horas', 'Hora normal HHEE', 'Base HHEE antes de recargos', null, $horas['normal_hhee'] ?? ($resumen['horaNormalHhee'] ?? 0), false],
        ['Horas', 'Hora extra 50%', 'Hora normal HHEE x 1,5', null, $horas['extra_50'] ?? ($resumen['horaExtra50'] ?? 0), false],
        ['Horas', 'Hora extra 100%', 'Hora normal HHEE x 2', null, $horas['extra_100'] ?? ($resumen['horaExtra100'] ?? 0), false],
    ];
@endphp

@if($es_borrador ?? false)
    <div class="watermark">BORRADOR</div>
@endif

<table class="header no-border">
    <tr>
        <td style="width:50%">
            @if($logo)
                <img src="{{ $logo }}" alt="SAEP" style="height:34px;max-width:130px">
            @else
                <div class="brand">saep</div>
            @endif
        </td>
        <td style="width:50%">
            <div class="doc-title">COTIZACIÓN</div>
            <div class="doc-meta">
                <strong>{{ $cotizacion->numero }}</strong><br>
                {{ optional($cotizacion->fecha_cotizacion)->format('d/m/Y') }} · Emitida {{ $fecha_emision }}
            </div>
        </td>
    </tr>
</table>

<table>
    <tr>
        <td style="width:25%"><div class="info-label">Cliente</div><div class="info-value">{{ $cliente->nombre_comercial ?? $cliente->nombre }}</div></td>
        <td style="width:18%"><div class="info-label">RUT</div><div class="info-value">{{ $cliente->rut ?? '—' }}</div></td>
        <td style="width:18%"><div class="info-label">Modalidad</div><div class="info-value">{{ $modalidad->codigo }} · {{ $modalidad->nombre }}</div></td>
        <td style="width:20%"><div class="info-label">Centro de costo</div><div class="info-value">{{ $centroCosto->nombre }}</div></td>
        <td style="width:19%"><div class="info-label">Cargo</div><div class="info-value">{{ $cotizacion->cargo }}</div></td>
    </tr>
    <tr>
        <td colspan="2"><div class="info-label">Email</div><div>{{ $cliente->email ?? '—' }}</div></td>
        <td><div class="info-label">Teléfono</div><div>{{ $cliente->telefono ?? '—' }}</div></td>
        <td colspan="2"><div class="info-label">Dirección / Región</div><div>{{ $cliente->direccion ?? '—' }} · {{ $cliente->region ?? '—' }}</div></td>
    </tr>
</table>

<div class="section">
    <table>
        <tr>
            <td><div class="metric-label">Total haberes</div><div class="metric-value">{{ $money($cotizacion->total_remuneraciones) }}</div></td>
            <td><div class="metric-label">Cotizaciones (ISES)</div><div class="metric-value">{{ $money($cotizacion->total_cotizaciones) }}</div></td>
            <td><div class="metric-label">Provisiones</div><div class="metric-value">{{ $money($cotizacion->total_provisiones) }}</div></td>
            <td><div class="metric-label">Gastos op.</div><div class="metric-value">{{ $money($cotizacion->total_gastos) }}</div></td>
            <td><div class="metric-label">Margen</div><div class="metric-value">{{ $pct($datos_calculo['margen_porcentaje'] ?? null) }}</div></td>
        </tr>
    </table>
    <table class="price-box" style="margin-top:5px">
        <tr>
            <td>PRECIO VENTA NORMAL</td>
            <td class="price">{{ $money($cotizacion->precio_venta) }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Desglose de cálculo tipo Excel</div>
    <table>
        <thead>
            <tr>
                <th style="width:14%">Grupo</th>
                <th style="width:27%">Concepto</th>
                <th style="width:33%">Base / fórmula</th>
                <th style="width:9%" class="right">%</th>
                <th style="width:17%" class="right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @php($grupoActual = null)
            @foreach($lineas as $linea)
                @if($grupoActual !== $linea[0])
                    @php($grupoActual = $linea[0])
                    <tr class="group-row"><td colspan="5">{{ $grupoActual }}</td></tr>
                @endif
                <tr class="{{ $linea[5] ? 'total-row' : '' }}">
                    <td>{{ $linea[0] }}</td>
                    <td class="strong">{{ $linea[1] }}</td>
                    <td class="muted">{{ $linea[2] ?: '—' }}</td>
                    <td class="right">{{ $pct($linea[3]) }}</td>
                    <td class="right amount">{{ $money($linea[4]) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Detalle por concepto guardado</div>
    <table>
        <thead>
            <tr>
                <th style="width:14%">Tipo</th>
                <th style="width:28%">Concepto</th>
                <th style="width:16%" class="right">Base</th>
                <th style="width:9%" class="right">%</th>
                <th style="width:16%" class="right">Valor</th>
                <th style="width:17%">Fórmula</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles->sortBy('orden') as $detalle)
            <tr>
                <td>{{ ucfirst($detalle->tipo) }}</td>
                <td class="strong">{{ $detalle->concepto }}</td>
                <td class="right">{{ $money($detalle->valor_base) }}</td>
                <td class="right">{{ $pct($detalle->porcentaje) }}</td>
                <td class="right amount">{{ $money($detalle->valor) }}</td>
                <td class="muted">{{ $detalle->formula['descripcion'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($uniformes->count() > 0)
<div class="section">
    <div class="section-title">Uniformes y equipos</div>
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="right" style="width:15%">Cantidad</th>
                <th class="right" style="width:20%">Precio unit.</th>
                <th class="right" style="width:20%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($uniformes as $uniforme)
            <tr>
                <td>{{ $uniforme->descripcion }}</td>
                <td class="right">{{ number_format($uniforme->cantidad, 0, ',', '.') }}</td>
                <td class="right">{{ $money($uniforme->precio_unitario) }}</td>
                <td class="right amount">{{ $money($uniforme->cantidad * $uniforme->precio_unitario) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($cotizacion->observaciones)
    <div class="note"><strong>Notas:</strong> {{ $cotizacion->observaciones }}</div>
@endif

<div class="footer">
    <strong>SAEP</strong> · Documento confidencial generado automáticamente.
    Documento {{ $cotizacion->numero }} · Versión {{ $cotizacion->version }} · {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
