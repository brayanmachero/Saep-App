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
    $centrosData = $a['centros'] ?? [];
    $areasData = $a['areas'] ?? [];
    $tiposObs = $a['tiposObservacion'] ?? [];
    $intExt = $a['internoExterno'] ?? [];
    $empresas = $a['empresas'] ?? [];
    $empresasObs = $a['empresasObservador'] ?? [];
    $turnos = $a['turnos'] ?? [];
    $antiguedades = $a['antiguedades'] ?? [];
    $cargos = $a['cargos'] ?? [];
    $topObs = $a['topObservadores'] ?? [];
    $negPorTipo = $a['negPorTipo'] ?? [];
    $posPorTipo = $a['posPorTipo'] ?? [];
    $topNeg = $a['topNegTrabajadores'] ?? [];
    $topPos = $a['topPosTrabajadores'] ?? [];
    $byMonth = $a['byMonth'] ?? [];
    $centrosCount = count($centrosData);
    $obsCount = count($topObs);
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
<tr><td align="center" style="padding:24px 8px;">
<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; max-width:680px; border-radius:4px;">

{{-- HEADER --}}
<tr>
<td style="background-color:#0f172a; padding:28px 32px; text-align:center; border-radius:4px 4px 0 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="text-align:center;">
            <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:bold; letter-spacing:0.3px;">
                &#9995; Reporte Tarjeta STOP
            </h1>
            <p style="margin:6px 0 0; color:#93c5fd; font-size:13px; letter-spacing:0.2px;">
                Observaciones de Seguridad &mdash; {{ $mesLabel ?? $periodo }}
            </p>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- INDICADOR PRINCIPAL --}}
<tr>
<td style="padding:20px 24px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:2px solid {{ $pctPos >= 60 ? '#22c55e' : ($pctPos >= 40 ? '#f59e0b' : '#ef4444') }}; border-radius:8px;">
    <tr>
        <td style="padding:16px 20px; text-align:center; background-color:{{ $pctPos >= 60 ? '#f0fdf4' : ($pctPos >= 40 ? '#fffbeb' : '#fef2f2') }};">
            <span style="font-size:36px; font-weight:bold; color:{{ $pctPos >= 60 ? '#16a34a' : ($pctPos >= 40 ? '#d97706' : '#dc2626') }};">{{ $pctPos }}%</span>
            <br/>
            <span style="font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:1px;">Tasa de Observaciones Positivas</span>
            <br/>
            <span style="font-size:11px; color:#94a3b8;">Meta recomendada: &ge;60%</span>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- KPIs GRID --}}
<tr>
<td style="padding:16px 24px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="20%" style="text-align:center; padding:12px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:28px; font-weight:bold; color:#3b82f6;">{{ number_format($total) }}</span><br/>
            <span style="font-size:9px; color:#64748b; text-transform:uppercase; letter-spacing:0.8px;">Total Obs.</span>
        </td>
        <td width="20%" style="text-align:center; padding:12px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:28px; font-weight:bold; color:#22c55e;">{{ number_format($pos) }}</span><br/>
            <span style="font-size:9px; color:#64748b; text-transform:uppercase;">Positivas</span><br/>
            <span style="font-size:10px; color:#22c55e; font-weight:bold;">{{ $pctPos }}%</span>
        </td>
        <td width="20%" style="text-align:center; padding:12px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:28px; font-weight:bold; color:#ef4444;">{{ number_format($neg) }}</span><br/>
            <span style="font-size:9px; color:#64748b; text-transform:uppercase;">Negativas</span><br/>
            <span style="font-size:10px; color:#ef4444; font-weight:bold;">{{ $pctNeg }}%</span>
        </td>
        <td width="20%" style="text-align:center; padding:12px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:28px; font-weight:bold; color:#8b5cf6;">{{ $centrosCount }}</span><br/>
            <span style="font-size:9px; color:#64748b; text-transform:uppercase;">Centros</span>
        </td>
        <td width="20%" style="text-align:center; padding:12px 4px;">
            <span style="font-size:28px; font-weight:bold; color:#f97316;">{{ $obsCount }}</span><br/>
            <span style="font-size:9px; color:#64748b; text-transform:uppercase;">Observadores</span>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- BARRA VISUAL POS vs NEG --}}
