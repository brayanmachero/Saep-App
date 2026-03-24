<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; }

.top-bar   { height: 7px; background: #0056b3; }
.header    { padding: 14px 24px 10px; display: table; width: 100%; border-bottom: 2px solid #e2e8f0; }
.hdr-logo  { display: table-cell; vertical-align: middle; width: 120px; }
.hdr-logo img { max-height: 40px; max-width: 110px; }
.hdr-logo-text { font-size: 16px; font-weight: 900; color: #0056b3; letter-spacing: 2px; }
.hdr-title { display: table-cell; vertical-align: middle; text-align: center; }
.hdr-title h1 { font-size: 13px; font-weight: 900; color: #0056b3; text-transform: uppercase; letter-spacing: 0.5px; }
.hdr-title p  { font-size: 9px; color: #64748b; margin-top: 2px; }
.hdr-folio { display: table-cell; vertical-align: middle; text-align: right; width: 110px; }
.hdr-folio span { font-size: 8px; color: #94a3b8; display: block; }
.hdr-folio strong { font-size: 10px; color: #0f172a; }

.info-grid { display: table; width: 100%; margin: 10px 0; }
.info-cell { display: table-cell; width: 25%; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; vertical-align: top; }
.info-cell .lbl { font-size: 7.5px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 2px; }
.info-cell .val { font-size: 10px; font-weight: 700; color: #0f172a; }

.section-title { font-size: 9px; font-weight: 900; text-transform: uppercase; color: #0056b3; letter-spacing: 0.8px; border-bottom: 1.5px solid #0056b3; padding-bottom: 3px; margin: 12px 0 6px; }

.contenido-box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; font-size: 9px; line-height: 1.65; color: #334155; background: #f8fafc; white-space: pre-wrap; word-wrap: break-word; }

/* Relator columns */
.relatores-row { display: table; width: 100%; border-collapse: collapse; }
.relator-col   { display: table-cell; text-align: center; padding: 8px 12px; border: 1px solid #e2e8f0; vertical-align: bottom; width: 33.33%; }
.relator-sig   { height: 60px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: 4px; }
.relator-sig img { max-height: 55px; max-width: 140px; object-fit: contain; }
.relator-line  { border-top: 1.5px solid #0f172a; width: 80%; margin: 0 auto 4px; }
.relator-name  { font-size: 9px; font-weight: 700; color: #0f172a; }
.relator-role  { font-size: 8px; color: #64748b; margin-top: 2px; }
.relator-rut   { font-size: 7.5px; color: #94a3b8; margin-top: 1px; }

/* Asistentes table */
.asistentes-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
.asistentes-table th { background: #0056b3; color: #fff; font-size: 8px; font-weight: 700; padding: 5px 6px; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
.asistentes-table td { padding: 5px 6px; font-size: 9px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.asistentes-table tr:nth-child(even) td { background: #f8fafc; }
.sig-cell img { max-height: 35px; max-width: 80px; }
.chip-firmado  { background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 3px; font-size: 7.5px; font-weight: 700; }
.chip-pending  { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 3px; font-size: 7.5px; font-weight: 700; }

.footer { margin-top: 14px; background: #0f172a; color: #94a3b8; padding: 8px 14px; font-size: 7.5px; border-radius: 4px; }
.footer-row { display: table; width: 100%; }
.footer-cell { display: table-cell; padding: 2px 6px; vertical-align: top; }

.page-break { page-break-after: always; }
</style>
</head>
<body>

<div class="top-bar"></div>

<!-- Header -->
<div class="header">
    <div class="hdr-logo">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
        <div class="hdr-logo-text" style="display:none;">SAEP</div>
    </div>
    <div class="hdr-title">
        <h1>Acta de Capacitación — Registro ODI</h1>
        <p>Art. 21 DS 40 / Ley 16.744 — Seguridad y Salud en el Trabajo</p>
    </div>
    <div class="hdr-folio">
        <span>Folio</span>
        <strong>#{{ str_pad($charla->id, 5, '0', STR_PAD_LEFT) }}</strong>
        <span style="margin-top:4px;">{{ now()->format('d/m/Y') }}</span>
    </div>
</div>

<!-- Info grid -->
@php
    $tipoLabel = ['CHARLA_5MIN'=>'Charla 5 Min','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'];
    $badge = $charla->estadoBadge;
@endphp
<div style="padding: 0 24px;">
    <div class="info-grid">
        <div class="info-cell">
            <div class="lbl">Título</div>
            <div class="val" style="font-size:9.5px;">{{ $charla->titulo }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Tipo</div>
            <div class="val">{{ $tipoLabel[$charla->tipo] ?? $charla->tipo }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Fecha</div>
            <div class="val">{{ $charla->fecha_programada->format('d/m/Y') }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Duración</div>
            <div class="val">{{ $charla->duracion_minutos }} minutos</div>
        </div>
    </div>
    <div class="info-grid">
        @if($charla->centroCosto)
        <div class="info-cell">
            <div class="lbl">Centro de Costo</div>
            <div class="val">{{ $charla->centroCosto->nombre }}</div>
        </div>
        @endif
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
            <div class="val">{{ $badge['label'] }}</div>
        </div>
    </div>

    <!-- Contenido -->
    @if($charla->contenido)
    <div class="section-title">Contenido / Temario</div>
    <div class="contenido-box">{{ $charla->contenido }}</div>
    @endif

    <!-- Relatores firmas -->
    @if($charla->relatores->count())
    <div class="section-title">Relatores / Instructores</div>
    <div class="relatores-row">
        @foreach($charla->relatores->take(3) as $rel)
        <div class="relator-col">
            <div class="relator-sig">
                @if($rel->firma_imagen)
                    <img src="{{ $rel->firma_imagen }}" alt="firma">
                @else
                    <div style="font-size:8px;color:#cbd5e1;font-style:italic;">Sin firma</div>
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

    <!-- Asistentes tabla -->
    <div class="section-title">
        Listado de Asistentes
        ({{ $charla->asistentes->where('estado','FIRMADO')->count() }}/{{ $charla->asistentes->count() }} firmados)
    </div>
    @if($charla->asistentes->count())
    <table class="asistentes-table">
        <thead>
            <tr>
                <th style="width:22px;">#</th>
                <th>Nombre</th>
                <th>Cargo</th>
                <th style="width:55px;text-align:center;">Estado</th>
                <th style="width:65px;">Fecha Firma</th>
                <th style="width:70px;text-align:center;">Firma</th>
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

    <!-- Footer -->
    <div class="footer" style="margin-top:16px;">
        <div class="footer-row">
            <div class="footer-cell" style="width:33%;">
                <strong style="color:#e2e8f0;">SAEP Servicios de Asesorías a Empresas Ltda.</strong><br>
                Documento generado el {{ now()->format('d/m/Y H:i:s') }}<br>
                Art. 21 DS 40 / Ley 16.744
            </div>
            <div class="footer-cell" style="width:33%;text-align:center;">
                @php
                    $firstHash = $charla->asistentes->whereNotNull('documento_hash')->first();
                @endphp
                @if($firstHash)
                Hash SHA-256:<br>
                <span style="font-family:monospace;font-size:6.5px;word-break:break-all;">{{ substr($firstHash->documento_hash,0,40) }}...</span>
                @endif
            </div>
            <div class="footer-cell" style="width:33%;text-align:right;">
                Folio: #{{ str_pad($charla->id, 5, '0', STR_PAD_LEFT) }}<br>
                Total asistentes: {{ $charla->asistentes->count() }}<br>
                Total firmados: {{ $charla->asistentes->where('estado','FIRMADO')->count() }}
            </div>
        </div>
    </div>
</div>

</body>
</html>

