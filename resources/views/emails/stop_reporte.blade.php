<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Reporte Tarjeta STOP CCU</title>
<!--[if mso]>
<style type="text/css">
body, table, td, th, p, span, h1, h2, h3 { font-family: Arial, Helvetica, sans-serif !important; }
</style>
<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
@php
    $a = $analytics;
    $total = $a['totalRows'] ?? 0;
    $clasif = $a['clasificacion'] ?? [];
    $pos = $clasif['Positiva'] ?? $clasif['positiva'] ?? 0;
    $neg = $clasif['Negativa'] ?? $clasif['negativa'] ?? 0;
    $pctPos = $total > 0 ? round(($pos / $total) * 100, 1) : 0;
    $pctNeg = $total > 0 ? round(($neg / $total) * 100, 1) : 0;
    $centrosData   = $a['centros'] ?? [];
    $centrosNeg    = $a['centrosNeg'] ?? [];
    $centrosPos    = $a['centrosPos'] ?? [];
    $areasData     = $a['areas'] ?? [];
    $areasNeg      = $a['areasNeg'] ?? [];
    $areasPos      = $a['areasPos'] ?? [];
    $intExt        = $a['internoExterno'] ?? [];
    $empresas      = $a['empresas'] ?? [];
    $empresasNeg   = $a['empresasNeg'] ?? [];
    $empresasPos   = $a['empresasPos'] ?? [];
    $empresasObs   = $a['empresasObservador'] ?? [];
    $turnos        = $a['turnos'] ?? [];
    $antiguedades  = $a['antiguedades'] ?? [];
    $cargos        = $a['cargos'] ?? [];
    $topObs        = $a['topObservadores'] ?? [];
    $obsNeg        = $a['observadoresNeg'] ?? [];
    $obsPos        = $a['observadoresPos'] ?? [];
    $negPorTipo    = $a['negPorTipo'] ?? [];
    $posPorTipo    = $a['posPorTipo'] ?? [];
    $topNeg        = $a['topNegTrabajadores'] ?? [];
    $topPos        = $a['topPosTrabajadores'] ?? [];
    $byMonth       = $a['byMonth'] ?? [];
    $byMonthNeg    = $a['byMonthNeg'] ?? [];
    $byMonthPos    = $a['byMonthPos'] ?? [];
    $centrosCount  = count($centrosData);
    $obsCount      = count($topObs);

    // Top falta negativa
    $topFaltaNeg = !empty($negPorTipo) ? array_key_first($negPorTipo) : null;
    $topFaltaNegCnt = $topFaltaNeg ? $negPorTipo[$topFaltaNeg] : 0;
    // Top trabajador negativo
    $topTrabNeg = !empty($topNeg) ? array_key_first($topNeg) : null;
    $topTrabNegCnt = $topTrabNeg ? $topNeg[$topTrabNeg] : 0;

    // --- Comparativa año anterior y acumulado ---
    $comp = $comparison ?? [];
    $ytd = $comp['ytd'] ?? [];
    $prev = $comp['prevYear'] ?? [];
    $hasComp = !empty($ytd) && !empty($prev);

    if ($hasComp) {
        $ytdTotal = $ytd['total'] ?? 0;
        $ytdPos = $ytd['pos'] ?? 0;
        $ytdNeg = $ytd['neg'] ?? 0;
        $prevYearLabel = $prev['year'] ?? ((int) date('Y') - 1);
        $prevTotal = $prev['sameMonthTotal'] ?? 0;
        $prevPos = $prev['sameMonthPos'] ?? 0;
        $prevNeg = $prev['sameMonthNeg'] ?? 0;
        $prevYtdTotal = $prev['ytdTotal'] ?? 0;
        $prevYtdPos = $prev['ytdPos'] ?? 0;
        $prevYtdNeg = $prev['ytdNeg'] ?? 0;

        // Deltas mes actual vs mismo mes año anterior
        $deltaTotal = $total - $prevTotal;
        $deltaNeg = $neg - $prevNeg;
        $deltaPos = $pos - $prevPos;
        // Deltas YTD vs YTD anterior
        $deltaYtdTotal = $ytdTotal - $prevYtdTotal;
        $deltaYtdNeg = $ytdNeg - $prevYtdNeg;
        $deltaYtdPos = $ytdPos - $prevYtdPos;

        // Arrow helper
        $arrow = function($val) {
            if ($val > 0) return ['▲', '#ef4444', '+' . number_format($val)];
            if ($val < 0) return ['▼', '#16a34a', number_format($val)];
            return ['─', '#6b7280', '0'];
        };
    }
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
<tr><td align="center" style="padding:24px 8px;">
<table role="presentation" width="700" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; max-width:700px; border-radius:4px;">

{{-- ═══════════ HEADER ═══════════ --}}
<tr>
<td style="background-color:#1B5E20; padding:24px 32px; text-align:center; border-radius:4px 4px 0 0;">
    <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:bold; letter-spacing:0.5px;">
        AUDITOR&Iacute;AS STOP CCU &mdash; Reporte {{ $frecuencia ?? 'Semanal' }}
        @if(strtolower($frecuencia ?? 'Semanal') === 'semanal')
        &mdash; Semana {{ now()->subWeek()->isoFormat('W') }}
        @endif
    </h1>
    <p style="margin:6px 0 0; color:#a5d6a7; font-size:13px;">
        Reporte de Observaciones de Seguridad &mdash; {{ $mesLabel ?? $periodo }}
    </p>