<tr>
<td style="padding:12px 24px 16px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        @if($pos > 0)
        <td width="{{ $pctPos }}%" style="background-color:#22c55e; padding:8px 0; text-align:center;">
            <span style="color:#ffffff; font-size:11px; font-weight:bold;">{{ $pctPos }}% Pos</span>
        </td>
        @endif
        @if($neg > 0)
        <td width="{{ $pctNeg }}%" style="background-color:#ef4444; padding:8px 0; text-align:center;">
            <span style="color:#ffffff; font-size:11px; font-weight:bold;">{{ $pctNeg }}% Neg</span>
        </td>
        @endif
    </tr>
    </table>
</td>
</tr>

{{-- INTERNO vs EXTERNO --}}
@if(!empty($intExt))
<tr>
<td style="padding:8px 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
    <tr>
        @foreach($intExt as $tipo => $cnt)
        <td style="text-align:center; padding:10px 8px; {{ !$loop->last ? 'border-right:1px solid #e2e8f0;' : '' }}">
            <span style="font-size:18px; font-weight:bold; color:#334155;">{{ number_format($cnt) }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase;">{{ $tipo }}</span>
            <span style="font-size:10px; color:#94a3b8;">({{ $total > 0 ? round(($cnt/$total)*100,1) : 0 }}%)</span>
        </td>
        @endforeach
    </tr>
    </table>
</td>
</tr>
@endif

{{-- TIPOS DE FALTA (Negativas) --}}
@if(!empty($negPorTipo))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #ef4444; padding-bottom:5px;">
        &#9888; Tipos de Falta &mdash; Tarjetas Negativas
    </h3>
    @php $maxNeg = max($negPorTipo); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#fef2f2;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Tipo de Falta</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:250px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:50px;">Cant.</td>
        </tr>
        @foreach(array_slice($negPorTipo, 0, 10, true) as $tipo => $cnt)
        @php $pctBar = $maxNeg > 0 ? round(($cnt / $maxNeg) * 100) : 0; @endphp
        <tr>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:11px;">{{ $tipo }}</td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#fca5a5; height:14px; border-radius:3px 0 0 3px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:14px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#ef4444;">{{ number_format($cnt) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TIPOS FELICITACION (Positivas) --}}
@if(!empty($posPorTipo))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #22c55e; padding-bottom:5px;">
        &#9989; Tipos de Felicitaci&oacute;n &mdash; Tarjetas Positivas
    </h3>
    @php $maxPos = max($posPorTipo); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#ecfdf5;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Tipo</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:250px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:50px;">Cant.</td>
        </tr>
        @foreach(array_slice($posPorTipo, 0, 10, true) as $tipo => $cnt)
        @php $pctBar = $maxPos > 0 ? round(($cnt / $maxPos) * 100) : 0; @endphp
        <tr>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:11px;">{{ $tipo }}</td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#86efac; height:14px; border-radius:3px 0 0 3px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:14px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#22c55e;">{{ number_format($cnt) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TOP TRABAJADORES NEGATIVAS --}}
@if(!empty($topNeg))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #ef4444; padding-bottom:5px;">
        Trabajadores con m&aacute;s Tarjetas Negativas
    </h3>
    @php $maxTN = max($topNeg); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#fef2f2;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:25px;">#</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Trabajador</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:200px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:45px;">Neg.</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topNeg, 0, 15, true) as $nombre => $cnt)
        @php $pctBar = $maxTN > 0 ? round(($cnt / $maxTN) * 100) : 0; @endphp
        <tr>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-weight:bold; font-size:10px;">{{ $rank }}</td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize; font-size:11px;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#fca5a5; height:10px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:10px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#ef4444;">{{ $cnt }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TOP TRABAJADORES POSITIVAS --}}
