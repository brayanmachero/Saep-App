<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Reporte Semanal Charlas SST</title>
<!--[if mso]>
<style type="text/css">
body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
</style>
<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
@php
    $tasa = $stats['tasa_cumplimiento'] ?? 0;
    $tasaColor = $tasa >= 80 ? '#15803d' : ($tasa >= 50 ? '#b45309' : '#dc2626');
    $tasaBg = $tasa >= 80 ? '#ecfdf5' : ($tasa >= 50 ? '#fffbeb' : '#fef2f2');
@endphp

<!-- Wrapper -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;">
<tr><td align="center" style="padding:24px 12px;">

<!-- Container 660px -->
<table role="presentation" width="660" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border:1px solid #e2e8f0; max-width:660px;">

{{-- ===== HEADER ===== --}}
<tr>
<td style="background-color:#0f172a; padding:28px 32px; text-align:center;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="text-align:center;">
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:bold; font-family:Arial, Helvetica, sans-serif; letter-spacing:0.3px;">
                Reporte Semanal &mdash; Charlas de Seguridad
            </h1>
            <p style="margin:8px 0 0; color:#93c5fd; font-size:13px; font-family:Arial, Helvetica, sans-serif;">{{ $periodo }}</p>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- ===== TASA BANNER ===== --}}
<tr>
<td style="background-color:{{ $tasaBg }}; padding:16px 32px; text-align:center; border-bottom:2px solid {{ $tasaColor }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="text-align:center;">
            <span style="font-size:32px; font-weight:bold; color:{{ $tasaColor }}; font-family:Arial, Helvetica, sans-serif;">{{ $tasa }}%</span>
            <span style="font-size:13px; color:#64748b; font-family:Arial, Helvetica, sans-serif; display:inline; margin-left:8px;">Tasa de Cumplimiento</span>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- ===== KPIs 5 COLUMNAS ===== --}}
<tr>
<td style="padding:24px 20px 16px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        {{-- Total --}}
        <td width="20%" align="center" valign="top" style="padding:0 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0; background-color:#f8fafc;">
            <tr><td style="padding:14px 4px 4px; text-align:center;">
                <span style="font-size:26px; font-weight:bold; color:#0369a1; font-family:Arial, Helvetica, sans-serif;">{{ $stats['total'] ?? 0 }}</span>
            </td></tr>
            <tr><td style="padding:2px 4px 12px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif;">Total</span>
            </td></tr>
            </table>
        </td>
        {{-- Completadas --}}
        <td width="20%" align="center" valign="top" style="padding:0 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #bbf7d0; background-color:#f0fdf4;">
            <tr><td style="padding:14px 4px 4px; text-align:center;">
                <span style="font-size:26px; font-weight:bold; color:#15803d; font-family:Arial, Helvetica, sans-serif;">{{ $stats['completadas'] ?? 0 }}</span>
            </td></tr>
            <tr><td style="padding:2px 4px 12px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif;">Completadas</span>
            </td></tr>
            </table>
        </td>
        {{-- Transferidos --}}
        <td width="20%" align="center" valign="top" style="padding:0 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #c4b5fd; background-color:#f5f3ff;">
            <tr><td style="padding:14px 4px 4px; text-align:center;">
                <span style="font-size:26px; font-weight:bold; color:#7c3aed; font-family:Arial, Helvetica, sans-serif;">{{ $stats['transferidos'] ?? 0 }}</span>
            </td></tr>
            <tr><td style="padding:2px 4px 12px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif;">Transferidos</span>
            </td></tr>
            </table>
        </td>
        {{-- Sin Gestión --}}
        <td width="20%" align="center" valign="top" style="padding:0 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #fecaca; background-color:#fef2f2;">
            <tr><td style="padding:14px 4px 4px; text-align:center;">
                <span style="font-size:26px; font-weight:bold; color:#dc2626; font-family:Arial, Helvetica, sans-serif;">{{ $stats['sin_gestion'] ?? 0 }}</span>
            </td></tr>
            <tr><td style="padding:2px 4px 12px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif;">Sin Gesti&oacute;n</span>
            </td></tr>
            </table>
        </td>
        {{-- Prom Días --}}
        <td width="20%" align="center" valign="top" style="padding:0 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #fed7aa; background-color:#fff7ed;">
            <tr><td style="padding:14px 4px 4px; text-align:center;">
                <span style="font-size:26px; font-weight:bold; color:#c2410c; font-family:Arial, Helvetica, sans-serif;">{{ $stats['prom_dias'] ?? 0 }}</span>
            </td></tr>
            <tr><td style="padding:2px 4px 12px; text-align:center;">
                <span style="font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif;">Prom. D&iacute;as</span>
            </td></tr>
            </table>
        </td>
    </tr>
    </table>
