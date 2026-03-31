<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Acta de Devolución</title></head>
<body style="font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f1f5f9;margin:0;padding:0;color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 15px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;overflow:hidden;border:1px solid #e2e8f0;">
    <tr>
        <td bgcolor="#9f1239" style="background-color:#9f1239;padding:35px 30px;text-align:center;color:#ffffff;border-bottom:4px solid #f43f5e;">
            <div style="margin-bottom:20px;">
                <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP" width="160" style="max-height:48px;width:auto;display:inline-block;" />
                <br><span style="font-size:11px;opacity:0.7;letter-spacing:1px;">SERVICIOS DE ASESORÍAS A EMPRESAS</span>
            </div>
            <h1 style="margin:0;font-size:24px;font-weight:600;letter-spacing:-0.025em;">Acta de Devolución de Vehículo</h1>
            <p style="margin:8px 0 0;font-size:15px;opacity:0.9;">Recepción oficial en base o instalaciones</p>
        </td>
    </tr>
    <tr>
        <td style="padding:35px 30px;">
            <div style="background-color:#fff1f2;border-left:4px solid #e11d48;padding:16px 20px;margin-bottom:25px;">
                <p style="margin:0;font-size:14.5px;line-height:1.6;color:#881337;">
                    Se ha registrado un acto de <strong>Devolución de Vehículo a la Base</strong> para la patente <strong>{{ $vehiculo['patente'] }}</strong>.
                    <em>El documento formal con el detalle exhaustivo de daños, recepción de elementos de seguridad y firmas de conformidad se encuentra adjunto.</em>
                </p>
            </div>

            <h2 style="color:#881337;font-size:16px;font-weight:600;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #ffe4e6;padding-bottom:10px;">Detalles de la Recepción</h2>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:25px;border:1px solid #fecdd3;">
                <tr><td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#9f1239;font-weight:500;width:40%;background-color:#fff1f2;font-size:14px;">Tipo de Operaci&oacute;n</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#4c0519;font-weight:500;background-color:#fff;font-size:14px;"><strong style="color:#e11d48;font-size:13px;">{{ $vehiculo['gestion'] }}</strong></td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#9f1239;font-weight:500;background-color:#fff1f2;font-size:14px;">Fecha de Devolución</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#4c0519;font-weight:500;background-color:#fff;font-size:14px;">{{ $vehiculo['fecha_hora_devolucion'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#9f1239;font-weight:500;background-color:#fff1f2;font-size:14px;">Patente (PPU)</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#4c0519;font-weight:600;background-color:#fff;font-size:16px;">{{ $vehiculo['patente'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#9f1239;font-weight:500;background-color:#fff1f2;font-size:14px;">Kilometraje Declarado</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#4c0519;font-weight:500;background-color:#fff;font-size:14px;">{{ $vehiculo['kilometraje_devolucion'] }} km</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#9f1239;font-weight:500;background-color:#fff1f2;font-size:14px;">¿Daños Nuevos?</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#4c0519;font-weight:700;background-color:#fff;font-size:14px;">{{ $vehiculo['danos_nuevos'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#9f1239;font-weight:500;background-color:#fff1f2;font-size:14px;">¿Kit Completo?</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #fecdd3;color:#4c0519;font-weight:500;background-color:#fff;font-size:14px;">{{ $vehiculo['kit_completo'] }}</td></tr>
                <tr><td style="padding:14px 16px;color:#9f1239;font-weight:500;background-color:#fff1f2;font-size:14px;">Ubicación GPS</td>
                    <td style="padding:14px 16px;color:#4c0519;font-weight:500;background-color:#fff;font-size:14px;"><a href="https://maps.google.com/?q={{ $vehiculo['geo_devolucion'] }}" target="_blank" style="color:#e11d48;text-decoration:none;">Ver Mapa →</a></td></tr>
            </table>

            @if($vehiculo['articulos_faltantes'] !== '-')
            <h2 style="color:#881337;font-size:16px;font-weight:600;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #ffe4e6;padding-bottom:10px;">Alerta de Elementos Faltantes</h2>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:25px;border:1px solid #fecdd3;">
                <tr><td style="padding:14px 16px;color:#be123c;font-weight:500;background-color:#fff1f2;width:40%;font-size:14px;">Faltantes Reportados</td>
                    <td style="padding:14px 16px;color:#e11d48;font-weight:bold;background-color:#fff;font-size:14px;">{{ $vehiculo['articulos_faltantes'] }}</td></tr>
            </table>
            @endif
        </td>
    </tr>
    <tr>
        <td style="background-color:#f8fafc;padding:24px 30px;text-align:center;font-size:13px;color:#64748b;border-top:1px solid #e2e8f0;">
            <strong>SAEP Servicios Profesionales</strong> © {{ date('Y') }}<br><br>
            Documento generado automáticamente a través de Kizeo Forms. No responda a este correo.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