</td>
</tr>

{{-- ═══════════ KPIs (3 cards como CCU) ═══════════ --}}
<tr>
<td style="padding:16px 20px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="33%" style="padding:4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#1B5E20; border-radius:6px;">
            <tr><td style="padding:14px 8px; text-align:center;">
                <span style="font-size:11px; color:#fca5a5; text-transform:uppercase; letter-spacing:1px;">Tarjetas STOP CCU</span><br/>
                <span style="font-size:32px; font-weight:bold; color:#ffffff;">{{ number_format($total) }}</span>
            </td></tr>
            </table>
        </td>
        <td width="33%" style="padding:4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fce7f3; border:2px solid #ec4899; border-radius:6px;">
            <tr><td style="padding:14px 8px; text-align:center;">
                <span style="font-size:11px; color:#9d174d; text-transform:uppercase; letter-spacing:1px;">Positivas</span><br/>
                <span style="font-size:32px; font-weight:bold; color:#ec4899;">{{ number_format($pos) }}</span>
                <span style="font-size:11px; color:#9d174d;">({{ $pctPos }}%)</span>
            </td></tr>
            </table>
        </td>
        <td width="33%" style="padding:4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fef2f2; border:2px solid #ef4444; border-radius:6px;">
            <tr><td style="padding:14px 8px; text-align:center;">
                <span style="font-size:11px; color:#991b1b; text-transform:uppercase; letter-spacing:1px;">Negativas</span><br/>
                <span style="font-size:32px; font-weight:bold; color:#ef4444;">{{ number_format($neg) }}</span>
                <span style="font-size:11px; color:#991b1b;">({{ $pctNeg }}%)</span>
            </td></tr>
            </table>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- ═══════════ BARRA POS/NEG ═══════════ --}}
<tr>
<td style="padding:4px 24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        @if($neg > 0)
        <td width="{{ $pctNeg }}%" style="background-color:#991b1b; padding:7px 0; text-align:center;">
            <span style="color:#ffffff; font-size:10px; font-weight:bold;">{{ $pctNeg }}% Neg</span>
        </td>
        @endif
        @if($pos > 0)
        <td width="{{ $pctPos }}%" style="background-color:#22c55e; padding:7px 0; text-align:center;">
            <span style="color:#ffffff; font-size:10px; font-weight:bold;">{{ $pctPos }}% Pos</span>
        </td>
        @endif
    </tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px;">
    <tr>
        <td style="font-size:10px; color:#991b1b;">&#9632; Negativa</td>
        <td style="font-size:10px; color:#22c55e; text-align:right;">&#9632; Positiva</td>
    </tr>
    </table>
</td>
</tr>

