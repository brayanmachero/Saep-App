<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; }

.top-bar { height: 8px; background: #0056b3; }
.header { padding: 18px 28px 14px; display: table; width: 100%; border-bottom: 2px solid #e2e8f0; }
.hdr-title { display: table-cell; vertical-align: middle; text-align: center; }
.hdr-title h1 { font-size: 15px; font-weight: 900; color: #0056b3; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.hdr-title p  { font-size: 9px; color: #2563eb; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; }
.hdr-folio { display: table-cell; vertical-align: middle; text-align: right; width: 150px; }
.hdr-folio span { font-size: 8px; color: #94a3b8; display: block; }
.hdr-folio strong { font-size: 11px; color: #0f172a; }

.info-grid { display: table; width: 100%; margin: 14px 0 0; border-collapse: collapse; }
.info-cell { display: table-cell; padding: 9px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; vertical-align: top; border-left: 3px solid #0056b3; }
.info-cell .lbl { font-size: 7.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 3px; }
.info-cell .val { font-size: 10.5px; font-weight: 700; color: #0f172a; }

.estado-chip { display: inline-block; padding: 2px 10px; border-radius: 3px; font-size: 9px; font-weight: 700; }

.section-bar { margin: 20px 0 10px; padding-bottom: 5px; border-bottom: 2px solid #e2e8f0; }
.section-title { font-size: 10.5px; font-weight: 900; text-transform: uppercase; color: #0056b3; letter-spacing: 0.5px; padding-left: 8px; border-left: 4px solid #0056b3; }

.doc-block { margin-bottom: 18px; page-break-inside: avoid; }
.doc-label { font-size: 8.5px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; padding: 4px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #0056b3; }
.doc-img { text-align: center; padding: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 3px; }
.doc-img img { max-width: 100%; max-height: 380px; }
.doc-pdf-note { padding: 12px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 3px; font-size: 9px; color: #1d4ed8; }
.doc-ausente { padding: 10px 14px; background: #fef9c3; border: 1px solid #fde68a; border-radius: 3px; font-size: 9px; color: #92400e; }

.footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; display: table; width: 100%; }
.footer-left  { display: table-cell; font-size: 7.5px; color: #94a3b8; vertical-align: bottom; }
.footer-right { display: table-cell; text-align: right; font-size: 7.5px; color: #94a3b8; vertical-align: bottom; }

.docs-cols { display: table; width: 100%; border-spacing: 10px; }
.doc-col   { display: table-cell; width: 50%; vertical-align: top; padding: 0 5px; }
</style>
</head>
<body>

<div class="top-bar"></div>

<div class="header">
    <div class="hdr-title">
        <h1>Ficha de Postulante</h1>
        <p>Proceso de Contratación — SAEP</p>
    </div>
    <div class="hdr-folio">
        <span>Folio</span>
        <strong>{{ $postulante->folio }}</strong>
        <span style="margin-top:4px;">{{ $postulante->created_at->format('d/m/Y') }}</span>
    </div>
</div>

{{-- Datos personales --}}
<div class="info-grid">
    <div class="info-cell" style="width:35%">
        <div class="lbl">Nombre Completo</div>
        <div class="val">{{ $postulante->nombre }}</div>
    </div>
    <div class="info-cell" style="width:20%">
        <div class="lbl">RUT</div>
        <div class="val">{{ $postulante->rut }}</div>
    </div>
    <div class="info-cell" style="width:30%">
        <div class="lbl">Correo Electrónico</div>
        <div class="val">{{ $postulante->email }}</div>
    </div>
    <div class="info-cell" style="width:15%; border-left-color: {{ $postulante->estado_color }}">
        <div class="lbl">Estado</div>
        <div class="val">
            <span class="estado-chip" style="background:{{ $postulante->estado_color }}22; color:{{ $postulante->estado_color }}; border: 1px solid {{ $postulante->estado_color }}55;">
                {{ $postulante->estado_label }}
            </span>
        </div>
    </div>
</div>

@if($postulante->observaciones)
<div style="margin-top:10px; padding: 8px 12px; background:#f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #64748b; font-size:9px; color:#475569;">
    <strong style="font-size:8px; text-transform:uppercase; color:#94a3b8;">Observaciones RRHH:</strong><br>
    {{ $postulante->observaciones }}
</div>
@endif

{{-- Documentos --}}
<div class="section-bar" style="margin-top:22px;">
    <span class="section-title">Documentos Adjuntos</span>
</div>

@if(empty($documentos))
    <div class="doc-ausente">Este postulante aún no ha subido ningún documento.</div>
@else
    {{-- Imágenes en columnas de 2 --}}
    @php
        $imagenes  = array_filter($documentos, fn($d) => $d['tipo'] === 'imagen');
        $pdfs      = array_filter($documentos, fn($d) => $d['tipo'] === 'pdf');
        $ausentes  = array_filter($documentos, fn($d) => $d['tipo'] === 'ausente');
        $imagenes  = array_values($imagenes);
    @endphp

    @if(count($imagenes))
        <div class="docs-cols">
            @foreach($imagenes as $i => $doc)
                @if($i % 2 === 0)
                    @if($i > 0)</div></div>@endif
                    <div class="docs-cols"><div class="doc-col">
                @else
                    </div><div class="doc-col">
                @endif
                    <div class="doc-block">
                        <div class="doc-label">{{ $doc['label'] }}</div>
                        <div class="doc-img">
                            <img src="{{ $doc['data'] }}" alt="{{ $doc['label'] }}">
                        </div>
                    </div>
                @if($loop->last)
                    </div>
                    @if(count($imagenes) % 2 !== 0)<div class="doc-col"></div>@endif
                    </div>
                @endif
            @endforeach
    @endif

    @if(count($pdfs))
        <div class="section-bar" style="margin-top:16px;">
            <span class="section-title" style="font-size:9px; color:#475569; border-left-color:#475569;">Documentos en formato PDF</span>
        </div>
        @foreach($pdfs as $doc)
            <div class="doc-block">
                <div class="doc-label">{{ $doc['label'] }}</div>
                <div class="doc-pdf-note">
                    Documento subido como PDF. Acceder en:
                    {{ $doc['data'] }}
                </div>
            </div>
        @endforeach
    @endif

    @if(count($ausentes))
        <div style="margin-top:10px; font-size:8.5px; color:#94a3b8;">
            <strong>Documentos no subidos:</strong>
            {{ implode(', ', array_column($ausentes, 'label')) }}
        </div>
    @endif
@endif

{{-- Footer --}}
<div class="footer">
    <div class="footer-left">
        SAEP — Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
    <div class="footer-right">
        Folio {{ $postulante->folio }} | {{ $postulante->nombre }}
    </div>
</div>

</body>
</html>