@if(!empty($topPos))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #22c55e; padding-bottom:5px;">
        Trabajadores con m&aacute;s Tarjetas Positivas
    </h3>
    @php $maxTP = max($topPos); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#ecfdf5;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:25px;">#</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Trabajador</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:200px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:45px;">Pos.</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topPos, 0, 15, true) as $nombre => $cnt)
        @php $pctBar = $maxTP > 0 ? round(($cnt / $maxTP) * 100) : 0; @endphp
        <tr>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-weight:bold; font-size:10px;">{{ $rank }}</td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize; font-size:11px;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#86efac; height:10px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:10px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#22c55e;">{{ $cnt }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- CENTROS DE TRABAJO --}}
@if(!empty($centrosData))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #3b82f6; padding-bottom:5px;">
        Centro de Trabajo (Lugar)
    </h3>
    @php $maxC = max($centrosData); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#eff6ff;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Centro</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:220px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:45px;">Cant.</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:40px;">%</td>
        </tr>
        @foreach(array_slice($centrosData, 0, 15, true) as $c => $cnt)
        @php $pctBar = $maxC > 0 ? round(($cnt / $maxC) * 100) : 0; @endphp
        <tr>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:11px;">{{ $c }}</td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#93c5fd; height:12px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:12px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#3b82f6;">{{ number_format($cnt) }}</td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; text-align:center; color:#94a3b8; font-size:10px;">{{ $total > 0 ? round(($cnt/$total)*100,1) : 0 }}%</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ZONAS / AREAS --}}
@if(!empty($areasData))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #06b6d4; padding-bottom:5px;">
        Zonas al Interior del Centro
    </h3>
    @php $maxA = max($areasData); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#ecfeff;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">&Aacute;rea / Zona</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:220px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:50px;">Cant.</td>
        </tr>
        @foreach(array_slice($areasData, 0, 12, true) as $ar => $cnt)
        @php $pctBar = $maxA > 0 ? round(($cnt / $maxA) * 100) : 0; @endphp
        <tr>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:11px;">{{ $ar }}</td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#a5f3fc; height:12px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:12px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:4px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#06b6d4;">{{ number_format($cnt) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TOP OBSERVADORES --}}
@if(!empty($topObs))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #f59e0b; padding-bottom:5px;">
        Persona que pas&oacute; la Tarjeta (Observadores Top 15)
    </h3>
    @php $maxO = max($topObs); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#fffbeb;">
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:25px;">#</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Observador</td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:180px;"></td>
            <td style="padding:5px 8px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:50px;">Obs.</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topObs, 0, 15, true) as $nombre => $cnt)
        @php $pctBar = $maxO > 0 ? round(($cnt / $maxO) * 100) : 0; @endphp
        <tr>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-weight:bold; font-size:10px;">{{ $rank }}</td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize; font-size:11px;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="{{ $pctBar }}%" style="background-color:#fde68a; height:10px; border-radius:2px;">&nbsp;</td>
                    <td width="{{ 100 - $pctBar }}%" style="height:10px;">&nbsp;</td>
                </tr></table>
            </td>
            <td style="padding:3px 8px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#f59e0b;">{{ $cnt }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- EMPRESA OBSERVADO + EMPRESA OBSERVADOR --}}