{{-- ═══════════ COMPARATIVA AÑO ANTERIOR + ACUMULADO AÑO ═══════════ --}}
@if($hasComp)
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:14px; font-weight:bold; letter-spacing:0.5px;">&#128202; Comparativa vs {{ $prevYearLabel }} &amp; Acumulado A&ntilde;o</span>
    </td></tr>
    </table>

    {{-- Tabla comparativa --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:6px; border:1px solid #e2e8f0; border-collapse:collapse;">
    <tr style="background-color:#f8fafc;">
        <th style="padding:6px 8px; font-size:11px; color:#334155; text-align:left; border:1px solid #e2e8f0;" width="28%">M&eacute;trica</th>
        <th style="padding:6px 8px; font-size:11px; color:#334155; text-align:center; border:1px solid #e2e8f0;" width="18%">Periodo Actual</th>
        <th style="padding:6px 8px; font-size:11px; color:#334155; text-align:center; border:1px solid #e2e8f0;" width="18%">Mismo Mes {{ $prevYearLabel }}</th>
        <th style="padding:6px 8px; font-size:11px; color:#334155; text-align:center; border:1px solid #e2e8f0;" width="12%">Var.</th>
        <th style="padding:6px 8px; font-size:11px; color:#334155; text-align:center; border:1px solid #e2e8f0;" width="12%">Acum. {{ date('Y') }}</th>
        <th style="padding:6px 8px; font-size:11px; color:#334155; text-align:center; border:1px solid #e2e8f0;" width="12%">Acum. {{ $prevYearLabel }}</th>
    </tr>
    @php
        $compRows = [
            ['Total Tarjetas', $total, $prevTotal, $deltaTotal, $ytdTotal, $prevYtdTotal],
            ['Negativas', $neg, $prevNeg, $deltaNeg, $ytdNeg, $prevYtdNeg],
            ['Positivas', $pos, $prevPos, $deltaPos, $ytdPos, $prevYtdPos],
        ];
    @endphp
    @foreach($compRows as $idx => $row)
    @php [$arrowSym, $arrowColor, $arrowText] = $arrow($row[3]); @endphp
    <tr style="background-color:{{ $idx % 2 === 1 ? '#f8fafc' : '#ffffff' }};">
        <td style="padding:5px 8px; font-size:11px; color:#1e293b; font-weight:bold; border:1px solid #e2e8f0;">{{ $row[0] }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#1e293b; text-align:center; border:1px solid #e2e8f0; font-weight:bold;">{{ number_format($row[1]) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#64748b; text-align:center; border:1px solid #e2e8f0;">{{ number_format($row[2]) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:{{ $arrowColor }}; text-align:center; border:1px solid #e2e8f0; font-weight:bold;">{{ $arrowSym }} {{ $arrowText }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#1e293b; text-align:center; border:1px solid #e2e8f0; font-weight:bold;">{{ number_format($row[4]) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#64748b; text-align:center; border:1px solid #e2e8f0;">{{ number_format($row[5]) }}</td>
    </tr>
    @endforeach
    </table>

    {{-- KPI deltas visuales --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">
    <tr>
        @php
            $pctChangeNeg = $prevNeg > 0 ? round((($neg - $prevNeg) / $prevNeg) * 100, 1) : ($neg > 0 ? 100 : 0);
            $pctChangeTotal = $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100, 1) : ($total > 0 ? 100 : 0);
            $pctChangeYtd = $prevYtdTotal > 0 ? round((($ytdTotal - $prevYtdTotal) / $prevYtdTotal) * 100, 1) : ($ytdTotal > 0 ? 100 : 0);
        @endphp
        <td width="33%" style="padding:4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; border-radius:6px; border:1px solid #e2e8f0;">
            <tr><td style="padding:10px 6px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Var. Total Mes</span><br/>
                <span style="font-size:22px; font-weight:bold; color:{{ $pctChangeTotal >= 0 ? '#ef4444' : '#16a34a' }};">{{ $pctChangeTotal >= 0 ? '+' : '' }}{{ $pctChangeTotal }}%</span>
            </td></tr>
            </table>
        </td>
        <td width="33%" style="padding:4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; border-radius:6px; border:1px solid #e2e8f0;">
            <tr><td style="padding:10px 6px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Var. Negativas</span><br/>
                <span style="font-size:22px; font-weight:bold; color:{{ $pctChangeNeg >= 0 ? '#ef4444' : '#16a34a' }};">{{ $pctChangeNeg >= 0 ? '+' : '' }}{{ $pctChangeNeg }}%</span>
            </td></tr>
            </table>
        </td>
        <td width="33%" style="padding:4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; border-radius:6px; border:1px solid #e2e8f0;">
            <tr><td style="padding:10px 6px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Var. Acum. A&ntilde;o</span><br/>
                <span style="font-size:22px; font-weight:bold; color:{{ $pctChangeYtd >= 0 ? '#ef4444' : '#16a34a' }};">{{ $pctChangeYtd >= 0 ? '+' : '' }}{{ $pctChangeYtd }}%</span>
            </td></tr>
            </table>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- ═══════════ ACUMULADO AÑO — TOP NEGATIVOS YTD ═══════════ --}}
@if(!empty($ytd['topNeg']))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#991b1b; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:14px; font-weight:bold;">&#128680; Top Trabajadores Negativos &mdash; Acumulado {{ date('Y') }}</span>
    </td></tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px; border:1px solid #e2e8f0; border-collapse:collapse;">
    <tr style="background-color:#fef2f2;">
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">#</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">Trabajador</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:center; border:1px solid #e2e8f0;">Tarjetas Neg.</th>
    </tr>
    @foreach(array_slice($ytd['topNeg'], 0, 10, true) as $nombre => $cnt)
    <tr style="background-color:{{ $loop->index % 2 === 1 ? '#fef2f2' : '#fff' }};">
        <td style="padding:4px 8px; font-size:11px; color:#991b1b; border:1px solid #e2e8f0;">{{ $loop->iteration }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#1e293b; border:1px solid #e2e8f0;">{{ $nombre }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#991b1b; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ $cnt }}</td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ ACUMULADO AÑO — TOP TIPOS FALTA NEGATIVA YTD ═══════════ --}}
@if(!empty($ytd['negPorTipo']))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:14px; font-weight:bold;">&#128269; Tipos de Falta Negativa &mdash; Acumulado {{ date('Y') }}</span>
    </td></tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px; border:1px solid #e2e8f0; border-collapse:collapse;">
    <tr style="background-color:#fef2f2;">
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">#</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">Tipo de Falta</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:center; border:1px solid #e2e8f0;">Cantidad</th>
    </tr>
    @foreach(array_slice($ytd['negPorTipo'], 0, 10, true) as $tipo => $cnt)
    <tr style="background-color:{{ $loop->index % 2 === 1 ? '#fef2f2' : '#fff' }};">
        <td style="padding:4px 8px; font-size:11px; color:#991b1b; border:1px solid #e2e8f0;">{{ $loop->iteration }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#1e293b; border:1px solid #e2e8f0;">{{ $tipo }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#991b1b; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ $cnt }}</td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TENDENCIA MENSUAL ACUMULADO AÑO ═══════════ --}}
@if(!empty($ytd['byMonth']))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:14px; font-weight:bold;">&#128200; Tendencia Mensual &mdash; {{ date('Y') }} vs {{ $prevYearLabel }}</span>
    </td></tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px; border:1px solid #e2e8f0; border-collapse:collapse;">
    <tr style="background-color:#f8fafc;">
        <th style="padding:5px 8px; font-size:10px; color:#334155; text-align:left; border:1px solid #e2e8f0;">Mes</th>
        <th style="padding:5px 8px; font-size:10px; color:#334155; text-align:center; border:1px solid #e2e8f0;">{{ date('Y') }} Total</th>
        <th style="padding:5px 8px; font-size:10px; color:#ef4444; text-align:center; border:1px solid #e2e8f0;">{{ date('Y') }} Neg</th>
        <th style="padding:5px 8px; font-size:10px; color:#22c55e; text-align:center; border:1px solid #e2e8f0;">{{ date('Y') }} Pos</th>
        <th style="padding:5px 8px; font-size:10px; color:#334155; text-align:center; border:1px solid #e2e8f0;">{{ $prevYearLabel }} Total</th>
        <th style="padding:5px 8px; font-size:10px; color:#ef4444; text-align:center; border:1px solid #e2e8f0;">{{ $prevYearLabel }} Neg</th>
        <th style="padding:5px 8px; font-size:10px; color:#22c55e; text-align:center; border:1px solid #e2e8f0;">{{ $prevYearLabel }} Pos</th>
    </tr>
    @php
        $meses = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
        $prevByMonth = $prev['byMonth'] ?? [];
        $prevByMonthNeg = $prev['byMonthNeg'] ?? [];
        $prevByMonthPos = $prev['byMonthPos'] ?? [];
        $currYear = date('Y');
    @endphp
    @foreach($meses as $mNum => $mName)
    @php
        $curKey = $currYear . '-' . $mNum;
        $prvKey = $prevYearLabel . '-' . $mNum;
        $cT = $ytd['byMonth'][$curKey] ?? 0;
        $cN = $ytd['byMonthNeg'][$curKey] ?? 0;
        $cP = $ytd['byMonthPos'][$curKey] ?? 0;
    @endphp
    @if($cT > 0 || isset($prevByMonth[$prvKey]))
    <tr style="background-color:{{ $loop->index % 2 === 1 ? '#f8fafc' : '#ffffff' }};">
        <td style="padding:4px 8px; font-size:11px; color:#1e293b; font-weight:bold; border:1px solid #e2e8f0;">{{ $mName }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#1e293b; text-align:center; border:1px solid #e2e8f0; font-weight:bold;">{{ $cT > 0 ? number_format($cT) : '-' }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#ef4444; text-align:center; border:1px solid #e2e8f0;">{{ $cN > 0 ? number_format($cN) : '-' }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#22c55e; text-align:center; border:1px solid #e2e8f0;">{{ $cP > 0 ? number_format($cP) : '-' }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#64748b; text-align:center; border:1px solid #e2e8f0;">{{ ($prevByMonth[$prvKey] ?? 0) > 0 ? number_format($prevByMonth[$prvKey]) : '-' }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#ef4444; text-align:center; border:1px solid #e2e8f0;">{{ ($prevByMonthNeg[$prvKey] ?? 0) > 0 ? number_format($prevByMonthNeg[$prvKey]) : '-' }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#22c55e; text-align:center; border:1px solid #e2e8f0;">{{ ($prevByMonthPos[$prvKey] ?? 0) > 0 ? number_format($prevByMonthPos[$prvKey]) : '-' }}</td>
    </tr>
    @endif
    @endforeach
    {{-- Fila totales --}}
    <tr style="background-color:#1B5E20;">
        <td style="padding:5px 8px; font-size:11px; color:#ffffff; font-weight:bold; border:1px solid #e2e8f0;">TOTAL</td>
        <td style="padding:5px 8px; font-size:11px; color:#ffffff; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ number_format($ytdTotal) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#fca5a5; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ number_format($ytdNeg) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#86efac; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ number_format($ytdPos) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#ffffff; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ number_format($prev['total'] ?? 0) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#fca5a5; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ number_format($prev['neg'] ?? 0) }}</td>
        <td style="padding:5px 8px; font-size:11px; color:#86efac; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ number_format($prev['pos'] ?? 0) }}</td>
    </tr>
    </table>
</td>
</tr>
@endif
@endif

{{-- ═══════════ TARJETAS STOP CCU POR CENTRO (barras apiladas) ═══════════ --}}
@if(!empty($centrosData))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Tarjetas STOP CCU por Centro</span>
    </td></tr>
    </table>
    @php $maxC = max($centrosData); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @foreach(array_slice($centrosData, 0, 10, true) as $c => $cnt)
        @php
            $cNeg = $centrosNeg[$c] ?? 0;
            $cPos = $centrosPos[$c] ?? 0;
            $pNeg = $maxC > 0 ? round(($cNeg / $maxC) * 100) : 0;
            $pPos = $maxC > 0 ? round(($cPos / $maxC) * 100) : 0;
            $pRest = 100 - $pNeg - $pPos;
        @endphp
        <tr>
            <td style="padding:3px 6px; width:120px; text-align:right; color:#334155; font-size:11px; white-space:nowrap;">{{ $c }}</td>
            <td style="padding:3px 4px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    @if($pNeg > 0)<td width="{{ $pNeg }}%" style="background-color:#991b1b; height:16px;">&nbsp;</td>@endif
                    @if($pPos > 0)<td width="{{ $pPos }}%" style="background-color:#22c55e; height:16px;">&nbsp;</td>@endif
                    @if($pRest > 0)<td width="{{ $pRest }}%" style="height:16px;">&nbsp;</td>@endif
                </tr></table>
            </td>
            <td style="padding:3px 6px; width:60px; text-align:right; font-size:10px;">
                <span style="color:#991b1b; font-weight:bold;">{{ $cNeg }}</span>
                <span style="color:#22c55e; font-weight:bold;">{{ $cPos }}</span>
            </td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ AREA O PROCESO DONDE OCURRIO (barras apiladas) ═══════════ --}}
@if(!empty($areasData))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">&Aacute;rea o Proceso donde Ocurri&oacute;</span>
    </td></tr>
    </table>
    @php $maxA = max($areasData); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @foreach(array_slice($areasData, 0, 10, true) as $ar => $cnt)
        @php
            $aNeg = $areasNeg[$ar] ?? 0;
            $aPos = $areasPos[$ar] ?? 0;
            $pNeg = $maxA > 0 ? round(($aNeg / $maxA) * 100) : 0;
            $pPos = $maxA > 0 ? round(($aPos / $maxA) * 100) : 0;
            $pRest = 100 - $pNeg - $pPos;
        @endphp
        <tr>
            <td style="padding:3px 6px; width:120px; text-align:right; color:#334155; font-size:11px; white-space:nowrap;">{{ $ar }}</td>
            <td style="padding:3px 4px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    @if($pNeg > 0)<td width="{{ $pNeg }}%" style="background-color:#991b1b; height:16px;">&nbsp;</td>@endif
                    @if($pPos > 0)<td width="{{ $pPos }}%" style="background-color:#22c55e; height:16px;">&nbsp;</td>@endif
                    @if($pRest > 0)<td width="{{ $pRest }}%" style="height:16px;">&nbsp;</td>@endif
                </tr></table>
            </td>
            <td style="padding:3px 6px; width:60px; text-align:right; font-size:10px;">
                <span style="color:#991b1b; font-weight:bold;">{{ $aNeg }}</span>
                <span style="color:#22c55e; font-weight:bold;">{{ $aPos }}</span>
            </td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TARJETAS POR MES (tendencia con neg/pos) ═══════════ --}}
@if(!empty($byMonth))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Tarjetas STOP CCU por Mes</span>
    </td></tr>
    </table>
    @php
        $maxMonth = max($byMonth);
        $lastMonths = array_slice($byMonth, -12, 12, true);
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:8px;">
    @foreach($lastMonths as $mk => $cnt)
    @php
        $mNeg = $byMonthNeg[$mk] ?? 0;
        $mPos = $byMonthPos[$mk] ?? 0;
        $pNeg = $maxMonth > 0 ? round(($mNeg / $maxMonth) * 100) : 0;
        $pPos = $maxMonth > 0 ? round(($mPos / $maxMonth) * 100) : 0;
        $pRest = max(0, 100 - $pNeg - $pPos);
        try { $mLabel = \Carbon\Carbon::createFromFormat('Y-m', $mk)->translatedFormat('M Y'); } catch(\Exception $e) { $mLabel = $mk; }
    @endphp
    <tr>
        <td style="padding:2px 6px; width:55px; text-align:right; color:#64748b; font-size:10px; white-space:nowrap;">{{ $mLabel }}</td>
        <td style="padding:2px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                @if($pNeg > 0)<td width="{{ $pNeg }}%" style="background-color:#991b1b; height:14px;">&nbsp;</td>@endif
                @if($pPos > 0)<td width="{{ $pPos }}%" style="background-color:#22c55e; height:14px;">&nbsp;</td>@endif
                @if($pRest > 0)<td width="{{ $pRest }}%" style="height:14px;">&nbsp;</td>@endif
            </tr></table>
        </td>
        <td style="padding:2px 4px; width:65px; text-align:right; font-size:10px;">
            <span style="color:#991b1b; font-weight:bold;">{{ $mNeg }}</span> /
            <span style="color:#22c55e; font-weight:bold;">{{ $mPos }}</span>
        </td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ PRINCIPAL RAZON (neg por tipo con barra) ═══════════ --}}
