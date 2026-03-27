<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acta de Devolución de Vehículo</title>
<style>
    @page { margin: 20mm 15mm 25mm 15mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; line-height: 1.4; }
    .header { border-bottom: 3px solid #e11d48; padding-bottom: 10px; margin-bottom: 15px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: middle; }
    .logo { max-height: 50px; width: auto; }
    .title-cell { text-align: right; }
    .title-cell h1 { margin: 0; font-size: 18px; color: #9f1239; text-transform: uppercase; letter-spacing: 1px; }
    .title-cell p { margin: 3px 0 0; font-size: 10px; color: #64748b; }
    .section-title { background-color: #9f1239; color: #ffffff; padding: 7px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin: 15px 0 8px 0; border-radius: 4px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .data-table th, .data-table td { border: 1px solid #fecdd3; padding: 7px 10px; text-align: left; font-size: 10px; }
    .data-table th { background-color: #fff1f2; font-weight: bold; width: 38%; color: #9f1239; }
    .data-table td { color: #1e293b; }
    .notes-box { border: 1px solid #fecdd3; padding: 14px; background-color: #fffbfa; margin-top: 5px; color: #4c0519; font-style: normal; text-align: justify; line-height: 1.5; border-radius: 6px; font-size: 9.5px; }
    .signatures { width: 100%; margin-top: 30px; border-collapse: collapse; }
    .signatures td { width: 50%; text-align: center; padding: 10px; vertical-align: bottom; }
    .signature-line { border-bottom: 1px solid #334155; width: 80%; margin: 0 auto 8px auto; min-height: 50px; line-height: 50px; }
    .signature-img { max-height: 60px; width: auto; }
    .ref-box { font-size: 9px; color: #64748b; margin-bottom: 10px; border: 1px dashed #cbd5e1; padding: 6px 10px; border-radius: 4px; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    .alert-red { color: #dc2626; font-weight: bold; }
</style>
</head>
<body>
    <div class="header">
      <table class="header-table">
        <tr>
          <td><img src="{{ $logoUrl }}" class="logo" alt="Logo SAEP"/></td>
          <td class="title-cell">
            <h1>Acta de Devolución de Vehículo</h1>
            <p><strong>Gestión:</strong> {{ $data['gestion'] }}</p>
            <p><strong>Fecha Autorización:</strong> {{ $data['fecha_hora_devolucion'] }}</p>
          </td>
        </tr>
      </table>
    </div>

    <div class="ref-box">
      <em>Referencia Entrega Original: Entregado el {{ $data['fecha_hora'] }} con kilometraje {{ $data['kilometraje_entrega'] }} km.</em>
    </div>

    <div class="section-title">1. Datos de Devolución / Recepción a Base</div>
    <table class="data-table">
      <tr><th>Marca y Modelo</th><td>{{ $data['marca_modelo'] }}</td></tr>
      <tr><th>Patente / PPU</th><td><strong style="color:#9f1239;font-size:13px;">{{ $data['patente'] }}</strong></td></tr>
      <tr><th>Kilometraje de Devolución</th><td>{{ $data['kilometraje_devolucion'] }} km</td></tr>
      <tr><th>Geolocalización (Coordenadas)</th><td>{{ $data['geo_devolucion'] }}</td></tr>
    </table>

    <div class="section-title">2. Estado y Condiciones (Inspección)</div>
    <table class="data-table">
      <tr><th>¿El vehículo presenta daños NUEVOS?</th><td><strong>{{ $data['danos_nuevos'] }}</strong></td></tr>
      <tr><th>¿Se devuelve el kit COMPLETO?</th><td>{{ $data['kit_completo'] }}</td></tr>
      @if(($data['articulos_faltantes'] ?? '-') !== '-')
      <tr><th>Artículos Faltantes Declarados</th><td class="alert-red">{{ $data['articulos_faltantes'] }}</td></tr>
      @endif
    </table>

    <div class="section-title">3. Observaciones Adicionales</div>
    <div class="notes-box">
      {!! ($data['observaciones_adicionales'] ?? '-') !== '-' ? nl2br(e($data['observaciones_adicionales'])) : 'Sin observaciones ingresadas por el receptor/conductor durante el proceso de devolución.' !!}
    </div>

    <!-- Firmas de Devolución -->
    <table class="signatures">
      <tr>
        <td>
          <div class="signature-line">
            @if(str_starts_with($data['firma_devolucion'] ?? '', 'data:image'))
              <img src="{{ $data['firma_devolucion'] }}" class="signature-img" />
            @else
              <em>{{ $data['firma_devolucion'] }}</em>
            @endif
          </div>
          <strong>Firma de Devolución (Conductor)</strong>
        </td>
        <td>
           <div class="signature-line"></div>
           <strong>Responsable Base (SAEP)</strong>
        </td>
      </tr>
    </table>

    <div class="footer">
      Documento generado automáticamente por SAEP Platform vía Kizeo Forms Webhook — {{ now()->format('d/m/Y H:i') }} — © {{ date('Y') }} SAEP
    </div>
</body>
</html>
