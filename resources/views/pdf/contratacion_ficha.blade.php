<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }

/* padding-bottom deja espacio para el footer fijo */
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; padding-bottom: 32px; }

.top-bar { height: 8px; background: #0056b3; }

/* Header usa tabla HTML, más fiable en DomPDF */
.header { padding: 18px 28px 14px; border-bottom: 2px solid #e2e8f0; }
.hdr-title { text-align: center; }
.hdr-title h1 { font-size: 15px; font-weight: 900; color: #0056b3; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.hdr-title p  { font-size: 9px; color: #2563eb; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; }
.hdr-folio { text-align: right; white-space: nowrap; width: 130px; }
.hdr-folio span { font-size: 8px; color: #94a3b8; display: block; }
.hdr-folio strong { font-size: 11px; color: #0f172a; }

/* info-grid: tabla HTML real para evitar problemas de DomPDF con display:table */
.lbl { font-size: 7.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 3px; }
.val { font-size: 10.5px; font-weight: 700; color: #0f172a; }
.estado-chip { display: inline-block; padding: 2px 10px; border-radius: 3px; font-size: 9px; font-weight: 700; }

.section-bar { margin: 16px 28px 8px; padding-bottom: 5px; border-bottom: 2px solid #e2e8f0; }
.section-title { font-size: 10.5px; font-weight: 900; text-transform: uppercase; color: #0056b3; letter-spacing: 0.5px; padding-left: 8px; border-left: 4px solid #0056b3; }

.doc-label { font-size: 8.5px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; padding: 4px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #0056b3; }
.doc-img { text-align: center; padding: 6px; border: 1px solid #e2e8f0; background: #fff; }
.doc-img img { max-width: 100%; max-height: 210px; }
.doc-pdf-note { padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe; font-size: 9px; color: #1d4ed8; }
.doc-ausente  { padding: 10px 14px; background: #fef9c3; border: 1px solid #fde68a; font-size: 9px; color: #92400e; }

/* Footer fijo: DomPDF lo repite al pie de CADA página */
.footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 6px 28px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
    font-size: 7.5px;
    color: #94a3b8;
}
.footer table { width: 100%; border-collapse: collapse; }
.footer-left  { vertical-align: middle; }
.footer-right { text-align: right; vertical-align: middle; }
</style>
</head>
<body>

{{-- Footer fijo — DomPDF lo renderiza al pie de CADA página --}}
<div class="footer">
    <table><tr>
        <td class="footer-left">SAEP — Generado el {{ now()->format('d/m/Y H:i') }}</td>
        <td class="footer-right">Folio {{ $postulante->folio }} | {{ $postulante->nombre }}</td>
    </tr></table>
</div>

<div class="top-bar"></div>

{{-- Header --}}
<table style="width:100%; padding: 18px 28px 14px; border-bottom: 2px solid #e2e8f0; border-collapse:collapse;">
    <tr>
        <td class="hdr-title">
            <h1>Ficha de Postulante</h1>
            <p>Proceso de Contratación — SAEP</p>
        </td>
        <td class="hdr-folio">
            <span>Folio</span>
            <strong>{{ $postulante->folio }}</strong>
            <span style="margin-top:4px;">{{ $postulante->created_at->format('d/m/Y') }}</span>
        </td>
    </tr>
</table>

{{-- Datos personales --}}
<table style="width:100%; margin: 14px 0 0; border-collapse:collapse; padding: 0 28px;">
    <tr>
        <td style="width:35%; padding:9px 12px; background:#f1f5f9; border:1px solid #e2e8f0; border-left:3px solid #0056b3; vertical-align:top;">
            <div class="lbl">Nombre Completo</div>
            <div class="val">{{ $postulante->nombre }}</div>
        </td>
        <td style="width:20%; padding:9px 12px; background:#f1f5f9; border:1px solid #e2e8f0; border-left:3px solid #0056b3; vertical-align:top;">
            <div class="lbl">RUT</div>
            <div class="val">{{ $postulante->rut }}</div>
        </td>
        <td style="width:30%; padding:9px 12px; background:#f1f5f9; border:1px solid #e2e8f0; border-left:3px solid #0056b3; vertical-align:top;">
            <div class="lbl">Correo Electrónico</div>
            <div class="val">{{ $postulante->email }}</div>
        </td>
        <td style="width:15%; padding:9px 12px; background:#f1f5f9; border:1px solid #e2e8f0; border-left:3px solid {{ $postulante->estado_color }}; vertical-align:top;">
            <div class="lbl">Estado</div>
            <div class="val">
                <span class="estado-chip" style="background:{{ $postulante->estado_color }}22; color:{{ $postulante->estado_color }}; border:1px solid {{ $postulante->estado_color }}55;">
                    {{ $postulante->estado_label }}
                </span>
            </div>
        </td>
    </tr>
</table>

@if($postulante->observaciones)
<div style="margin: 10px 28px 0; padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-left:3px solid #64748b; font-size:9px; color:#475569;">
    <strong style="font-size:8px; text-transform:uppercase; color:#94a3b8;">Observaciones RRHH:</strong><br>
    {{ $postulante->observaciones }}
</div>
@endif

@php
    // Separar carnet frontal/reverso del resto de documentos
    // y dentro del resto, separar imágenes de PDFs/ausentes
    $carnetFrontal = null;
    $carnetReverso = null;
    $otrosDocs     = [];
    foreach ($documentos as $doc) {
        $ll = strtolower($doc['label']);
        if (str_contains($ll, 'frontal')) {
            $carnetFrontal = $doc;
        } elseif (str_contains($ll, 'reverso')) {
            $carnetReverso = $doc;
        } else {
            $otrosDocs[] = $doc;
        }
    }
    // Separar en dos grupos: los que son PDF/ausente (notas inline) y los que son imagen (página completa)
    $otrosInline  = array_filter($otrosDocs, fn($d) => $d['tipo'] !== 'imagen');
    $otrosImagen  = array_filter($otrosDocs, fn($d) => $d['tipo'] === 'imagen');
@endphp

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- PÁGINA 1 — Cédula de identidad (frontal + reverso) una bajo la otra --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@if(empty($documentos))
    <div class="doc-ausente" style="margin:12px 28px 0;">Este postulante aún no ha subido ningún documento.</div>
@else
    <div class="section-bar" style="margin-top:14px;">
        <span class="section-title">Cédula de Identidad</span>
    </div>

    @foreach([$carnetFrontal, $carnetReverso] as $carnet)
        @if($carnet)
            <div style="margin: 0 28px 8px;">
                <div class="doc-label">{{ $carnet['label'] }}</div>
                @if($carnet['tipo'] === 'imagen')
                    <div style="text-align:center; padding:4px; border:1px solid #e2e8f0; background:#fff;">
                        <img src="{{ $carnet['data'] }}" alt="{{ $carnet['label'] }}"
                             style="max-width:100%; max-height:380px; width:auto; height:auto;">
                    </div>
                @elseif($carnet['tipo'] === 'pdf')
                    <div class="doc-pdf-note">Documento adjunto en páginas siguientes.</div>
                @else
                    <div class="doc-ausente">Documento no subido.</div>
                @endif
            </div>
        @endif
    @endforeach

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- PÁGINA 1 (cont.) — Notas de docs PDF/ausentes inline               --}}
    {{-- Se muestran en la misma página que los carnets, sin salto forzado  --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if(!empty($otrosInline))
        <div class="section-bar" style="margin-top:12px;">
            <span class="section-title">Otros Documentos</span>
        </div>
        @foreach($otrosInline as $doc)
            <div style="margin: 6px 28px 0;">
                <div class="doc-label" style="margin-bottom:4px;">{{ $doc['label'] }}</div>
                @if($doc['tipo'] === 'pdf')
                    <div class="doc-pdf-note">Documento adjunto en páginas siguientes.</div>
                @else
                    <div class="doc-ausente">Documento no subido.</div>
                @endif
            </div>
        @endforeach
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- PÁGINAS 2+ — Solo imágenes, cada una en hoja completa             --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @foreach($otrosImagen as $doc)
        <div style="page-break-before: always; margin: 0; padding: 8px 28px 0;">
            <div class="doc-label" style="margin-bottom:8px;">{{ $doc['label'] }}</div>
            <div style="text-align:center; padding:4px 0;">
                <img src="{{ $doc['data'] }}" alt="{{ $doc['label'] }}"
                     style="max-width:100%; max-height:1020px; width:auto; height:auto;">
            </div>
        </div>
    @endforeach
@endif

</body>
</html>