@if(!empty($negPorTipo))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Principal Raz&oacute;n &mdash; Tarjetas Negativas</span>
    </td></tr>
    </table>
    @php $maxNeg = max($negPorTipo); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @foreach(array_slice($negPorTipo, 0, 10, true) as $tipo => $cnt)
        @php
            $tPos = $posPorTipo[$tipo] ?? 0;
            $tTotal = $cnt + $tPos;
            $maxBar = max($maxNeg, 1);
            $pN = round(($cnt / $maxBar) * 100);
            $pP = round(($tPos / $maxBar) * 100);
            $pR = max(0, 100 - $pN - $pP);
        @endphp
        <tr>
            <td style="padding:3px 6px; width:140px; text-align:right; color:#334155; font-size:11px;">{{ $tipo }}</td>
            <td style="padding:3px 4px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    @if($pN > 0)<td width="{{ $pN }}%" style="background-color:#991b1b; height:16px;">&nbsp;</td>@endif
                    @if($pP > 0)<td width="{{ $pP }}%" style="background-color:#22c55e; height:16px;">&nbsp;</td>@endif
                    @if($pR > 0)<td width="{{ $pR }}%" style="height:16px;">&nbsp;</td>@endif
                </tr></table>
            </td>
            <td style="padding:3px 6px; width:30px; text-align:center; font-weight:bold; color:#991b1b; font-size:11px;">{{ $cnt }}</td>
        </tr>
        @endforeach
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px;">
    <tr>
        <td style="font-size:10px; color:#991b1b; text-align:center;">&#9632; Negativa &nbsp;&nbsp; <span style="color:#22c55e;">&#9632; Positiva</span></td>
    </tr>
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TARJETAS EMITIDAS POR EMPRESA (barras apiladas) ═══════════ --}}
@if(!empty($empresas))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Tarjetas Emitidas por Empresa</span>
    </td></tr>
    </table>
    @php $maxE = max($empresas); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @foreach($empresas as $emp => $cnt)
        @php
            $eNeg = $empresasNeg[$emp] ?? 0;
            $ePos = $empresasPos[$emp] ?? 0;
            $pNeg = $maxE > 0 ? round(($eNeg / $maxE) * 100) : 0;
            $pPos = $maxE > 0 ? round(($ePos / $maxE) * 100) : 0;
            $pRest = max(0, 100 - $pNeg - $pPos);
        @endphp
        <tr>
            <td style="padding:3px 6px; width:100px; text-align:right; color:#334155; font-size:11px;">{{ $emp }}</td>
            <td style="padding:3px 4px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    @if($pNeg > 0)<td width="{{ $pNeg }}%" style="background-color:#991b1b; height:16px;">&nbsp;</td>@endif
                    @if($pPos > 0)<td width="{{ $pPos }}%" style="background-color:#22c55e; height:16px;">&nbsp;</td>@endif
                    @if($pRest > 0)<td width="{{ $pRest }}%" style="height:16px;">&nbsp;</td>@endif
                </tr></table>
            </td>
            <td style="padding:3px 6px; width:60px; text-align:right; font-size:10px;">
                <span style="color:#991b1b; font-weight:bold;">{{ $eNeg }}</span>
                <span style="color:#22c55e; font-weight:bold;">{{ $ePos }}</span>
            </td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TOTAL DE TARJETAS POR OBSERVADOR (con neg/pos) ═══════════ --}}
