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
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; background: #fff; }

    .watermark { position: fixed; top: 35%; left: 12%; font-size: 88px; color: rgba(15,27,76,0.025); font-weight: 900; letter-spacing: 16px; white-space: nowrap; z-index: 0; pointer-events: none; transform: rotate(-35deg); }

    /* Header */
    .hdr { width: 100%; border-collapse: collapse; background: #0f1b4c; }
    .hdr td { padding: 16px 24px; color: #fff; vertical-align: middle; }
    .hdr h1 { font-size: 22px; font-weight: 800; letter-spacing: 4px; margin: 0; }
    .hdr .sub { font-size: 7px; opacity: 0.5; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
    .folio-cell { text-align: right; }
    .folio-box { display: inline-block; border: 1px solid rgba(255,255,255,0.2); padding: 5px 12px; text-align: center; }
    .folio-lbl { font-size: 6px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.5; }
    .folio-num { font-size: 12px; font-weight: 800; }
    .accent { width: 100%; height: 4px; background: #f97316; }

    /* Form title bar */
    .titlebar { width: 100%; border-collapse: collapse; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .titlebar td { padding: 10px 24px; vertical-align: middle; }

    /* Badge */
    .badge { display: inline-block; padding: 3px 10px; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-Pendiente  { background: #fef3c7; color: #92400e; }
    .badge-Aprobado   { background: #d1fae5; color: #065f46; }
    .badge-Rechazado  { background: #fee2e2; color: #991b1b; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #dbeafe; color: #1e40af; }

    /* Body */
    .body { padding: 16px 24px 40px 24px; }

    /* Section title */
    .st { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #0f1b4c; border-bottom: 2px solid #0f1b4c; padding-bottom: 4px; margin-bottom: 10px; }

    /* Info grid */
    .info { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .info td { padding: 6px 10px; font-size: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .info .lbl { color: #6b7280; font-size: 8px; width: 18%; text-transform: uppercase; letter-spacing: 0.03em; }
    .info .val { color: #111827; font-weight: 600; width: 32%; }

    /* Two-column fields */
    .cols { width: 100%; border-collapse: collapse; }
    .cols > tbody > tr > td { width: 50%; vertical-align: top; }
    .col-l { padding-right: 10px; }
    .col-r { padding-left: 10px; border-left: 1.5px solid #e2e8f0; }

    /* Fields */
    .fb { margin-bottom: 8px; }
    .fl { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px; }
    .fv { font-size: 10px; border: 1px solid #e5e7eb; padding: 7px 10px; background: #fafbfc; line-height: 1.4; min-height: 22px; }
    .sig-box { border: 1px solid #d1d5db; background: #fff; text-align: center; padding: 6px; }
    .sig-box img { max-height: 50px; }

    /* Approval */
    .ap { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    .ap td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 9px; }
    .ap-ok td { border-left: 4px solid #059669; background: #f0fdf4; }
    .ap-no td { border-left: 4px solid #dc2626; background: #fef2f2; }

    /* Verification */
    .vrf { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; margin-top: 14px; }
    .vrf-t { font-size: 8px; font-weight: 700; color: #0f1b4c; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
    .vrf-g { width: 100%; border-collapse: collapse; }
    .vrf-g td { padding: 3px 6px; font-size: 8px; }
    .vrf-l { color: #6b7280; width: 16%; }
    .vrf-v { font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; font-weight: 600; width: 34%; }

    /* Footer - fixed bottom */
    .ftr { width: 100%; border-collapse: collapse; background: #0f1b4c; position: fixed; bottom: 0; left: 0; }
    .ftr td { padding: 10px 24px; color: rgba(255,255,255,0.55); font-size: 7px; vertical-align: middle; }

    /* Legal text */
    .legal { font-size: 7px; color: #94a3b8; line-height: 1.3; margin-top: 4px; }
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
        <td class="folio-cell">
            <div class="folio-box">
                <div class="folio-lbl">Folio</div>
                <div class="folio-num">{{ $folio }}</div>
            </div>
        </td>
    </tr>
</table>
<div class="accent"></div>

{{-- TITLE BAR --}}
<table class="titlebar" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;">
            <span style="font-size:8px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">Formulario:</span><br>
            <strong style="font-size:13px;color:#0f1b4c;">{{ $respuesta->formulario->nombre }}</strong>
        </td>
        <td style="text-align:center;width:25%;">
            <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Fecha:</span><br>
            <strong style="font-size:11px;">{{ $respuesta->created_at->format('d/m/Y') }}</strong>
        </td>
        <td style="text-align:right;width:25%;">
            <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Estado:</span><br>
            <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
        </td>
    </tr>
</table>

{{-- BODY --}}
<div class="body">

    {{-- SOLICITANTE --}}
    <div class="st">Informaci&oacute;n del Solicitante</div>
    <table class="info" cellpadding="0" cellspacing="0">
        <tr>
            <td class="lbl">Nombre</td>
            <td class="val">{{ $respuesta->usuario->name ?? '—' }}</td>
            <td class="lbl">Email</td>
            <td class="val">{{ $respuesta->usuario->email ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Departamento</td>
            <td class="val">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</td>
            <td class="lbl">Cargo</td>
            <td class="val">{{ $respuesta->usuario->cargo->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Fecha env&iacute;o</td>
            <td class="val">{{ $respuesta->created_at->format('d/m/Y H:i:s') }}</td>
            <td class="lbl">Versi&oacute;n</td>
            <td class="val">v{{ $respuesta->version_form }}</td>
        </tr>
        @if($respuesta->fecha_resolucion)
        <tr>
            <td class="lbl">Resoluci&oacute;n</td>
            <td class="val">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</td>
            <td class="lbl">Actualizado</td>
            <td class="val">{{ $respuesta->updated_at->format('d/m/Y H:i') }}</td>
        </tr>
        @endif
    </table>

    {{-- DATOS SIDE BY SIDE --}}
    <div class="st">Datos del Formulario</div>
    <table class="cols" cellpadding="0" cellspacing="0">
        <tr>
            <td class="col-l">
                @foreach($leftFields as $field)
                    <div class="fb">
                        <div class="fl">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</div>
                        @php $val = $datos[$field['id']] ?? null; @endphp
                        @if($field['type'] === 'signature' && $val && str_starts_with($val, 'data:image'))
                            <div class="sig-box"><img src="{{ $val }}" alt="Firma"></div>
                        @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                            <div class="fv">{{ $val['name'] }}@if(isset($val['size'])) <span style="color:#6b7280;font-size:8px;">({{ number_format($val['size']/1024, 0) }} KB)</span>@endif</div>
                        @elseif($field['type'] === 'textarea')
                            <div class="fv" style="white-space:pre-line;min-height:40px;">{{ $val ?: '—' }}</div>
                        @elseif(is_array($val))
                            <div class="fv">{{ implode(', ', $val) }}</div>
                        @else
                            <div class="fv">{{ $val ?: '—' }}</div>
                        @endif
                    </div>
                @endforeach
            </td>
            <td class="col-r">
                @foreach($rightFields as $field)
                    <div class="fb">
                        <div class="fl">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</div>
                        @php $val = $datos[$field['id']] ?? null; @endphp
                        @if($field['type'] === 'signature' && $val && str_starts_with($val, 'data:image'))
                            <div class="sig-box"><img src="{{ $val }}" alt="Firma"></div>
                        @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                            <div class="fv">{{ $val['name'] }}@if(isset($val['size'])) <span style="color:#6b7280;font-size:8px;">({{ number_format($val['size']/1024, 0) }} KB)</span>@endif</div>
                        @elseif($field['type'] === 'textarea')
                            <div class="fv" style="white-space:pre-line;min-height:40px;">{{ $val ?: '—' }}</div>
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

    {{-- APROBACIONES --}}
    @if($respuesta->aprobaciones->count() > 0)
    <div style="margin-top:14px;">
        <div class="st">Historial de Aprobaci&oacute;n</div>
        @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $ap)
        @php $rc = match($ap->accion) { 'Aprobado'=>'ap-ok','Rechazado'=>'ap-no', default=>'' }; @endphp
        <table class="ap {{ $rc }}" cellpadding="0" cellspacing="0">
            <tr>
                <td style="font-weight:700;width:40%;">{{ $ap->aprobador->name ?? '—' }}</td>
                <td style="text-align:center;width:20%;">
                    <span class="badge badge-{{ $ap->accion === 'Aprobado' ? 'Aprobado' : ($ap->accion === 'Rechazado' ? 'Rechazado' : 'Pendiente') }}">{{ $ap->accion }}</span>
                </td>
                <td style="text-align:right;color:#6b7280;width:40%;">{{ $ap->fecha ? \Carbon\Carbon::parse($ap->fecha)->format('d/m/Y H:i') : '' }}</td>
            </tr>
            @if($ap->comentario)
            <tr><td colspan="3" style="font-style:italic;color:#374151;">"{{ $ap->comentario }}"</td></tr>
            @endif
        </table>
        @endforeach
    </div>
    @endif

    {{-- VERIFICACION --}}
    <div class="vrf">
        <div class="vrf-t">Verificaci&oacute;n Digital</div>
        <table class="vrf-g" cellpadding="0" cellspacing="0">
            <tr>
                <td class="vrf-l">Folio</td><td class="vrf-v">{{ $folio }}</td>
                <td class="vrf-l">Hash</td><td class="vrf-v">{{ $hash }}</td>
            </tr>
            <tr>
                <td class="vrf-l">Generado</td><td class="vrf-v">{{ $fechaEmision }}</td>
                <td class="vrf-l">Por</td><td class="vrf-v">{{ auth()->user()->name ?? 'Sistema' }}</td>
            </tr>
            <tr>
                <td class="vrf-l">Plataforma</td><td class="vrf-v">SAEP v1.0</td>
                <td class="vrf-l">IP</td><td class="vrf-v">{{ request()->ip() ?? '—' }}</td>
            </tr>
        </table>
        <div class="legal">
            Este documento ha sido generado electr&oacute;nicamente por la plataforma SAEP y constituye un registro oficial
            de la informaci&oacute;n ingresada. El hash de integridad permite verificar la autenticidad del documento.
            Cualquier modificaci&oacute;n posterior invalida este registro.
        </div>
    </div>

</div>

{{-- FOOTER --}}
<table class="ftr" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:60%;">
            <strong style="color:#fff;font-size:8px;">SAEP Platform</strong> — Sistema Automatizado de Ejecuci&oacute;n y Prevenci&oacute;n<br>
            Documento generado el {{ $fechaEmision }} &middot; {{ $folio }}
        </td>
        <td style="text-align:right;width:40%;">
            Verificaci&oacute;n: {{ $hash }}<br>
            saep.bmachero.com
        </td>
    </tr>
</table>

</body>
</html>