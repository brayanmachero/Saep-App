<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; }

.top-bar   { height: 8px; background: #0056b3; }
.header    { padding: 18px 28px 14px; display: table; width: 100%; border-bottom: 2px solid #e2e8f0; }
.hdr-logo  { display: table-cell; vertical-align: middle; width: 130px; }
.hdr-logo img { max-height: 44px; max-width: 120px; }
.hdr-title { display: table-cell; vertical-align: middle; text-align: center; }
.hdr-title h1 { font-size: 14px; font-weight: 900; color: #0056b3; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.hdr-title p  { font-size: 9px; color: #2563eb; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; }
.hdr-folio { display: table-cell; vertical-align: middle; text-align: right; width: 130px; }
.hdr-folio span { font-size: 8px; color: #94a3b8; display: block; }
.hdr-folio strong { font-size: 10px; color: #0f172a; }
.hdr-folio .version { font-size: 7.5px; color: #94a3b8; margin-top: 3px; }

.info-grid { display: table; width: 100%; margin: 10px 0; }
.info-cell { display: table-cell; width: 25%; padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; vertical-align: top; border-left: 3px solid #cbd5e1; }
.info-cell .lbl { font-size: 7.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 3px; }
.info-cell .val { font-size: 10px; font-weight: 700; color: #0f172a; }

.section-bar { margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0; }
.section-bar-inner { display: table; width: 100%; }
.section-indicator { display: table-cell; width: 4px; background: #0056b3; border-radius: 2px; }
.section-title { display: table-cell; vertical-align: middle; padding-left: 8px; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #1e293b; letter-spacing: 0.5px; }

/* Contenido en dos columnas */
.content-cols { display: table; width: 100%; margin-top: 6px; }
.content-col  { display: table-cell; width: 50%; vertical-align: top; padding: 0 6px; }
.legal-point  { margin-bottom: 8px; }
.legal-point h3 { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #0056b3; margin-bottom: 3px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; }
.legal-point ul { font-size: 9px; line-height: 1.45; color: #475569; list-style-type: disc; margin-left: 12px; }
.legal-point ul.risk { color: #b91c1c; font-weight: 600; }
.legal-point ul.prohib { color: #c2410c; font-weight: 600; }

.contenido-box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px 12px; font-size: 9px; line-height: 1.65; color: #334155; background: #f8fafc; white-space: pre-wrap; word-wrap: break-word; }

/* Relator signatures */
.relatores-row { display: table; width: 100%; border-collapse: collapse; margin-top: 6px; }
.relator-col   { display: table-cell; text-align: center; padding: 10px 14px; border: 1px solid #e2e8f0; vertical-align: bottom; width: 33.33%; background: #fff; }
.relator-sig   { height: 65px; display: block; margin-bottom: 4px; text-align: center; }
.relator-sig img { max-height: 58px; max-width: 140px; }
.relator-line  { border-top: 1.5px solid #0f172a; width: 80%; margin: 0 auto 4px; }
.relator-name  { font-size: 10px; font-weight: 800; color: #0f172a; text-transform: uppercase; }
.relator-role  { font-size: 8.5px; color: #2563eb; font-weight: 700; margin-top: 2px; }
.relator-rut   { font-size: 7.5px; color: #94a3b8; margin-top: 2px; }

/* Asistentes table */
.asistentes-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
.asistentes-table th { background: #0056b3; color: #fff; font-size: 8px; font-weight: 700; padding: 6px 8px; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
.asistentes-table td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.asistentes-table tr:nth-child(even) td { background: #f8fafc; }
.sig-cell img { max-height: 38px; max-width: 85px; }
.chip-firmado  { background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 3px; font-size: 7.5px; font-weight: 700; }
.chip-pending  { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 3px; font-size: 7.5px; font-weight: 700; }

/* Footer */
.footer { margin-top: 16px; background: #0f172a; color: #94a3b8; padding: 12px 18px; font-size: 7.5px; border-radius: 4px; }
.footer-row { display: table; width: 100%; }
.footer-cell { display: table-cell; padding: 3px 8px; vertical-align: top; }
.footer-hash { font-family: monospace; font-size: 6.5px; word-break: break-all; background: rgba(255,255,255,0.05); padding: 4px 6px; border-radius: 3px; margin-top: 3px; display: block; }
.footer-bottom { display: table; width: 100%; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); }
.footer-bottom-cell { display: table-cell; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; }

.page-break { page-break-after: always; }
</style>
</head>
<body>

<div class="top-bar"></div>

<!-- Header -->
<div class="header">
    <div class="hdr-logo">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
    </div>
    <div class="hdr-title">
        <h1>Acta de Capacitación ODI</h1>
        <p>Derecho a Saber — Art. 21 DS 40 / Ley 16.744</p>
    </div>
    <div class="hdr-folio">
        <span>Folio</span>
        <strong>SAEP-{{ date('Y') }}-{{ str_pad($charla->id, 4, '0', STR_PAD_LEFT) }}</strong>
        <span class="version">Versión 3.0 ({{ now()->format('F Y') }})</span>
    </div>
</div>

<!-- 1. Antecedentes de la Actividad -->
@php
    $tipoLabel = ['CHARLA_5MIN'=>'Charla 5 Min','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'];
@endphp

<div style="padding: 0 24px;">

    <div class="section-bar">
        <div class="section-bar-inner">
            <div class="section-indicator">&nbsp;</div>
            <div class="section-title">1. Antecedentes de la Actividad</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-cell">
            <div class="lbl">Título Actividad</div>
            <div class="val">{{ $charla->titulo }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Centro de Trabajo</div>
            <div class="val">{{ $charla->centroCosto->nombre ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Fecha / Hora</div>
            <div class="val">{{ $charla->fecha_programada->format('d-m-Y') }} / {{ $charla->fecha_programada->format('H:i') }} hrs</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Duración</div>
            <div class="val">{{ $charla->duracion_minutos }} Minutos</div>
        </div>
    </div>
    <div class="info-grid">
        <div class="info-cell">
            <div class="lbl">Tipo</div>
            <div class="val">{{ $tipoLabel[$charla->tipo] ?? $charla->tipo }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Lugar</div>
            <div class="val">{{ $charla->lugar ?: '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Supervisor</div>
            <div class="val">{{ $charla->supervisor->name ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Estado</div>
            <div class="val">{{ $charla->estadoBadge['label'] }}</div>
        </div>
    </div>

    <!-- 2. Contenido -->
    @if($charla->contenido)
    <div class="section-bar">
        <div class="section-bar-inner">
            <div class="section-indicator">&nbsp;</div>
            <div class="section-title">2. Contenidos del Procedimiento Técnico de Trabajo Seguro (PTS)</div>
        </div>
    </div>
    <div class="contenido-box">{{ $charla->contenido }}</div>
    @endif

    <!-- 3. Firmas de Relatores -->
    @if($charla->relatores->count())
    <div class="section-bar">
        <div class="section-bar-inner">
            <div class="section-indicator">&nbsp;</div>
            <div class="section-title">3. Registro de Firmas y Responsabilidades</div>
        </div>
    </div>
    <div class="relatores-row">
        @foreach($charla->relatores->take(3) as $rel)
        <div class="relator-col">
            <div class="relator-sig">
                @if($rel->firma_imagen)
                    <img src="{{ $rel->firma_imagen }}" alt="firma">
                @else
                    <div style="font-size:8px;color:#cbd5e1;font-style:italic;padding-top:25px;">Firma Pendiente</div>
                @endif
            </div>
            <div class="relator-line"></div>
            <div class="relator-name">{{ $rel->usuario->name }}</div>
            <div class="relator-role">{{ $rel->rolLabel }}</div>
            @if($rel->estado === 'FIRMADO' && $rel->fecha_firma)
            <div class="relator-rut">Firmado {{ \Carbon\Carbon::parse($rel->fecha_firma)->format('d/m/Y H:i') }}</div>
            @else
            <div class="relator-rut" style="color:#f59e0b;">Firma pendiente</div>
            @endif
        </div>
        @endforeach
        @for($pad = $charla->relatores->count(); $pad < 3; $pad++)
        <div class="relator-col">
            <div class="relator-sig">&nbsp;</div>
            <div class="relator-line"></div>
            <div class="relator-rut" style="color:#e2e8f0;">&nbsp;</div>
        </div>
        @endfor
    </div>
    @endif

    <!-- 4. Listado de Asistentes -->
    <div class="section-bar">
        <div class="section-bar-inner">
            <div class="section-indicator">&nbsp;</div>
            <div class="section-title">
                4. Listado de Asistentes
                ({{ $charla->asistentes->where('estado','FIRMADO')->count() }}/{{ $charla->asistentes->count() }} firmados)
            </div>
        </div>
    </div>

    @if($charla->asistentes->count())
    <table class="asistentes-table">
        <thead>
            <tr>
                <th style="width:22px;">#</th>
                <th>Nombre</th>
                <th>Cargo</th>
                <th style="width:55px;text-align:center;">Estado</th>
                <th style="width:75px;">Fecha Firma</th>
                <th style="width:75px;text-align:center;">Firma</th>
            </tr>
        </thead>
        <tbody>
            @foreach($charla->asistentes as $i => $asis)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:700;">{{ $asis->usuario->name }}</td>
                <td style="color:#64748b;">{{ $asis->usuario->rol->nombre ?? '—' }}</td>
                <td style="text-align:center;">
                    @if($asis->estado === 'FIRMADO')
                        <span class="chip-firmado">Firmado</span>
                    @else
                        <span class="chip-pending">Pendiente</span>
                    @endif
                </td>
                <td>
                    {{ $asis->fecha_firma ? \Carbon\Carbon::parse($asis->fecha_firma)->format('d/m/Y H:i') : '—' }}
                </td>
                <td class="sig-cell" style="text-align:center;">
                    @if($asis->firma_imagen)
                        <img src="{{ $asis->firma_imagen }}" alt="firma">
                    @else
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="color:#94a3b8;font-size:9px;padding:8px 0;">Sin asistentes registrados</p>
    @endif

    <!-- Footer Técnico -->
    <div class="footer">
        <div class="footer-row">
            <div class="footer-cell" style="width:40%;">
                <strong style="color:#93c5fd;">Validación Electrónica (Ley 19.799)</strong><br>
                <span style="font-style:italic;opacity:0.7;font-size:7px;">
                    Este documento cuenta con firma electrónica simple. Los datos de IP y timestamp
                    han sido capturados al momento de la firma para garantizar la integridad del registro
                    ante fiscalizaciones de la Dirección del Trabajo (DT).
                </span>
            </div>
            <div class="footer-cell" style="width:35%;">
                @php
                    $firstHash = $charla->asistentes->whereNotNull('documento_hash')->first();
                    $firstSigned = $charla->asistentes->where('estado','FIRMADO')->first();
                @endphp
                @if($firstHash)
                <span style="color:#60a5fa;">HASH:</span>
                <span class="footer-hash">{{ $firstHash->documento_hash }}</span>
                @endif
                @if($firstSigned && $firstSigned->ip_firma)
                <span style="color:#60a5fa;">IP:</span> {{ $firstSigned->ip_firma }}
                @endif
            </div>
            <div class="footer-cell" style="width:25%;text-align:right;">
                <strong style="color:#e2e8f0;">SAEP S.A.</strong><br>
                Folio: SAEP-{{ date('Y') }}-{{ str_pad($charla->id, 4, '0', STR_PAD_LEFT) }}<br>
                Total asistentes: {{ $charla->asistentes->count() }}<br>
                Total firmados: {{ $charla->asistentes->where('estado','FIRMADO')->count() }}<br>
                Impreso: {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-cell" style="text-align:left;">SAEP Platform Security Services</div>
            <div class="footer-bottom-cell" style="text-align:center;">PDR-MOD-PTS-V3</div>
            <div class="footer-bottom-cell" style="text-align:right;">Impreso por sistema</div>
        </div>
    </div>
</div>

</body>
</html>