@if(!empty($topObs))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Total de Tarjetas por Observador</span>
    </td></tr>
    </table>
    @php $maxO = max($topObs); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        <tr style="background-color:#f1f5f9;">
            <td style="padding:4px 6px; font-weight:bold; color:#64748b; font-size:10px;">#</td>
            <td style="padding:4px 6px; font-weight:bold; color:#64748b; font-size:10px;">Observador</td>
            <td style="padding:4px 6px; font-weight:bold; color:#64748b; font-size:10px; width:200px;"></td>
            <td style="padding:4px 6px; font-weight:bold; color:#64748b; text-align:center; font-size:10px; width:35px;">Total</td>
            <td style="padding:4px 6px; font-weight:bold; color:#991b1b; text-align:center; font-size:10px; width:30px;">Neg</td>
            <td style="padding:4px 6px; font-weight:bold; color:#22c55e; text-align:center; font-size:10px; width:30px;">Pos</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topObs, 0, 15, true) as $nombre => $cnt)
        @php
            $oNeg = $obsNeg[$nombre] ?? 0;
            $oPos = $obsPos[$nombre] ?? 0;
            $pNeg = $maxO > 0 ? round(($oNeg / $maxO) * 100) : 0;
            $pPos = $maxO > 0 ? round(($oPos / $maxO) * 100) : 0;
            $pRest = max(0, 100 - $pNeg - $pPos);
        @endphp
        <tr>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-size:10px;">{{ $rank }}</td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:11px; text-transform:capitalize;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    @if($pNeg > 0)<td width="{{ $pNeg }}%" style="background-color:#991b1b; height:10px;">&nbsp;</td>@endif
                    @if($pPos > 0)<td width="{{ $pPos }}%" style="background-color:#22c55e; height:10px;">&nbsp;</td>@endif
                    @if($pRest > 0)<td width="{{ $pRest }}%" style="height:10px;">&nbsp;</td>@endif
                </tr></table>
            </td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#334155; font-size:11px;">{{ $cnt }}</td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#991b1b; font-size:11px;">{{ $oNeg }}</td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#22c55e; font-size:11px;">{{ $oPos }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TRABAJADOR CON MAYOR TARJETAS NEGATIVAS ═══════════ --}}
