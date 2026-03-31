<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acta de Entrega de Vehículo – {{ $data['patente'] }}</title>
<style>
    @page { margin: 18mm 15mm 22mm 15mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1a202c; margin: 0; padding: 0; line-height: 1.45; }
    .header-wrap { border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 12px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: top; }
    .logo { max-height: 48px; width: auto; }
    .company-info { font-size: 8px; color: #374151; line-height: 1.6; margin-top: 4px; }
    .company-info strong { font-size: 9px; color: #1e3a8a; }
    .doc-meta { text-align: right; }
    .doc-meta h1 { margin: 0 0 3px; font-size: 15px; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; }
    .folio-box { border: 1.5px solid #1e40af; border-radius: 4px; padding: 3px 10px; display: inline-block; margin-top: 4px; }
    .folio-label { font-size: 7px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
    .folio-value { font-size: 11px; font-weight: bold; color: #1e3a8a; letter-spacing: 1px; }
    .section-title { background-color: #1e40af; color: #fff; padding: 6px 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; margin: 14px 0 7px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .data-table th, .data-table td { border: 1px solid #dde1ea; padding: 6px 9px; font-size: 9px; text-align: left; }
    .data-table th { background-color: #f0f4ff; font-weight: bold; width: 38%; color: #374151; }
    .data-table td { color: #1a202c; }
    .decl-box { border: 1px solid #bfdbfe; background: #eff6ff; padding: 10px 12px; font-size: 9px; text-align: justify; line-height: 1.6; border-radius: 4px; margin-bottom: 12px; }
    .decl-title { font-weight: bold; color: #1e40af; font-size: 9.5px; margin-bottom: 5px; }
    .signatures-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
    .signatures-table td { width: 48%; text-align: center; vertical-align: bottom; padding: 0 8px; }
    .sig-block { border: 1px solid #d1d5db; border-radius: 4px; padding: 6px; min-height: 78px; text-align: center; }
    .sig-img { max-height: 65px; max-width: 160px; }
    .sig-line { border-bottom: 1.5px solid #374151; width: 80%; margin: 44px auto 5px; }
    .sig-name { font-weight: bold; font-size: 9px; color: #1a202c; margin-top: 4px; }
    .sig-role { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .sig-rut  { font-size: 8px; color: #374151; margin-top: 1px; }
    .fes-box { border: 1px dashed #6b7280; padding: 7px 10px; border-radius: 4px; margin-top: 18px; font-size: 8px; color: #4b5563; line-height: 1.5; }
    .footer { text-align: center; font-size: 7.5px; color: #9ca3af; margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    .media-img { max-height: 130px; width: auto; border: 1px solid #cbd5e1; border-radius: 3px; }
    .clause { margin-bottom: 6px; text-align: justify; line-height: 1.55; }
    .clause-num { font-weight: bold; color: #1e40af; }
    .clause-title { font-weight: bold; text-transform: uppercase; font-size: 8.5px; }
</style>
</head>
<body>

@php
    $clean = function($val) {
        if ($val === '-' || $val === null || $val === '') return '-';
        if (is_string($val) && (str_starts_with(trim($val), '{') || str_starts_with(trim($val), '['))) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                if (isset($decoded['hidden']) && $decoded['hidden'] === true) return '-';
                if (isset($decoded['result']) && $decoded['result'] === null) return '-';
            }
        }
        return $val;
    };
@endphp

<div class="header-wrap">
  <table class="header-table">
    <tr>
      <td style="width:38%">
        <img src="{{ $logoUrl }}" class="logo" alt="Logo SAEP"/>
        <div class="company-info">
          <strong>{{ $data['empresa_razon_social'] }}</strong><br>
          @if(!empty($data['empresa_rut']))RUT: {{ $data['empresa_rut'] }}<br>@endif
          @if(!empty($data['empresa_direccion'])){{ $data['empresa_direccion'] }}<br>@endif
          {{ $data['empresa_ciudad'] }}
        </div>
      </td>
      <td class="doc-meta">
        <h1>Acta de Entrega de Vehículo</h1>
        <div class="folio-box">
          <div class="folio-label">N° Documento / Folio</div>
          <div class="folio-value">ENTREGA-{{ strtoupper($data['folio']) }}</div>
        </div>
        <div style="font-size:8px;color:#6b7280;margin-top:5px">
          Emisión: <strong>{{ now()->format('d/m/Y H:i') }}</strong> &nbsp;|&nbsp; Gestión: <strong>{{ $data['gestion'] }}</strong>
        </div>
      </td>
    </tr>
  </table>
</div>

<div class="section-title">1. Datos del Vehículo Entregado</div>
<table class="data-table">
  <tr><th>Marca y Modelo</th><td>{{ $data['marca_modelo'] }}</td></tr>
  <tr><th>Patente / PPU</th><td><strong style="font-size:12px;color:#1e3a8a">{{ $data['patente'] }}</strong></td></tr>
  <tr><th>Kilometraje al Momento de Entrega</th><td>{{ $data['kilometraje_entrega'] }} km</td></tr>
  <tr><th>Kit de Seguridad y Emergencia</th><td>{{ $data['kit_seguridad'] }}</td></tr>
  <tr><th>Fecha y Hora de Entrega</th><td><strong>{{ $data['fecha_hora'] }}</strong></td></tr>
  @if($clean($data['geo_entrega'] ?? '-') !== '-')
  <tr><th>Geolocalización GPS (lat, long)</th><td style="font-family:monospace;font-size:8.5px">{{ $clean($data['geo_entrega']) }}</td></tr>
  @endif
  @if(str_starts_with($data['dibujo'] ?? '', 'data:image'))
  <tr><th>Esquema de Novedades</th><td><img src="{{ $data['dibujo'] }}" class="media-img"/></td></tr>
  @endif
</table>

<div class="section-title">2. Identificación del Conductor Receptor</div>
<table class="data-table">
  <tr>
    <th>Nombre Completo del Conductor</th>
    <td><strong>{{ $data['conductor_nombre'] !== '-' ? $data['conductor_nombre'] : '(No registrado)' }}</strong></td>
  </tr>
  @if(($data['conductor_rut'] ?? '-') !== '-')
  <tr><th>RUT del Conductor</th><td>{{ $data['conductor_rut'] }}</td></tr>
  @endif
  <tr><th>Empleador</th><td>{{ $data['empresa_razon_social'] }}@if(!empty($data['empresa_rut'])) &nbsp;·&nbsp; RUT {{ $data['empresa_rut'] }}@endif</td></tr>
</table>

<div class="section-title">3. Declaración de Recepción, Custodia y Responsabilidad del Vehículo</div>
<div class="decl-box">
  <div class="decl-title">El conductor declara bajo juramento:</div>
  <p style="margin:0 0 6px;text-align:justify;line-height:1.55">
    Por el presente documento, y mediante la firma digital o manuscrita adjunta, declaro haber recibido de parte de la empresa
    <strong>{{ $data['empresa_razon_social'] }}</strong> el vehículo individualizado en los apartados anteriores, así como sus
    correspondientes accesorios, herramientas, neumático de repuesto, extintor y documentación legal exigida, en el estado de
    conservación y funcionamiento que se detalla en la presente inspección.
  </p>
  <p style="margin:0 0 8px;text-align:justify;font-weight:bold;font-size:9px">
    Al recibir la custodia de este vehículo, asumo y acepto expresamente las siguientes condiciones y responsabilidades:
  </p>

  <div class="clause">
    <span class="clause-num">1.</span> <span class="clause-title">Cumplimiento de la Ley de Tránsito Nacional:</span>
    Me comprometo a conducir el vehículo respetando íntegramente la Ley de Tránsito (Ley N° 18.290) y demás normativas viales vigentes en el territorio de la República de Chile. Reconozco que {{ $data['empresa_razon_social'] }} queda totalmente eximida de cualquier responsabilidad civil, penal o infraccional derivada de mi conducción.
  </div>

  <div class="clause">
    <span class="clause-num">2.</span> <span class="clause-title">Responsabilidad Exclusiva del Conductor:</span>
    Asumo la total y exclusiva responsabilidad legal y económica frente a terceros, autoridades y ante la propia empresa, por cualquier accidente, siniestro, daño, atropello o eventualidad que se produzca como consecuencia de:
    <br>• Conducir bajo los efectos del alcohol, drogas o estupefacientes (Ley Emilia / Ley Tolerancia Cero).
    <br>• Evadir, huir o desobedecer instrucciones de Carabineros de Chile, PDI o Inspectores Fiscales.
    <br>• Conducir a exceso de velocidad o realizar maniobras temerarias comprobables.
    <br>• Cualquier otra negligencia grave, dolo o uso del vehículo para fines ajenos a los autorizados.
  </div>

  <div class="clause">
    <span class="clause-num">3.</span> <span class="clause-title">Multas e Infracciones de Tránsito (TAG, Parquímetros, Empadronadas):</span>
    Acepto que cualquier multa empadronada, infracción por mal estacionamiento, cobros de vías exclusivas, evasión de pórticos TAG o partes cursados por Juzgados de Policía Local durante el período en que el vehículo se encuentra bajo mi registro y custodia, son de mi absoluta responsabilidad económica.
  </div>

  <div class="clause">
    <span class="clause-num">4.</span> <span class="clause-title">Custodia de Equipamiento y Accesorios:</span>
    Me constituyo como depositario y custodio del vehículo y de todos los implementos de seguridad y accesorios entregados con él (incluyendo, pero no limitado a: neumático de repuesto, gata hidráulica, llave de ruedas, extintor de incendios, botiquín, chaleco reflectante, radio y documentos). La pérdida, extravío, robo o hurto de estos elementos por dejar el vehículo abierto, sin seguros, o por negligencia en su cuidado, será de mi exclusiva responsabilidad.
  </div>

  <div class="clause">
    <span class="clause-num">5.</span> <span class="clause-title">Exención de Responsabilidad a la Empresa:</span>
    Declaro que {{ $data['empresa_razon_social'] }} no será responsable bajo ninguna circunstancia por la pérdida o sustracción de objetos personales de valor, dinero o especies que yo, o cualquier acompañante, dejemos al interior del vehículo.
  </div>

  <p style="margin:8px 0 0;text-align:justify;line-height:1.55;font-style:italic;font-size:8.5px;border-top:1px solid #bfdbfe;padding-top:6px">
    Al firmar este documento electrónico, declaro haber leído, comprendido y aceptado a cabalidad cada uno de los puntos anteriores, constituyendo este registro prueba suficiente de mi consentimiento para todos los fines legales, laborales y administrativos que {{ $data['empresa_razon_social'] }} estime convenientes.
  </p>
  <br>
  <strong>Aceptación expresa:</strong> {{ $data['he_leido_acepto'] !== '-' ? $data['he_leido_acepto'] : 'Sí, he leído, comprendo y acepto las condiciones.' }}
</div>

<div class="section-title">4. Firmas y Conformidad</div>
<table class="signatures-table">
  <tr>
    <td>
      <div class="sig-block">
        @if(str_starts_with($data['firma_entrega'] ?? '', 'data:image'))
          <img src="{{ $data['firma_entrega'] }}" class="sig-img"/>
        @else
          <div class="sig-line"></div>
        @endif
      </div>
      <div class="sig-name">{{ $data['conductor_nombre'] !== '-' ? strtoupper($data['conductor_nombre']) : '________________________________' }}</div>
      @if(($data['conductor_rut'] ?? '-') !== '-')<div class="sig-rut">RUT: {{ $data['conductor_rut'] }}</div>@endif
      <div class="sig-role">Conductor que Recibe el Vehículo</div>
      <div class="sig-role">Fecha: {{ $data['fecha_hora'] }} &nbsp;·&nbsp; Lugar: {{ $data['empresa_ciudad'] }}</div>
    </td>
    <td>
      <div class="sig-block">
        @if(str_starts_with($data['firma_encargado'] ?? '', 'data:image'))
          <img src="{{ $data['firma_encargado'] }}" class="sig-img"/>
        @else
          <div class="sig-line"></div>
        @endif
      </div>
      <div class="sig-name">{{ strtoupper($data['empresa_responsable']) }}</div>
      <div class="sig-role">Representante Empleador / Encargado de Flota</div>
      <div class="sig-role">{{ $data['empresa_razon_social'] }}</div>
      @if(!empty($data['empresa_rut']))<div class="sig-rut">RUT Empresa: {{ $data['empresa_rut'] }}</div>@endif
    </td>
  </tr>
</table>

<div class="fes-box">
  <strong>Validez Legal — Firma Electrónica Simple (FES):</strong> La firma digitalizada del conductor fue capturada mediante dispositivo móvil a través de Kizeo Forms y tiene plena validez en virtud de la <strong>Ley N° 19.799 sobre Documentos Electrónicos, Firma Electrónica y Servicios de Certificación</strong> (D.O. 12.04.2002) y sus reglamentos. El registro incluye geolocalización GPS, marca de tiempo universal y folio único de trazabilidad. <strong>Folio:</strong> ENTREGA-{{ strtoupper($data['folio']) }} &nbsp;|&nbsp; <strong>ID Kizeo:</strong> {{ $data['data_id'] }}.
</div>

<div class="footer">
  Generado por SAEP Platform — Folio: ENTREGA-{{ strtoupper($data['folio']) }} — {{ now()->format('d/m/Y H:i:s') }} ({{ now()->timezoneName }}) — © {{ date('Y') }} {{ $data['empresa_razon_social'] }}<br>
  Instrumento privado con firma electrónica simple — Ley N° 19.799. Conservar en legajo de personal del conductor.
</div>

</body>
</html>