</td>
</tr>

{{-- ===== CUMPLIMIENTO POR DESTINATARIO (secci\u00f3n principal) ===== --}}
@if(!empty($topDestinatarios))
<tr>
<td style="padding:8px 24px 20px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="padding:0 0 10px; border-bottom:2px solid #dc2626;">
        <span style="font-size:14px; font-weight:bold; color:#1e293b; font-family:Arial, Helvetica, sans-serif;">Formularios Pendientes por Responsable</span>
    </td></tr>
    <tr><td style="padding-top:8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr style="background-color:#f1f5f9;">
            <th align="left" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Responsable</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Pend.</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">OK</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Total</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Recup.</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Sin desc.</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Tasa</th>
        </tr>
        @foreach($topDestinatarios as $dest)
        @php
            $dPend = $dest['pendientes'] ?? 0;
            $dTasa = $dest['tasa'] ?? 0;
            $dColor = $dTasa >= 80 ? '#15803d' : ($dTasa >= 50 ? '#b45309' : '#dc2626');
            $rowBg = $dPend > 0 ? '#fff5f5' : '#ffffff';
        @endphp
        <tr style="background-color:{{ $rowBg }};">
            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#1e293b; font-weight:bold; font-family:Arial, Helvetica, sans-serif;">{{ $dest['nombre'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:bold; color:#dc2626; font-family:Arial, Helvetica, sans-serif;">{{ $dPend }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#15803d; font-weight:bold; font-family:Arial, Helvetica, sans-serif;">{{ $dest['completadas'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#334155; font-family:Arial, Helvetica, sans-serif;">{{ $dest['total'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#0369a1; font-family:Arial, Helvetica, sans-serif;">{{ $dest['recuperadas'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#b45309; font-family:Arial, Helvetica, sans-serif;">{{ $dest['sin_descargar'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; font-weight:bold; color:{{ $dColor }}; font-family:Arial, Helvetica, sans-serif;">{{ $dTasa }}%</td>
        </tr>
        @endforeach
        </table>
    </td></tr>
    {{-- Leyenda de columnas --}}
    <tr><td style="padding:10px 10px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0;">
        <tr><td style="padding:10px 14px;">
            <p style="margin:0 0 6px; font-size:11px; font-weight:bold; color:#475569; font-family:Arial, Helvetica, sans-serif; text-transform:uppercase; letter-spacing:0.5px;">Glosario de columnas</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td width="50%" valign="top" style="padding:2px 0;">
                    <p style="margin:0; font-size:11px; color:#334155; font-family:Arial, Helvetica, sans-serif; line-height:16px;"><strong style="color:#dc2626;">Pend.</strong> &mdash; Formularios a&uacute;n no completados por el destinatario.</p>
                </td>
                <td width="50%" valign="top" style="padding:2px 0 2px 12px;">
                    <p style="margin:0; font-size:11px; color:#334155; font-family:Arial, Helvetica, sans-serif; line-height:16px;"><strong style="color:#15803d;">OK</strong> &mdash; Formularios completados exitosamente.</p>
                </td>
            </tr>
            <tr>
                <td width="50%" valign="top" style="padding:2px 0;">
                    <p style="margin:0; font-size:11px; color:#334155; font-family:Arial, Helvetica, sans-serif; line-height:16px;"><strong style="color:#0369a1;">Recup.</strong> &mdash; Descargados al dispositivo m&oacute;vil, pero no completados a&uacute;n.</p>
                </td>
                <td width="50%" valign="top" style="padding:2px 0 2px 12px;">
                    <p style="margin:0; font-size:11px; color:#334155; font-family:Arial, Helvetica, sans-serif; line-height:16px;"><strong style="color:#b45309;">Sin desc.</strong> &mdash; Transferidos pero no descargados al dispositivo a&uacute;n.</p>
                </td>
            </tr>
            </table>
        </td></tr>
        </table>
    </td></tr>
    </table>