@if(!empty($empresas) || !empty($empresasObs))
<tr>
<td style="padding:16px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        @if(!empty($empresas))
        <td width="49%" valign="top">
            <h3 style="margin:0 0 8px; font-size:13px; color:#0f172a; border-bottom:2px solid #a855f7; padding-bottom:4px;">
                Empresa Observado
            </h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px;">
                @foreach($empresas as $emp => $cnt)
                <tr>
                    <td style="padding:3px 6px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $emp }}</td>
                    <td style="padding:3px 6px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#a855f7; width:40px;">{{ number_format($cnt) }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($empresasObs))
        <td width="49%" valign="top">
            <h3 style="margin:0 0 8px; font-size:13px; color:#0f172a; border-bottom:2px solid #ec4899; padding-bottom:4px;">
                Empresa Observador
            </h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px;">
                @foreach($empresasObs as $emp => $cnt)
                <tr>
                    <td style="padding:3px 6px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $emp }}</td>
                    <td style="padding:3px 6px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#ec4899; width:40px;">{{ number_format($cnt) }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        @endif
    </tr>
    </table>
</td>
</tr>
@endif

{{-- ANTIGUEDAD + CARGO + TURNO --}}
<tr>
<td style="padding:16px 24px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        @if(!empty($antiguedades))
        <td width="32%" valign="top">
            <h3 style="margin:0 0 8px; font-size:13px; color:#0f172a; border-bottom:2px solid #8b5cf6; padding-bottom:4px;">
                Antig&uuml;edad
            </h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px;">
                @foreach(array_slice($antiguedades, 0, 8, true) as $antig => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $antig }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#8b5cf6; width:35px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($cargos))
        <td width="32%" valign="top">
            <h3 style="margin:0 0 8px; font-size:13px; color:#0f172a; border-bottom:2px solid #14b8a6; padding-bottom:4px;">
                Cargo
            </h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px;">
                @foreach(array_slice($cargos, 0, 8, true) as $cargo => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $cargo }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#14b8a6; width:35px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="2%">&nbsp;</td>
        @endif
        @if(!empty($turnos))
        <td width="32%" valign="top">
            <h3 style="margin:0 0 8px; font-size:13px; color:#0f172a; border-bottom:2px solid #64748b; padding-bottom:4px;">
                Turno
            </h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px;">
                @foreach($turnos as $turno => $cnt)
                <tr>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $turno }}</td>
                    <td style="padding:2px 4px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:bold; color:#64748b; width:35px;">{{ $cnt }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        @endif
    </tr>
    </table>
</td>
</tr>

{{-- TENDENCIA MENSUAL (barras HTML) --}}
@if(!empty($byMonth))
<tr>
<td style="padding:16px 24px 4px;">
    <h3 style="margin:0 0 10px; font-size:14px; color:#0f172a; border-bottom:2px solid #3b82f6; padding-bottom:5px;">
        Tendencia Mensual
    </h3>
    @php
        $maxMonth = max($byMonth);
        $monthLabels = [];
        foreach($byMonth as $mk => $v) {
            try { $monthLabels[$mk] = \Carbon\Carbon::createFromFormat('Y-m', $mk)->translatedFormat('M y'); } catch(\Exception $e) { $monthLabels[$mk] = $mk; }
        }
        $lastMonths = array_slice($byMonth, -12, 12, true);
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:11px;">
    @foreach($lastMonths as $mk => $cnt)
    @php $pctBar = $maxMonth > 0 ? round(($cnt / $maxMonth) * 100) : 0; @endphp
    <tr>
        <td style="padding:2px 6px; width:60px; text-align:right; color:#64748b; font-size:10px; white-space:nowrap;">{{ $monthLabels[$mk] ?? $mk }}</td>
        <td style="padding:2px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                <td width="{{ $pctBar }}%" style="background-color:#60a5fa; height:14px; border-radius:2px;">&nbsp;</td>
                <td width="{{ 100 - $pctBar }}%" style="height:14px;">&nbsp;</td>
            </tr></table>
        </td>
        <td style="padding:2px 6px; width:40px; text-align:right; font-weight:bold; color:#3b82f6; font-size:10px;">{{ number_format($cnt) }}</td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- CTA BUTTON --}}
<tr>
<td style="padding:24px 32px; text-align:center;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
    <tr>
        <td style="background-color:#3b82f6; border-radius:8px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ url('/stop-dashboard') }}" style="height:44px;v-text-anchor:middle;width:240px;" arcsize="18%" fillcolor="#3b82f6" stroke="f">
            <center style="color:#ffffff;font-family:Arial;font-size:14px;font-weight:bold;">Ver Dashboard Completo</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ url('/stop-dashboard') }}" style="display:inline-block; padding:12px 36px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; line-height:20px;">
                Ver Dashboard Completo
            </a>
            <!--<![endif]-->
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- FOOTER --}}
<tr>
<td style="padding:16px 32px; background-color:#f8fafc; text-align:center; border-top:1px solid #e2e8f0; border-radius:0 0 4px 4px;">
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
