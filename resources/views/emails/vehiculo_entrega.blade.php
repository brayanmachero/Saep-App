<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Acta de Entrega</title></head>
<body style="font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f1f5f9;margin:0;padding:0;color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 15px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);border:1px solid #e2e8f0;">
    <tr>
        <td style="background:linear-gradient(135deg,#1e3a8a 0%,#1e40af 100%);padding:35px 30px;text-align:center;color:#ffffff;border-bottom:4px solid #f97316;">
            <div style="margin-bottom:20px;">
                <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP" style="max-height:48px;width:auto;" />
            </div>
            <h1 style="margin:0;font-size:24px;font-weight:600;letter-spacing:-0.025em;">Acta de Entrega de Vehículo</h1>
            <p style="margin:8px 0 0;font-size:15px;opacity:0.9;">Confirmación oficial de recepción y custodia</p>
        </td>
    </tr>
    <tr>
        <td style="padding:35px 30px;">
            <div style="background:#f8fafc;border-left:4px solid #3b82f6;padding:16px 20px;border-radius:0 8px 8px 0;margin-bottom:25px;">
                <p style="margin:0;font-size:14.5px;line-height:1.6;color:#475569;">
                    Se ha completado exitosamente la inspección y el acto de entrega del vehículo <strong>{{ $vehiculo['patente'] }}</strong>.
                    Puede revisar la firma digital y las conformidades de responsabilidad en el <strong>documento oficial de respaldo (PDF)</strong> adjunto a este correo.
                </p>
            </div>

            <h2 style="color:#0f172a;font-size:16px;font-weight:600;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e2e8f0;padding-bottom:10px;">Detalles de la Operación</h2>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0;margin-bottom:25px;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;width:40%;background:#f8fafc;font-size:14px;">Tipo de Operación</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background:#fff;font-size:14px;"><span style="background:#dbeafe;color:#1d4ed8;padding:4px 10px;border-radius:12px;font-size:13px;font-weight:600;">{{ $vehiculo['gestion'] }}</span></td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background:#f8fafc;font-size:14px;">Fecha de Autorización</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background:#fff;font-size:14px;">{{ $vehiculo['fecha_hora'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background:#f8fafc;font-size:14px;">Patente (PPU)</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:600;background:#fff;font-size:16px;">{{ $vehiculo['patente'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background:#f8fafc;font-size:14px;">Marca y Modelo</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background:#fff;font-size:14px;">{{ $vehiculo['marca_modelo'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background:#f8fafc;font-size:14px;">Kilometraje de Entrega</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background:#fff;font-size:14px;">{{ $vehiculo['kilometraje_entrega'] }} km</td></tr>
                <tr><td style="padding:14px 16px;color:#64748b;font-weight:500;background:#f8fafc;font-size:14px;">Ubicación GPS</td>
                    <td style="padding:14px 16px;color:#0f172a;font-weight:500;background:#fff;font-size:14px;"><a href="https://maps.google.com/?q={{ $vehiculo['geo_entrega'] }}" target="_blank" style="color:#2563eb;text-decoration:none;">Ver Localización en Google Maps →</a></td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="background:#f8fafc;padding:24px 30px;text-align:center;font-size:13px;color:#64748b;border-top:1px solid #e2e8f0;">
            <strong>SAEP Servicios Profesionales</strong> © {{ date('Y') }}<br><br>
            Documento generado de forma automática a través de Kizeo Forms. No responda a este correo.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
