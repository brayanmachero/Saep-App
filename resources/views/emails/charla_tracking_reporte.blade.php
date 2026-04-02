<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reporte Semanal Charlas SST</title>
</head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f0f2f5;color:#1e293b">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:24px 0">
<tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">

{{-- Header --}}
<tr>
<td style="background:linear-gradient(135deg,#0f172a 0%,#1e40af 100%);padding:28px 32px;text-align:center">
    <h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;letter-spacing:.3px">
        📊 Reporte Semanal — Charlas de Seguridad
    </h1>
    <p style="margin:6px 0 0;color:#93c5fd;font-size:13px">{{ $periodo }}</p>
</td>
</tr>

{{-- KPIs --}}
<tr>
<td style="padding:24px 32px 16px">
    <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td width="25%" style="text-align:center;padding:8px">
            <div style="background:#f0f9ff;border-radius:10px;padding:14px 8px;border:1px solid #bae6fd">
                <div style="font-size:28px;font-weight:800;color:#0369a1">{{ $stats['total'] ?? 0 }}</div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Total</div>
            </div>
        </td>
        <td width="25%" style="text-align:center;padding:8px">
            <div style="background:#f0fdf4;border-radius:10px;padding:14px 8px;border:1px solid #bbf7d0">
                <div style="font-size:28px;font-weight:800;color:#15803d">{{ $stats['completadas'] ?? 0 }}</div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Completadas</div>
            </div>
        </td>
        <td width="25%" style="text-align:center;padding:8px">
            <div style="background:#fef2f2;border-radius:10px;padding:14px 8px;border:1px solid #fecaca">
                <div style="font-size:28px;font-weight:800;color:#dc2626">{{ $stats['pendientes'] ?? 0 }}</div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Pendientes</div>
            </div>
        </td>
        <td width="25%" style="text-align:center;padding:8px">
            @php
                $tasa = $stats['tasa_cumplimiento'] ?? 0;
                $tasaColor = $tasa >= 80 ? '#15803d' : ($tasa >= 50 ? '#d97706' : '#dc2626');
                $tasaBg = $tasa >= 80 ? '#f0fdf4' : ($tasa >= 50 ? '#fffbeb' : '#fef2f2');
                $tasaBorder = $tasa >= 80 ? '#bbf7d0' : ($tasa >= 50 ? '#fde68a' : '#fecaca');
            @endphp
            <div style="background:{{ $tasaBg }};border-radius:10px;padding:14px 8px;border:1px solid {{ $tasaBorder }}">
                <div style="font-size:28px;font-weight:800;color:{{ $tasaColor }}">{{ $tasa }}%</div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Cumplimiento</div>
            </div>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- Pendientes por usuario --}}
@if(!empty($pendientesPorUsuario))
<tr>
<td style="padding:8px 32px 16px">
    <h2 style="font-size:14px;color:#334155;margin:0 0 12px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">
        ⚠ Usuarios con tareas pendientes
    </h2>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse">
    <tr style="background:#f8fafc">
        <th style="text-align:left;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Usuario</th>
        <th style="text-align:center;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Pendientes</th>
        <th style="text-align:center;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Más antigua</th>
        <th style="text-align:center;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Días</th>
    </tr>
    @foreach($pendientesPorUsuario as $usuario)
    @php
        $dias = $usuario['dias_max'] ?? 0;
        $diasColor = $dias > 14 ? '#dc2626' : ($dias > 7 ? '#d97706' : '#64748b');
    @endphp
    <tr>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;font-weight:600">{{ $usuario['nombre'] }}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center">
            <span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700">{{ $usuario['cantidad'] }}</span>
        </td>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;color:#64748b;font-size:12px">{{ $usuario['fecha_mas_antigua'] ?? '-' }}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;font-weight:700;color:{{ $diasColor }}">{{ $dias }}d</td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- Resumen semanal --}}
@if(!empty($resumenSemanal))
<tr>
<td style="padding:8px 32px 16px">
    <h2 style="font-size:14px;color:#334155;margin:0 0 12px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">
        📈 Evolución últimas semanas
    </h2>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse">
    <tr style="background:#f8fafc">
        <th style="text-align:left;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Semana</th>
        <th style="text-align:center;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Total</th>
        <th style="text-align:center;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Completadas</th>
        <th style="text-align:center;padding:8px 12px;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e2e8f0">Tasa</th>
    </tr>
    @foreach($resumenSemanal as $sem)
    @php
        $tSem = $sem['tasa'] ?? 0;
        $tColor = $tSem >= 80 ? '#15803d' : ($tSem >= 50 ? '#d97706' : '#dc2626');
    @endphp
    <tr>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;font-weight:600">S{{ $sem['semana'] }} — {{ $sem['anio'] }}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center">{{ $sem['total'] }}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;color:#15803d;font-weight:600">{{ $sem['completadas'] }}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;font-weight:700;color:{{ $tColor }}">{{ $tSem }}%</td>
    </tr>
    @endforeach
    </table>
</td>
</tr>
@endif

{{-- CTA --}}
<tr>
<td style="padding:16px 32px;text-align:center">
    <a href="{{ url('/charla-tracking') }}" style="display:inline-block;background:#1e40af;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">
        Ver Dashboard Completo
    </a>
</td>
</tr>

{{-- Footer --}}
<tr>
<td style="padding:16px 32px 24px;text-align:center;border-top:1px solid #e2e8f0">
    <p style="margin:0;color:#94a3b8;font-size:11px">
        Este reporte se genera automáticamente cada lunes a las 08:00 AM.<br>
        SAEP Platform — Prevención de Riesgos
    </p>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
