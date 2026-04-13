<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Formulario #{{ str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) }}</title>
@php
    $folio = 'SAEP-' . str_pad($respuesta->id, 6, '0', STR_PAD_LEFT);
    $hash = strtoupper(substr(hash('sha256', $respuesta->id . $respuesta->created_at . ($respuesta->usuario->email ?? '')), 0, 16));
    $fechaEmision = now()->format('d/m/Y H:i:s');
    $allFields = collect($schema)->filter(fn($f) => $f['type'] !== 'divider')->values();
    $half = (int) ceil($allFields->count() / 2);
    $leftFields = $allFields->slice(0, $half);
    $rightFields = $allFields->slice($half);
@endphp
<style>
    @page { margin: 10mm 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1f2937; background: #fff; }

    .watermark { position: fixed; top: 28%; left: 25%; font-size: 90px; color: rgba(15,27,76,0.025); font-weight: 900; letter-spacing: 18px; white-space: nowrap; z-index: 0; pointer-events: none; transform: rotate(-25deg); }

    /* Header */
    .hdr { width: 100%; border-collapse: collapse; background: #0f1b4c; }
    .hdr td { padding: 8px 14px; color: #fff; vertical-align: middle; }
    .hdr h1 { font-size: 15px; font-weight: 800; letter-spacing: 3px; margin: 0; }
    .hdr .sub { font-size: 6px; opacity: 0.5; text-transform: uppercase; letter-spacing: 1.5px; }
    .folio-cell { text-align: right; }
    .folio-box { display: inline-block; border: 1px solid rgba(255,255,255,0.2); padding: 3px 10px; text-align: center; }
    .folio-lbl { font-size: 5.5px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.5; }
    .folio-num { font-size: 10px; font-weight: 800; }
    .accent { width: 100%; height: 3px; background: #f97316; }

    /* Badge */
    .badge { display: inline-block; padding: 2px 7px; font-size: 7px; font-weight: 700; text-transform: uppercase; }
    .badge-Pendiente  { background: #fef3c7; color: #92400e; }
    .badge-Aprobado   { background: #d1fae5; color: #065f46; }
    .badge-Rechazado  { background: #fee2e2; color: #991b1b; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #dbeafe; color: #1e40af; }

    /* Main 3-col layout */
    .main { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .main > tbody > tr > td { vertical-align: top; }
    .col-info { width: 28%; padding-right: 10px; }
    .col-left { width: 36%; padding: 0 8px; border-left: 1px solid #e2e8f0; }
    .col-right { width: 36%; padding-left: 8px; border-left: 1px solid #e2e8f0; }

    /* Section title */
    .st { font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #0f1b4c; border-bottom: 1.5px solid #0f1b4c; padding-bottom: 2px; margin-bottom: 5px; }

    /* Info rows */
    .irow { margin-bottom: 4px; }
    .irow .lbl { font-size: 6.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; }
    .irow .val { font-size: 8.5px; font-weight: 500; color: #111827; }

    /* Fields */
    .fb { margin-bottom: 4px; }
    .fl { font-size: 6px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; margin-bottom: 1px; }
    .fv { font-size: 8px; border: 1px solid #e5e7eb; padding: 3px 5px; background: #fafbfc; line-height: 1.3; min-height: 14px; }
    .sig-box { border: 1px solid #d1d5db; background: #fff; text-align: center; padding: 3px; }
    .sig-box img { max-height: 35px; }

    /* Approval */
    .ap { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
    .ap td { padding: 3px 5px; border: 1px solid #e5e7eb; font-size: 7.5px; }
    .ap-ok td { border-left: 3px solid #059669; background: #f0fdf4; }
    .ap-no td { border-left: 3px solid #dc2626; background: #fef2f2; }

    /* Footer */
    .ftr { width: 100%; border-collapse: collapse; background: #0f1b4c; }
    .ftr td { padding: 5px 14px; color: rgba(255,255,255,0.55); font-size: 6px; vertical-align: middle; }

    /* Verification */
    .vrf { background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 8px; margin-top: 6px; }
    .vrf-t { font-size: 6.5px; font-weight: 700; color: #0f1b4c; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
    .vrf-g { width: 100%; border-collapse: collapse; }
    .vrf-g td { padding: 1px 4px; font-size: 6.5px; }
    .vrf-l { color: #6b7280; }
    .vrf-v { font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; font-weight: 600; }
</style>
</head>
<body>

<div class="watermark">SAEP</div>

{{-- HEADER --}}
<table class="hdr" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <h1>SAEP</h1>
            <div class="sub">Sistema Automatizado de Ejecuci&oacute;n y Prevenci&oacute;n</div>
        </td>
        <td style="text-align:center;">
            <strong style="font-size:10px;">{{ $respuesta->formulario->nombre }}</strong><br>
            <span style="font-size:7px;opacity:0.6;">{{ $respuesta->created_at->format('d/m/Y') }}</span>
        </td>
        <td class="folio-cell">
            <div class="folio-box">
                <div class="folio-lbl">Folio</div>
                <div class="folio-num">{{ $folio }}</div>
            </div>
            <div style="margin-top:3px;">
                <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
            </div>
        </td>
    </tr>
</table>
<div class="accent"></div>

{{-- MAIN 3-COLUMN LAYOUT --}}
<table class="main" cellpadding="0" cellspacing="0">
    <tr>
        {{-- LEFT: Solicitante Info + Aprobaciones --}}
        <td class="col-info">
            <div class="st">Solicitante</div>
            <div class="irow"><div class="lbl">Nombre</div><div class="val">{{ $respuesta->usuario->name ?? '—' }}</div></div>
            <div class="irow"><div class="lbl">Email</div><div class="val" style="font-size:7.5px;">{{ $respuesta->usuario->email ?? '—' }}</div></div>
            <div class="irow"><div class="lbl">Departamento</div><div class="val">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</div></div>
            <div class="irow"><div class="lbl">Cargo</div><div class="val">{{ $respuesta->usuario->cargo->nombre ?? '—' }}</div></div>
            <div class="irow"><div class="lbl">Fecha env&iacute;o</div><div class="val">{{ $respuesta->created_at->format('d/m/Y H:i') }}</div></div>
            <div class="irow"><div class="lbl">Versi&oacute;n</div><div class="val">v{{ $respuesta->version_form }}</div></div>
            @if($respuesta->fecha_resolucion)
            <div class="irow"><div class="lbl">Resoluci&oacute;n</div><div class="val">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</div></div>
            @endif

            @if($respuesta->aprobaciones->count() > 0)
            <div class="st" style="margin-top:8px;">Aprobaciones</div>
            @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $ap)
            @php $rc = match($ap->accion) { 'Aprobado'=>'ap-ok','Rechazado'=>'ap-no', default=>'' }; @endphp
            <table class="ap {{ $rc }}" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-weight:600;">{{ $ap->aprobador->name ?? '—' }}</td>
                    <td style="text-align:right;">
                        <span class="badge badge-{{ $ap->accion === 'Aprobado' ? 'Aprobado' : ($ap->accion === 'Rechazado' ? 'Rechazado' : 'Pendiente') }}">{{ $ap->accion }}</span>
                    </td>
                </tr>
                @if($ap->comentario)
                <tr><td colspan="2" style="font-style:italic;color:#374151;font-size:7px;">"{{ $ap->comentario }}"</td></tr>
                @endif
            </table>
            @endforeach
            @endif
        </td>

        {{-- CENTER: Fields left half --}}
        <td class="col-left">
            <div class="st">Datos del Formulario</div>
            @foreach($leftFields as $field)
                <div class="fb">
                    <div class="fl">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</div>
                    @php $val = $datos[$field['id']] ?? null; @endphp
                    @if($field['type'] === 'signature' && $val && str_starts_with($val, 'data:image'))
                        <div class="sig-box"><img src="{{ $val }}" alt="Firma"></div>
                    @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                        <div class="fv">{{ $val['name'] }}</div>
                    @elseif($field['type'] === 'textarea')
                        <div class="fv" style="white-space:pre-line;">{{ $val ?: '—' }}</div>
                    @elseif(is_array($val))
                        <div class="fv">{{ implode(', ', $val) }}</div>
                    @else
                        <div class="fv">{{ $val ?: '—' }}</div>
                    @endif
                </div>
            @endforeach
        </td>

        {{-- RIGHT: Fields right half --}}
        <td class="col-right">
            <div class="st">&nbsp;</div>
            @foreach($rightFields as $field)
                <div class="fb">
                    <div class="fl">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</div>
                    @php $val = $datos[$field['id']] ?? null; @endphp
                    @if($field['type'] === 'signature' && $val && str_starts_with($val, 'data:image'))
                        <div class="sig-box"><img src="{{ $val }}" alt="Firma"></div>
                    @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                        <div class="fv">{{ $val['name'] }}</div>
                    @elseif($field['type'] === 'textarea')
                        <div class="fv" style="white-space:pre-line;">{{ $val ?: '—' }}</div>
                    @elseif(is_array($val))
                        <div class="fv">{{ implode(', ', $val) }}</div>
                    @else
                        <div class="fv">{{ $val ?: '—' }}</div>
                    @endif
                </div>
            @endforeach
        </td>
    </tr>
</table>

{{-- VERIFICATION --}}
<div class="vrf">
    <div class="vrf-t">Verificaci&oacute;n Digital</div>
    <table class="vrf-g" cellpadding="0" cellspacing="0">
        <tr>
            <td class="vrf-l">Folio</td><td class="vrf-v">{{ $folio }}</td>
            <td class="vrf-l">Hash</td><td class="vrf-v">{{ $hash }}</td>
            <td class="vrf-l">Generado</td><td class="vrf-v">{{ $fechaEmision }}</td>
            <td class="vrf-l">Por</td><td class="vrf-v">{{ auth()->user()->name ?? 'Sistema' }}</td>
        </tr>
    </table>
</div>

{{-- FOOTER --}}
<table class="ftr" cellpadding="0" cellspacing="0">
    <tr>
        <td><strong style="color:#fff;font-size:7px;">SAEP</strong> — Sistema Automatizado de Ejecuci&oacute;n y Prevenci&oacute;n &middot; {{ $fechaEmision }} &middot; {{ $folio }}</td>
        <td style="text-align:right;">{{ $hash }} &middot; saep.bmachero.com</td>
    </tr>
</table>

</body>
</html>