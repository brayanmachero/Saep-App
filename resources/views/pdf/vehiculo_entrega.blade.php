<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acta de Entrega de Vehículo</title>
<style>
    @page { margin: 20mm 15mm 25mm 15mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; line-height: 1.4; }
    .header { border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 15px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: middle; }
    .logo { max-height: 50px; width: auto; }
    .title-cell { text-align: right; }
    .title-cell h1 { margin: 0; font-size: 18px; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
    .title-cell p { margin: 3px 0 0; font-size: 10px; color: #64748b; }
    .section-title { background-color: #1e40af; color: #ffffff; padding: 7px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin: 15px 0 8px 0; border-radius: 4px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 7px 10px; text-align: left; font-size: 10px; }
    .data-table th { background-color: #f8fafc; font-weight: bold; width: 38%; color: #475569; }
    .data-table td { color: #1e293b; }
    .notes-box { border: 1px solid #e2e8f0; padding: 14px; background-color: #f8fafc; margin-top: 5px; color: #334155; font-style: normal; text-align: justify; line-height: 1.5; border-radius: 6px; font-size: 9px; }
    .signatures { width: 100%; margin-top: 30px; border-collapse: collapse; }
    .signatures td { width: 50%; text-align: center; padding: 10px; vertical-align: bottom; }
    .signature-line { border-bottom: 1px solid #334155; width: 80%; margin: 0 auto 8px auto; min-height: 50px; line-height: 50px; }
    .signature-img { max-height: 60px; width: auto; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    .media-img { max-height: 140px; width: auto; border: 1px solid #cbd5e1; border-radius: 4px; }
</style>
</head>
<body>
    <div class="header">
      <table class="header-table">
        <tr>
          <td><img src="{{ $logoUrl }}" class="logo" alt="Logo SAEP"/></td>
          <td class="title-cell">
            <h1>Acta de Entrega de Vehículo</h1>
            <p><strong>Gestión:</strong> {{ $data['gestion'] }}</p>
            <p><strong>Fecha Autorización:</strong> {{ $data['fecha_hora'] }}</p>
          </td>
        </tr>
      </table>
    </div>

    <div class="section-title">1. Datos del Vehículo Entregado</div>
    <table class="data-table">
      <tr><th>Marca y Modelo</th><td>{{ $data['marca_modelo'] }}</td></tr>
      <tr><th>Patente / PPU</th><td><strong style="color:#1a365d;font-size:13px;">{{ $data['patente'] }}</strong></td></tr>
      <tr><th>Kilometraje de Entrega</th><td>{{ $data['kilometraje_entrega'] }} km</td></tr>
      <tr><th>Kit de Seguridad y Emergencia</th><td>{{ $data['kit_seguridad'] }}</td></tr>
      <tr><th>Geolocalización (Coordenadas)</th><td>{{ $data['geo_entrega'] }}</td></tr>
      @if(str_starts_with($data['dibujo'] ?? '', 'data:image'))
      <tr><th>Esquema / Dibujo de Novedades</th><td><img src="{{ $data['dibujo'] }}" class="media-img" /></td></tr>
      @elseif(($data['dibujo'] ?? '-') !== '-')
      <tr><th>Esquema / Dibujo de Novedades</th><td><em>{{ $data['dibujo'] }}</em></td></tr>
      @endif
    </table>

    <div class="section-title">2. Declaración Jurada de Recepción</div>
    <div class="notes-box">
      <p style="margin-top:0;"><strong>Texto de Responsabilidad y Custodia:</strong></p>
      {!! ($data['declaracion_recepcion'] ?? '-') !== '-' ? nl2br(e($data['declaracion_recepcion'])) : 'El conductor asume la total responsabilidad y custodia del vehículo recibido en las dependencias establecidas.' !!}
      <br><br>
      <strong>Aceptación del conductor:</strong> {{ $data['he_leido_acepto'] }}
    </div>

    <!-- Firma de Entrega -->
    <table class="signatures">
      <tr>
        <td>
          <div class="signature-line">
            @if(str_starts_with($data['firma_entrega'] ?? '', 'data:image'))
              <img src="{{ $data['firma_entrega'] }}" class="signature-img" />
            @else
              <em>{{ $data['firma_entrega'] }}</em>
            @endif
          </div>
          <strong>Firma del Conductor que Recibe</strong>
        </td>
      </tr>
    </table>

    <div class="footer">
      Documento generado automáticamente por SAEP Platform vía Kizeo Forms Webhook — {{ now()->format('d/m/Y H:i') }} — © {{ date('Y') }} SAEP
    </div>
</body>
</html>