@if(!empty($topNeg))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Trabajador con Mayor Tarjetas STOP CCU Negativas</span>
    </td></tr>
    </table>
    @php $maxTN = max($topNeg); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @php $rank = 1; @endphp
        @foreach(array_slice($topNeg, 0, 15, true) as $nombre => $cnt)
        @php $pctBar = $maxTN > 0 ? round(($cnt / $maxTN) * 100) : 0; @endphp
        <tr>
            <td style="padding:2px 6px; width:20px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-size:10px;">{{ $rank }}</td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize; font-size:11px;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; width:200px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#991b1b; height:12px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:12px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#991b1b; width:30px;">{{ $cnt }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TRABAJADORES CON TARJETAS POSITIVAS ═══════════ --}}
@if(!empty($topPos))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#166534; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Trabajadores con Tarjetas STOP CCU Positivas</span>
    </td></tr>
    </table>
    @php $maxTP = max($topPos); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @php $rank = 1; @endphp
        @foreach(array_slice($topPos, 0, 15, true) as $nombre => $cnt)
        @php $pctBar = $maxTP > 0 ? round(($cnt / $maxTP) * 100) : 0; @endphp
        <tr>
            <td style="padding:2px 6px; width:20px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-size:10px;">{{ $rank }}</td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize; font-size:11px;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; width:200px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#22c55e; height:12px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:12px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:2px 6px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#22c55e; width:30px;">{{ $cnt }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ TIPO FELICITACION (Positivas) ═══════════ --}}
