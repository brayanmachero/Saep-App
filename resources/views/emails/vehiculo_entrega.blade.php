<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Acta de Entrega</title></head>
<body style="font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f1f5f9;margin:0;padding:0;color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 15px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;overflow:hidden;border:1px solid #e2e8f0;">
    @include('emails.partials.saep_header', [
        'module' => 'Kizeo Forms · Vehiculos',
        'badge' => 'Acta de entrega',
        'badgeColor' => '#2563eb',
        'accentColor' => '#2563eb',
    ])
    <tr>
        <td style="padding:35px 30px;">
            <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#0f1b4c;">Acta de Entrega de Vehículo</h1>
            <p style="margin:0 0 24px;font-size:14px;color:#64748b;">Confirmación oficial de recepción y custodia</p>

            <div style="background-color:#f8fafc;border-left:4px solid #3b82f6;padding:16px 20px;">
                <p style="margin:0;font-size:14.5px;line-height:1.6;color:#475569;">
                    Se ha completado exitosamente la inspección y el acto de entrega del vehículo <strong>{{ $vehiculo['patente'] }}</strong>.
                    Puede revisar la firma digital y las conformidades de responsabilidad en el <strong>documento oficial de respaldo (PDF)</strong> adjunto a este correo.
                </p>
            </div>

            <h2 style="color:#0f172a;font-size:16px;font-weight:600;margin:0 0 15px;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e2e8f0;padding-bottom:10px;">Detalles de la Operación</h2>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:25px;border:1px solid #e2e8f0;">
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;width:40%;background-color:#f8fafc;font-size:14px;">Tipo de Operación</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background-color:#fff;font-size:14px;"><strong style="color:#1d4ed8;font-size:13px;">{{ $vehiculo['gestion'] }}</strong></td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background-color:#f8fafc;font-size:14px;">Fecha de Autorización</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background-color:#fff;font-size:14px;">{{ $vehiculo['fecha_hora'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background-color:#f8fafc;font-size:14px;">Patente (PPU)</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:600;background-color:#fff;font-size:16px;">{{ $vehiculo['patente'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background-color:#f8fafc;font-size:14px;">Marca y Modelo</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background-color:#fff;font-size:14px;">{{ $vehiculo['marca_modelo'] }}</td></tr>
                <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:500;background-color:#f8fafc;font-size:14px;">Kilometraje de Entrega</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:500;background-color:#fff;font-size:14px;">{{ $vehiculo['kilometraje_entrega'] }} km</td></tr>
                @if($vehiculo['geo_entrega'] !== '-')
                <tr><td style="padding:14px 16px;color:#64748b;font-weight:500;background-color:#f8fafc;font-size:14px;">Ubicación GPS</td>
                    <td style="padding:14px 16px;color:#0f172a;font-weight:500;background-color:#fff;font-size:14px;"><a href="https://maps.google.com/?q={{ $vehiculo['geo_entrega'] }}" target="_blank" style="color:#2563eb;text-decoration:none;">Ver Localización en Google Maps →</a></td></tr>
                @endif
            </table>
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Documento generado automaticamente a traves de Kizeo Forms.',
        'context' => 'No respondas a este correo.',
    ])
</table>
</td></tr>
</table>
</body>
</html>