</td>
</tr>
@endif

{{-- ===== PENDIENTES POR USUARIO ===== --}}
@if(!empty($pendientesPorUsuario))
<tr>
<td style="padding:8px 24px 20px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="padding:0 0 10px; border-bottom:2px solid #dc2626;">
        <span style="font-size:14px; font-weight:bold; color:#1e293b; font-family:Arial, Helvetica, sans-serif;">Charlas Sin Completar por Responsable</span>
    </td></tr>
    <tr><td style="padding-top:8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr style="background-color:#f1f5f9;">
            <th align="left" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Responsable</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Pend.</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">M&aacute;s antigua</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">D&iacute;as</th>
        </tr>
        @foreach($pendientesPorUsuario as $usuario)
        @php
            $dias = $usuario['dias_max'] ?? 0;
            $diasColor = $dias > 14 ? '#dc2626' : ($dias > 7 ? '#b45309' : '#64748b');
        @endphp
        <tr>
            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; font-weight:bold; color:#1e293b; font-family:Arial, Helvetica, sans-serif;">{{ $usuario['nombre'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; font-weight:bold; color:#dc2626; font-family:Arial, Helvetica, sans-serif;">{{ $usuario['cantidad'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:12px; color:#64748b; font-family:Arial, Helvetica, sans-serif;">{{ $usuario['fecha_mas_antigua'] ?? '-' }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; font-weight:bold; color:{{ $diasColor }}; font-family:Arial, Helvetica, sans-serif;">{{ $dias }}d</td>
        </tr>
        @endforeach
        </table>
    </td></tr>
    </table>
</td>
</tr>
@endif

{{-- ===== EVOLUCIÓN SEMANAL ===== --}}
@if(!empty($resumenSemanal))
<tr>
<td style="padding:8px 24px 20px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td style="padding:0 0 10px; border-bottom:2px solid #0369a1;">
        <span style="font-size:14px; font-weight:bold; color:#1e293b; font-family:Arial, Helvetica, sans-serif;">Evoluci&oacute;n &Uacute;ltimas Semanas</span>
    </td></tr>
    <tr><td style="padding-top:8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr style="background-color:#f1f5f9;">
            <th align="left" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Semana</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Total</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">OK</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Transf.</th>
            <th align="center" style="padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-family:Arial, Helvetica, sans-serif; border-bottom:1px solid #e2e8f0; font-weight:bold;">Tasa</th>
        </tr>
        @foreach($resumenSemanal as $sem)
        @php
            $tSem = $sem['tasa'] ?? 0;
            $tColor = $tSem >= 80 ? '#15803d' : ($tSem >= 50 ? '#b45309' : '#dc2626');
        @endphp
        <tr>
            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; font-weight:bold; color:#1e293b; font-family:Arial, Helvetica, sans-serif;">S{{ $sem['semana'] }} ({{ $sem['fecha'] }})</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#334155; font-family:Arial, Helvetica, sans-serif;">{{ $sem['total'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#15803d; font-weight:bold; font-family:Arial, Helvetica, sans-serif;">{{ $sem['completadas'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#7c3aed; font-family:Arial, Helvetica, sans-serif;">{{ $sem['transferidos'] }}</td>
            <td align="center" style="padding:7px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; font-weight:bold; color:{{ $tColor }}; font-family:Arial, Helvetica, sans-serif;">{{ $tSem }}%</td>
        </tr>
        @endforeach
        </table>
    </td></tr>
    </table>
</td>
</tr>
@endif

{{-- ===== FOOTER ===== --}}
<tr>
<td style="background-color:#f8fafc; padding:20px 32px; text-align:center; border-top:1px solid #e2e8f0;">
    <p style="margin:0; color:#94a3b8; font-size:11px; font-family:Arial, Helvetica, sans-serif; line-height:18px;">
        Este reporte se genera autom&aacute;ticamente cada lunes a las 08:00 AM.<br />
        <strong style="color:#64748b;">SAEP Platform</strong> &mdash; Prevenci&oacute;n de Riesgos
    </p>
</td>
</tr>

</table>
<!-- /Container -->

</td></tr>
</table>
<!-- /Wrapper -->

</body>
</html>
