<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Reporte Tarjeta STOP</title>
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
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
<tr><td align="center" style="padding:24px 8px;">
<table role="presentation" width="700" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; max-width:700px; border-radius:4px;">

{{-- ═══════════ HEADER ═══════════ --}}
<tr>
<td style="background-color:#7f1d1d; padding:24px 32px; text-align:center; border-radius:4px 4px 0 0;">
    <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:bold; letter-spacing:0.5px;">
        AUDITOR&Iacute;AS STOP &mdash; Reporte {{ $frecuencia ?? 'Semanal' }}
    </h1>
    <p style="margin:6px 0 0; color:#fca5a5; font-size:13px;">
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
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#7f1d1d; border-radius:6px;">
            <tr><td style="padding:14px 8px; text-align:center;">
                <span style="font-size:11px; color:#fca5a5; text-transform:uppercase; letter-spacing:1px;">Tarjetas STOP</span><br/>
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

{{-- ═══════════ TARJETAS STOP POR CENTRO (barras apiladas) ═══════════ --}}
@if(!empty($centrosData))
<tr>
<td style="padding:12px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Tarjetas STOP por Centro</span>
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Tarjetas STOP por Mes</span>
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Trabajador con Mayor Tarjetas STOP Negativas</span>
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
        <span style="color:#ffffff; font-size:13px; font-weight:bold;">Trabajadores con Tarjetas STOP Positivas</span>
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
    <tr><td style="background-color:#7f1d1d; padding:8px 12px; text-align:center; border-radius:4px;">
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
            <tr><td style="background-color:#7f1d1d; padding:6px 8px; text-align:center; border-radius:4px;">
                <span style="color:#ffffff; font-size:11px; font-weight:bold;">Antig&uuml;edad</span>
            </td></tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:4px;">
                @foreach(array_slice($antiguedades, 0, 8, true) as $antig => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $antig }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#7f1d1d; width:30px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($cargos))
        <td width="32%" valign="top">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="background-color:#7f1d1d; padding:6px 8px; text-align:center; border-radius:4px;">
                <span style="color:#ffffff; font-size:11px; font-weight:bold;">Cargo</span>
            </td></tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:4px;">
                @foreach(array_slice($cargos, 0, 8, true) as $cargo => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $cargo }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#7f1d1d; width:30px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($turnos))
        <td width="32%" valign="top">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="background-color:#7f1d1d; padding:6px 8px; text-align:center; border-radius:4px;">
                <span style="color:#ffffff; font-size:11px; font-weight:bold;">Turno</span>
            </td></tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px; margin-top:4px;">
                @foreach($turnos as $turno => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $turno }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#7f1d1d; width:30px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        @endif
    </tr>
    </table>
</td>
</tr>

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
        <strong>{{ number_format($total) }} tarjetas STOP</strong>, de las cuales
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
        Nota: La informaci&oacute;n obtenida de tarjetas STOP se encuentra en la base de datos.
    </p>
</td>
</tr>

{{-- ═══════════ CTA BUTTON ═══════════ --}}
<tr>
<td style="padding:20px 32px; text-align:center;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
    <tr>
        <td style="background-color:#7f1d1d; border-radius:8px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ url('/stop-dashboard') }}" style="height:44px;v-text-anchor:middle;width:260px;" arcsize="18%" fillcolor="#7f1d1d" stroke="f">
            <center style="color:#ffffff;font-family:Arial;font-size:14px;font-weight:bold;">Ver Dashboard Completo</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ url('/stop-dashboard') }}" style="display:inline-block; padding:12px 40px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; line-height:20px;">
                Ver Dashboard Completo
            </a>
            <!--<![endif]-->
        </td>
    </tr>
    </table>
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