@if(!empty($posPorTipo))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#166534; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Tipos de Felicitaci&oacute;n &mdash; Tarjetas Positivas</span>
    </td></tr>
    </table>
    @php $maxPos = max($posPorTipo); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @foreach(array_slice($posPorTipo, 0, 10, true) as $tipo => $cnt)
        @php $pctBar = $maxPos > 0 ? round(($cnt / $maxPos) * 100) : 0; @endphp
        <tr>
            <td style="padding:3px 6px; color:#334155; font-size:11px;">{{ $tipo }}</td>
            <td style="padding:3px 4px; width:200px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#22c55e; height:14px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:14px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:3px 6px; text-align:center; font-weight:bold; color:#22c55e; width:30px;">{{ $cnt }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ EMPRESA OBSERVADOR (quien pasó la tarjeta) ═══════════ --}}
@if(!empty($empresasObs))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#1B5E20; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Persona que Pas&oacute; la Tarjeta &mdash; por Empresa</span>
    </td></tr>
    </table>
    @php $maxEO = max($empresasObs); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; margin-top:8px;">
        @foreach($empresasObs as $emp => $cnt)
        @php $pctBar = $maxEO > 0 ? round(($cnt / $maxEO) * 100) : 0; @endphp
        <tr>
            <td style="padding:3px 6px; color:#334155; font-size:11px;">{{ $emp }}</td>
            <td style="padding:3px 4px; width:200px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#3b82f6; height:14px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:14px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:3px 6px; text-align:center; font-weight:bold; color:#3b82f6; width:30px;">{{ $cnt }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ═══════════ INTERNO vs EXTERNO ═══════════ --}}
@if(!empty($intExt))
<tr>
<td style="padding:12px 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
    <tr>
        @foreach($intExt as $tipo => $cnt)
        <td style="text-align:center; padding:12px 8px; {{ !$loop->last ? 'border-right:1px solid #e2e8f0;' : '' }}">
            <span style="font-size:22px; font-weight:bold; color:#334155;">{{ number_format($cnt) }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase;">{{ $tipo }}</span>
            <span style="font-size:10px; color:#94a3b8;">({{ $total > 0 ? round(($cnt/$total)*100,1) : 0 }}%)</span>
        </td>
        @endforeach
    </tr>
    </table>
</td>
</tr>
@endif

{{-- ═══════════ ANTIGUEDAD + CARGO + TURNO (compacto 3 columnas) ═══════════ --}}
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        @if(!empty($antiguedades))
        <td width="32%" valign="top">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="background-color:#1B5E20; padding:6px 8px; text-align:center; border-radius:4px;">
                <span style="color:#ffffff; font-size:11px; font-weight:bold;">Antig&uuml;edad</span>
            </td></tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:4px;">
                @foreach(array_slice($antiguedades, 0, 8, true) as $antig => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $antig }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#1B5E20; width:30px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($cargos))
        <td width="32%" valign="top">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="background-color:#1B5E20; padding:6px 8px; text-align:center; border-radius:4px;">
                <span style="color:#ffffff; font-size:11px; font-weight:bold;">Cargo</span>
            </td></tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:4px;">
                @foreach(array_slice($cargos, 0, 8, true) as $cargo => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $cargo }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#1B5E20; width:30px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($turnos))
        <td width="32%" valign="top">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="background-color:#1B5E20; padding:6px 8px; text-align:center; border-radius:4px;">
                <span style="color:#ffffff; font-size:11px; font-weight:bold;">Turno</span>
            </td></tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:4px;">
                @foreach($turnos as $turno => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $turno }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#1B5E20; width:30px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        @endif
    </tr>
    </table>
</td>
</tr>

{{-- ═══════════ DETALLE EVALUACIONES NEGATIVAS ═══════════ --}}
@php
    $ed = $evalDetail ?? [];
    $edWorkers = $ed['workers'] ?? [];
    $edItemRank = $ed['itemRanking'] ?? [];
    $hasEval = !empty($edWorkers) || !empty($edItemRank);
@endphp
@if($hasEval)

{{-- Ranking ítems más incumplidos --}}
@if(!empty($edItemRank))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#991b1b; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:14px; font-weight:bold;">&#128269; &Iacute;tems con Mayor Incumplimiento</span>
    </td></tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px; border:1px solid #e2e8f0; border-collapse:collapse;">
    <tr style="background-color:#fef2f2;">
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">#</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">Categor&iacute;a</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:left; border:1px solid #e2e8f0;">&Iacute;tem Evaluado</th>
        <th style="padding:5px 8px; font-size:10px; color:#991b1b; text-align:center; border:1px solid #e2e8f0;">No Cumple</th>
    </tr>
    @foreach(array_slice($edItemRank, 0, 15, true) as $itemKey => $cnt)
    @php [$itemCat, $itemQ] = explode(' | ', $itemKey, 2); @endphp
    <tr style="background-color:{{ $loop->index % 2 === 1 ? '#fef2f2' : '#fff' }};">
        <td style="padding:4px 8px; font-size:11px; color:#991b1b; border:1px solid #e2e8f0;">{{ $loop->iteration }}</td>
        <td style="padding:4px 8px; font-size:10px; color:#64748b; border:1px solid #e2e8f0;">{{ $itemCat }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#1e293b; border:1px solid #e2e8f0;">{{ $itemQ }}</td>
        <td style="padding:4px 8px; font-size:11px; color:#991b1b; text-align:center; font-weight:bold; border:1px solid #e2e8f0;">{{ $cnt }}</td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- Detalle por trabajador (máx 20 en email) --}}
