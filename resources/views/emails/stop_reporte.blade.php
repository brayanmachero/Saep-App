<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Reporte Tarjeta STOP</title>
<!--[if mso]>
<style type="text/css">
body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
</style>
<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
@php
    $total = $stats['total'] ?? 0;
    $pos = $stats['positivas'] ?? 0;
    $neg = $stats['negativas'] ?? 0;
    $pctPos = $total > 0 ? round(($pos / $total) * 100, 1) : 0;
    $pctNeg = $total > 0 ? round(($neg / $total) * 100, 1) : 0;
    $centrosCount = $stats['centros'] ?? 0;
    $obsCount = $stats['observadores'] ?? 0;
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="660" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border:1px solid #e2e8f0; max-width:660px;">

{{-- HEADER --}}
<tr>
<td style="background-color:#0f172a; padding:28px 32px; text-align:center;">
    <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:bold; letter-spacing:0.3px;">
        Reporte Tarjeta STOP &mdash; Observaciones de Seguridad
    </h1>
    <p style="margin:8px 0 0; color:#93c5fd; font-size:13px;">{{ $periodo }}{{ $mesLabel ? ' — '.$mesLabel : '' }}</p>
</td>
</tr>

{{-- KPIs --}}
<tr>
<td style="padding:24px 20px 16px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        {{-- Total --}}
        <td width="20%" style="text-align:center; padding:8px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:24px; font-weight:bold; color:#3b82f6;">{{ number_format($total) }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Total</span>
        </td>
        {{-- Positivas --}}
        <td width="20%" style="text-align:center; padding:8px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:24px; font-weight:bold; color:#22c55e;">{{ number_format($pos) }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Positivas</span><br/>
            <span style="font-size:10px; color:#22c55e;">{{ $pctPos }}%</span>
        </td>
        {{-- Negativas --}}
        <td width="20%" style="text-align:center; padding:8px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:24px; font-weight:bold; color:#ef4444;">{{ number_format($neg) }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Negativas</span><br/>
            <span style="font-size:10px; color:#ef4444;">{{ $pctNeg }}%</span>
        </td>
        {{-- Centros --}}
        <td width="20%" style="text-align:center; padding:8px 4px; border-right:1px solid #e2e8f0;">
            <span style="font-size:24px; font-weight:bold; color:#8b5cf6;">{{ $centrosCount }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Centros</span>
        </td>
        {{-- Observadores --}}
        <td width="20%" style="text-align:center; padding:8px 4px;">
            <span style="font-size:24px; font-weight:bold; color:#f97316;">{{ $obsCount }}</span><br/>
            <span style="font-size:10px; color:#64748b; text-transform:uppercase;">Observadores</span>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- TIPOS DE FALTA - NEGATIVAS --}}
@if(!empty($negPorTipo))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #ef4444; padding-bottom:6px;">
        Tipos de Falta &mdash; Tarjetas Negativas
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#fef2f2;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Tipo</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Cantidad</td>
        </tr>
        @foreach(array_slice($negPorTipo, 0, 10, true) as $tipo => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $tipo }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#ef4444;">{{ number_format($count) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TIPOS DE FELICITACIÓN - POSITIVAS --}}
@if(!empty($posPorTipo))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #22c55e; padding-bottom:6px;">
        Tipos de Felicitaci&oacute;n &mdash; Tarjetas Positivas
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#ecfdf5;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Tipo</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Cantidad</td>
        </tr>
        @foreach(array_slice($posPorTipo, 0, 10, true) as $tipo => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $tipo }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#22c55e;">{{ number_format($count) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TRABAJADORES CON MÁS TARJETAS NEGATIVAS --}}
@if(!empty($topNegTrabajadores))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #ef4444; padding-bottom:6px;">
        Trabajadores con m&aacute;s Tarjetas Negativas
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#fef2f2;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:30px;">#</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Trabajador</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Neg.</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topNegTrabajadores, 0, 15, true) as $nombre => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-weight:bold;">{{ $rank }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#ef4444;">{{ number_format($count) }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TRABAJADORES CON MÁS TARJETAS POSITIVAS --}}
@if(!empty($topPosTrabajadores))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #22c55e; padding-bottom:6px;">
        Trabajadores con m&aacute;s Tarjetas Positivas
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#ecfdf5;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:30px;">#</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Trabajador</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Pos.</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topPosTrabajadores, 0, 15, true) as $nombre => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-weight:bold;">{{ $rank }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#22c55e;">{{ number_format($count) }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- CENTROS DE TRABAJO --}}
@if(!empty($centros))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #3b82f6; padding-bottom:6px;">
        Lugar &mdash; Centro de Trabajo
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#eff6ff;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Centro</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Cantidad</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:60px;">%</td>
        </tr>
        @foreach(array_slice($centros, 0, 15, true) as $c => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $c }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#3b82f6;">{{ number_format($count) }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; color:#94a3b8;">{{ $total > 0 ? round(($count / $total) * 100, 1) : 0 }}%</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ÁREAS / ZONAS --}}
@if(!empty($areas))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #06b6d4; padding-bottom:6px;">
        Zonas al Interior del Centro
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#ecfeff;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">&Aacute;rea</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Cantidad</td>
        </tr>
        @foreach(array_slice($areas, 0, 15, true) as $a => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $a }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#06b6d4;">{{ number_format($count) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- TOP OBSERVADORES (quien pasó la tarjeta) --}}
@if(!empty($topObservadores))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #f59e0b; padding-bottom:6px;">
        Persona que pas&oacute; la Tarjeta (Top 15)
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#fffbeb;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0; width:30px;">#</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Observador</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Obs.</td>
        </tr>
        @php $rank = 1; @endphp
        @foreach(array_slice($topObservadores, 0, 15, true) as $nombre => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#94a3b8; font-weight:bold;">{{ $rank }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155; text-transform:capitalize;">{{ mb_strtolower($nombre) }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#f59e0b;">{{ number_format($count) }}</td>
        </tr>
        @php $rank++; @endphp
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- ANTIGÜEDAD --}}
@if(!empty($antiguedades))
<tr>
<td style="padding:16px 24px 8px;">
    <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a; border-bottom:2px solid #8b5cf6; padding-bottom:6px;">
        Antig&uuml;edad de Persona Observada
    </h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;">
        <tr style="background-color:#f5f3ff;">
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; border-bottom:1px solid #e2e8f0;">Antig&uuml;edad</td>
            <td style="padding:6px 10px; font-weight:bold; color:#64748b; text-align:center; border-bottom:1px solid #e2e8f0; width:80px;">Cantidad</td>
        </tr>
        @foreach($antiguedades as $antig => $count)
        <tr>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#334155;">{{ $antig }}</td>
            <td style="padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:bold; color:#8b5cf6;">{{ number_format($count) }}</td>
        </tr>
        @endforeach
    </table>
</td>
</tr>
@endif

{{-- CTA BUTTON --}}
<tr>
<td style="padding:24px 32px; text-align:center;">
    <a href="{{ url('/stop-dashboard') }}" style="display:inline-block; padding:12px 32px; background-color:#3b82f6; color:#ffffff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:bold;">
        Ver Dashboard Completo
    </a>
</td>
</tr>

{{-- FOOTER --}}
<tr>
<td style="padding:16px 32px; background-color:#f8fafc; text-align:center; border-top:1px solid #e2e8f0;">
    <p style="margin:0; font-size:11px; color:#94a3b8;">
        Reporte generado autom&aacute;ticamente por SAEP &mdash; {{ config('app.url') }}
    </p>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
