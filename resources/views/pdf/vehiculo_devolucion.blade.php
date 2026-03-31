<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acta de Devolución de Vehículo – {{ $data['patente'] }}</title>
<style>
    @page { margin: 18mm 15mm 22mm 15mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1a202c; margin: 0; padding: 0; line-height: 1.45; }
    .header-wrap { border-bottom: 3px solid #9f1239; padding-bottom: 10px; margin-bottom: 12px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: top; }
    .logo { max-height: 48px; width: auto; }
    .company-info { font-size: 8px; color: #374151; line-height: 1.6; margin-top: 4px; }
    .company-info strong { font-size: 9px; color: #9f1239; }
    .doc-meta { text-align: right; }
    .doc-meta h1 { margin: 0 0 3px; font-size: 15px; color: #9f1239; text-transform: uppercase; letter-spacing: 0.5px; }
    .folio-box { border: 1.5px solid #9f1239; border-radius: 4px; padding: 3px 10px; display: inline-block; margin-top: 4px; }
    .folio-label { font-size: 7px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
    .folio-value { font-size: 11px; font-weight: bold; color: #9f1239; letter-spacing: 1px; }
    .section-title { background-color: #9f1239; color: #fff; padding: 6px 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; margin: 14px 0 7px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .data-table th, .data-table td { border: 1px solid #fecdd3; padding: 6px 9px; font-size: 9px; text-align: left; }
    .data-table th { background-color: #fff1f2; font-weight: bold; width: 38%; color: #9f1239; }
    .data-table td { color: #1a202c; }
    .ref-box { font-size: 8.5px; color: #4b5563; margin-bottom: 10px; border: 1px dashed #fda4af; padding: 6px 10px; border-radius: 4px; background: #fff7f7; }
    .decl-box { border: 1px solid #fecdd3; background: #fff7f7; padding: 10px 12px; font-size: 9px; text-align: justify; line-height: 1.6; border-radius: 4px; margin-bottom: 12px; }
    .decl-title { font-weight: bold; color: #9f1239; font-size: 9.5px; margin-bottom: 5px; }
    .alert-red { color: #dc2626; font-weight: bold; }
    .signatures-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
    .signatures-table td { width: 48%; text-align: center; vertical-align: bottom; padding: 0 8px; }
    .sig-block { border: 1px solid #fecdd3; border-radius: 4px; padding: 6px; min-height: 78px; text-align: center; }
    .sig-img { max-height: 65px; max-width: 160px; }
    .sig-line { border-bottom: 1.5px solid #374151; width: 80%; margin: 44px auto 5px; }
    .sig-name { font-weight: bold; font-size: 9px; color: #1a202c; margin-top: 4px; }
    .sig-role { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .sig-rut  { font-size: 8px; color: #374151; margin-top: 1px; }
    .fes-box { border: 1px dashed #6b7280; padding: 7px 10px; border-radius: 4px; margin-top: 18px; font-size: 8px; color: #4b5563; line-height: 1.5; }
    .footer { text-align: center; font-size: 7.5px; color: #9ca3af; margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>

@php
    // Limpia valores JSON crudos que Kizeo devuelve como hidden/null
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
        <h1>Acta de Devolución de Vehículo</h1>
        <div class="folio-box">
          <div class="folio-label">N° Documento / Folio</div>
          <div class="folio-value">DEVOL-{{ strtoupper($data['folio']) }}</div>
        </div>
        <div style="font-size:8px;color:#6b7280;margin-top:5px">
          Emisión: <strong>{{ now()->format('d/m/Y H:i') }}</strong> &nbsp;|&nbsp; Gestión: <strong>{{ $data['gestion'] }}</strong>
        </div>
      </td>
    </tr>
  </table>
</div>

<div class="ref-box">
  <strong>Referencia Acta de Entrega Original:</strong> Vehículo entregado el <strong>{{ $data['fecha_hora'] }}</strong> con kilometraje inicial de <strong>{{ $data['kilometraje_entrega'] }} km</strong>.
  Recorrido estimado: <strong>{{ (is_numeric(str_replace(['.','km',' '],'',$data['kilometraje_devolucion'])) && is_numeric(str_replace(['.','km',' '],'',$data['kilometraje_entrega']))) ? (intval(str_replace(['.','km',' '],'',$data['kilometraje_devolucion'])) - intval(str_replace(['.','km',' '],'',$data['kilometraje_entrega']))) . ' km' : 'Ver valores de km' }}</strong>.
</div>

<div class="section-title">1. Datos del Vehículo Devuelto</div>
<table class="data-table">
  <tr><th>Marca y Modelo</th><td>{{ $data['marca_modelo'] }}</td></tr>
  <tr><th>Patente / PPU</th><td><strong style="font-size:12px;color:#9f1239">{{ $data['patente'] }}</strong></td></tr>
  <tr><th>Kilometraje al Momento de Devolución</th><td>{{ $data['kilometraje_devolucion'] }} km</td></tr>
  <tr><th>Fecha y Hora de Devolución</th><td><strong>{{ $data['fecha_hora_devolucion'] }}</strong></td></tr>
  @if($clean($data['geo_devolucion'] ?? '-') !== '-')
  <tr><th>Geolocalización GPS (lat, long)</th><td style="font-family:monospace;font-size:8.5px">{{ $clean($data['geo_devolucion']) }}</td></tr>
  @endif
</table>

<div class="section-title">2. Identificación del Conductor que Devuelve</div>
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

<div class="section-title">3. Estado e Inspección del Vehículo</div>
<table class="data-table">
  <tr>
    <th>¿El vehículo presenta daños NUEVOS?</th>
    <td><strong class="{{ $data['danos_nuevos'] === 'Sí' || str_contains(strtolower($data['danos_nuevos']), 'si') ? 'alert-red' : '' }}">{{ $data['danos_nuevos'] }}</strong></td>
  </tr>
  <tr><th>¿Se devuelve el kit COMPLETO?</th><td>{{ $data['kit_completo'] }}</td></tr>
  @if($clean($data['articulos_faltantes'] ?? '-') !== '-')
  <tr><th>Artículos Faltantes Declarados</th><td class="alert-red">{{ $clean($data['articulos_faltantes']) }}</td></tr>
  @endif
</table>

<div class="section-title">4. Observaciones del Conductor / Receptor SAEP</div>
<div class="decl-box">
  <div class="decl-title">Observaciones al momento de devolución:</div>
  {!! ($data['observaciones_adicionales'] ?? '-') !== '-'
      ? nl2br(e($data['observaciones_adicionales']))
      : 'Sin observaciones adicionales registradas por el conductor durante el proceso de devolución. El vehículo se entrega conforme a las condiciones acordadas.' !!}
</div>

<div class="section-title">5. Firmas y Conformidad</div>
<table class="signatures-table">
  <tr>
    <td>
      <div class="sig-block">
        @if(str_starts_with($data['firma_devolucion'] ?? '', 'data:image'))
          <img src="{{ $data['firma_devolucion'] }}" class="sig-img"/>
        @else
          <div class="sig-line"></div>
        @endif
      </div>
      <div class="sig-name">{{ $data['conductor_nombre'] !== '-' ? strtoupper($data['conductor_nombre']) : '________________________________' }}</div>
      @if(($data['conductor_rut'] ?? '-') !== '-')<div class="sig-rut">RUT: {{ $data['conductor_rut'] }}</div>@endif
      <div class="sig-role">Conductor que Devuelve el Vehículo</div>
      <div class="sig-role">Fecha: {{ $data['fecha_hora_devolucion'] }} &nbsp;·&nbsp; Lugar: {{ $data['empresa_ciudad'] }}</div>
    </td>
    <td>
      <div class="sig-block"><div class="sig-line"></div></div>
      <div class="sig-name">{{ strtoupper($data['empresa_responsable']) }}</div>
      <div class="sig-role">Receptor / Encargado de Flota SAEP</div>
      <div class="sig-role">{{ $data['empresa_razon_social'] }}</div>
      @if(!empty($data['empresa_rut']))<div class="sig-rut">RUT Empresa: {{ $data['empresa_rut'] }}</div>@endif
    </td>
  </tr>
</table>

<div class="fes-box">
  <strong>Validez Legal — Firma Electrónica Simple (FES):</strong> La firma digitalizada del conductor fue capturada mediante dispositivo móvil a través de Kizeo Forms y tiene plena validez en virtud de la <strong>Ley N° 19.799 sobre Documentos Electrónicos, Firma Electrónica y Servicios de Certificación</strong> (D.O. 12.04.2002) y sus reglamentos. El registro incluye geolocalización GPS, marca de tiempo universal y folio único de trazabilidad. <strong>Folio:</strong> DEVOL-{{ strtoupper($data['folio']) }} &nbsp;|&nbsp; <strong>ID Kizeo:</strong> {{ $data['data_id'] }}.
</div>

<div class="footer">
  Generado por SAEP Platform — Folio: DEVOL-{{ strtoupper($data['folio']) }} — {{ now()->format('d/m/Y H:i:s') }} ({{ now()->timezoneName }}) — © {{ date('Y') }} {{ $data['empresa_razon_social'] }}<br>
  Instrumento privado con firma electrónica simple — Ley N° 19.799. Conservar en legajo de personal del conductor.
</div>

</body>
</html>