@if(!empty($edWorkers))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:14px; font-weight:bold;">&#128203; Detalle Evaluaciones Negativas por Trabajador</span>
        <br/><span style="color:#fca5a5; font-size:10px;">({{ count($edWorkers) }} evaluaciones &mdash; mostrando m&aacute;x. 20)</span>
    </td></tr>
    </table>
    @foreach(array_slice($edWorkers, 0, 20) as $w)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:6px; border:1px solid #e2e8f0; border-collapse:collapse;">
    <tr style="background-color:#fef2f2;">
        <td colspan="2" style="padding:6px 8px; border:1px solid #e2e8f0;">
            <span style="font-size:12px; font-weight:bold; color:#991b1b;">{{ $w['trabajador'] }}</span>
            <span style="font-size:10px; color:#64748b;"> &mdash; {{ $w['centro'] }} &mdash; {{ $w['area'] }} &mdash; {{ $w['cargo'] }}</span>
            <br/><span style="font-size:10px; color:#64748b;">{{ $w['empresa'] }} | Antig.: {{ $w['antiguedad'] }} | Turno: {{ $w['turno'] }} | Tipo: {{ $w['tipoObs'] }} | Fecha: {{ $w['fecha'] }}</span>
        </td>
    </tr>
    @if(!empty($w['noCumple']))
    <tr>
        <td width="70" style="padding:4px 8px; font-size:10px; color:#991b1b; font-weight:bold; border:1px solid #e2e8f0; vertical-align:top;">NO CUMPLE<br/>({{ $w['totalNC'] }})</td>
        <td style="padding:4px 8px; font-size:10px; color:#991b1b; border:1px solid #e2e8f0;">
            @foreach($w['noCumple'] as $nc)
            &bull; {{ $nc }}<br/>
            @endforeach
        </td>
    </tr>
    @endif
    @if(!empty($w['cumple']))
    <tr>
        <td width="70" style="padding:4px 8px; font-size:10px; color:#16a34a; font-weight:bold; border:1px solid #e2e8f0; vertical-align:top;">CUMPLE<br/>({{ $w['totalC'] }})</td>
        <td style="padding:4px 8px; font-size:10px; color:#16a34a; border:1px solid #e2e8f0;">
            @foreach($w['cumple'] as $c)
            &bull; {{ $c }}<br/>
            @endforeach
        </td>
    </tr>
    @endif
    </table>
    @endforeach
</td>
</tr>
@endif
@endif

{{-- ═══════════ CONCLUSION AUTO-GENERADA ═══════════ --}}
<tr>
<td style="padding:16px 24px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#0f172a; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Conclusi&oacute;n</span>
    </td></tr>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
    <tr><td style="padding:14px 16px; font-size:12px; color:#334155; line-height:1.6;">
        Durante el per&iacute;odo <strong>{{ $mesLabel ?? $periodo }}</strong> se cursaron
        <strong>{{ number_format($total) }} tarjetas STOP CCU</strong>, de las cuales
        <strong style="color:#22c55e;">{{ number_format($pos) }}</strong> son positivas y
        <strong style="color:#991b1b;">{{ number_format($neg) }}</strong> son negativas,
        lo que representa una tasa de observaciones positivas del <strong>{{ $pctPos }}%</strong>.
        @if($pctPos < 60)
        <br/>Se recomienda reforzar las observaciones positivas para alcanzar la meta del 60%.
        @endif
        @if($topFaltaNeg)
        <br/>La principal desviaci&oacute;n registrada corresponde a <strong>&laquo;{{ $topFaltaNeg }}&raquo;</strong>
        con {{ $topFaltaNegCnt }} tarjeta{{ $topFaltaNegCnt > 1 ? 's' : '' }} negativa{{ $topFaltaNegCnt > 1 ? 's' : '' }}.
        Se recomienda reforzar las desviaciones asociadas a esta &aacute;rea.
        @endif
        @if($topTrabNeg && $topTrabNegCnt > 1)
        <br/>El trabajador con mayor cantidad de tarjetas negativas es
        <strong>{{ mb_convert_case(mb_strtolower($topTrabNeg), MB_CASE_TITLE) }}</strong> ({{ $topTrabNegCnt }} neg.).
        @endif
        @if($centrosCount > 0)
        <br/>Las observaciones se distribuyeron en <strong>{{ $centrosCount }} centro{{ $centrosCount > 1 ? 's' : '' }} de trabajo</strong>.
        @endif
    </td></tr>
    </table>
    <p style="margin:8px 0 0; font-size:10px; color:#94a3b8; font-style:italic;">
        Nota: La informaci&oacute;n obtenida de tarjetas STOP CCU se encuentra en la base de datos.
    </p>
</td>
</tr>

{{-- ═══════════ FOOTER ═══════════ --}}
<tr>
<td style="padding:14px 32px; background-color:#f8fafc; text-align:center; border-top:1px solid #e2e8f0; border-radius:0 0 4px 4px;">
    <p style="margin:0 0 4px; font-size:11px; color:#94a3b8;">
        Reporte generado autom&aacute;ticamente por <strong>SAEP</strong> &mdash; {{ $mesLabel ?? $periodo }}
    </p>
    <p style="margin:0; font-size:10px; color:#cbd5e1;">
        {{ config('app.url') }} &middot; Prevenci&oacute;n de Riesgos &middot; Observaciones de Seguridad
    </p>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
