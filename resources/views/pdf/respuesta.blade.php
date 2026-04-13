<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Formulario #{{ str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) }}</title>
@php
    $folio = 'SAEP-' . str_pad($respuesta->id, 6, '0', STR_PAD_LEFT);
    $hash = strtoupper(substr(hash('sha256', $respuesta->id . $respuesta->created_at . ($respuesta->usuario->email ?? '')), 0, 16));
    $fechaEmision = now()->format('d/m/Y H:i:s');
    $fields = collect($schema)->filter(fn($f) => $f['type'] !== 'divider')->values();
    $half = (int) ceil($fields->count() / 2);
    $leftFields = $fields->slice(0, $half);
    $rightFields = $fields->slice($half);
@endphp
<style>
    @page { margin: 12mm 14mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1f2937; background: #fff; }

    .watermark { position: fixed; top: 35%; left: 22%; font-size: 72px; color: rgba(15,27,76,0.03); font-weight: 900; letter-spacing: 14px; white-space: nowrap; z-index: 0; pointer-events: none; transform: rotate(-35deg); }

    /* Header */
    .header { width: 100%; border-collapse: collapse; background: #0f1b4c; }
    .header td { padding: 10px 16px; color: #fff; vertical-align: middle; }
    .header h1 { font-size: 16px; font-weight: 800; letter-spacing: 3px; margin: 0; }
    .header .sub { font-size: 6px; opacity: 0.55; text-transform: uppercase; letter-spacing: 1.5px; }
    .folio-cell { text-align: right; }
    .folio-box { display: inline-block; border: 1px solid rgba(255,255,255,0.2); padding: 4px 10px; text-align: center; }
    .folio-lbl { font-size: 6px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.55; }
    .folio-num { font-size: 11px; font-weight: 800; letter-spacing: 1px; }
    .accent { width: 100%; height: 3px; background: #f97316; }

    /* Status bar */
    .status { width: 100%; border-collapse: collapse; background: #f8fafc; border: 1px solid #e2e8f0; margin: 8px 0; }
    .status td { padding: 6px 10px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; font-size: 7px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-Pendiente  { background: #fef3c7; color: #92400e; }
    .badge-Aprobado   { background: #d1fae5; color: #065f46; }
    .badge-Rechazado  { background: #fee2e2; color: #991b1b; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #dbeafe; color: #1e40af; }

    /* Section title */
    .stitle { font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #0f1b4c; border-bottom: 1.5px solid #0f1b4c; padding-bottom: 3px; margin-bottom: 6px; }

    /* Info grid */
    .info { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .info td { padding: 3px 6px; font-size: 7.5px; border-bottom: 1px solid #f1f5f9; }
    .info .lbl { color: #6b7280; font-size: 7px; width: 18%; }
    .info .val { color: #1f2937; font-weight: 500; width: 32%; }

    /* Two-column layout */
    .cols { width: 100%; border-collapse: collapse; }
    .cols > tbody > tr > td { width: 50%; vertical-align: top; }
    .cols .col-left { padding-right: 8px; }
    .cols .col-right { padding-left: 8px; border-left: 1px solid #e5e7eb; }

    /* Fields */
    .fb { margin-bottom: 5px; }
    .fl { font-size: 6.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1px; font-weight: 600; }
    .fv { font-size: 8px; border: 1px solid #e5e7eb; padding: 4px 6px; background: #fafbfc; line-height: 1.3; min-height: 16px; }
    .sig-box { border: 1px solid #d1d5db; background: #fff; text-align: center; padding: 4px; }
    .sig-box img { max-height: 40px; }

    /* Approval */
    .ap-tbl { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .ap-tbl td { padding: 4px 6px; border: 1px solid #e5e7eb; font-size: 7.5px; }
    .ap-ok td { border-left: 3px solid #059669; background: #f0fdf4; }
    .ap-no td { border-left: 3px solid #dc2626; background: #fef2f2; }

    /* Verification */
    .verif { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; margin-top: 8px; }
    .verif-title { font-size: 7px; font-weight: 700; color: #0f1b4c; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
    .verif-grid { width: 100%; border-collapse: collapse; }
    .verif-grid td { padding: 2px 4px; font-size: 7px; }
    .verif-lbl { color: #6b7280; width: 18%; }
    .verif-val { font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; font-weight: 600; width: 32%; }

    /* Footer */
    .footer { width: 100%; border-collapse: collapse; background: #0f1b4c; margin-top: 8px; }
    .footer td { padding: 6px 16px; color: rgba(255,255,255,0.6); font-size: 6.5px; vertical-align: middle; }
</style>
</head>
<body>

<div class="watermark">SAEP</div>

{{-- HEADER --}}
<table class="header" cellpadding="0" cellspacing="0">
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

{{-- STATUS BAR --}}
<table class="status" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%;">
            <span style="font-size:6.5px;color:#6b7280;text-transform:uppercase;">Formulario:</span><br>
            <strong style="font-size:9px;">{{ $respuesta->formulario->nombre }}</strong>
        </td>
        <td style="text-align:center;width:25%;">
            <span style="font-size:6.5px;color:#6b7280;text-transform:uppercase;">Fecha:</span><br>
            <strong style="font-size:8px;">{{ $respuesta->created_at->format('d/m/Y') }}</strong>
        </td>
        <td style="text-align:right;width:25%;">
            <span style="font-size:6.5px;color:#6b7280;text-transform:uppercase;">Estado:</span><br>
            <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
        </td>
    </tr>
</table>

{{-- SOLICITANTE --}}
<div class="stitle">Informaci&oacute;n del Solicitante</div>
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
        <td class="val">{{ $respuesta->created_at->format('d/m/Y H:i') }}</td>
        <td class="lbl">Versi&oacute;n</td>
        <td class="val">v{{ $respuesta->version_form }}</td>
    </tr>
</table>

{{-- DATOS - SIDE BY SIDE --}}
<div class="stitle">Datos del Formulario</div>
<table class="cols" cellpadding="0" cellspacing="0">
    <tr>
        <td class="col-left">
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
        <td class="col-right">
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

{{-- APROBACIONES --}}
@if($respuesta->aprobaciones->count() > 0)
<div style="margin-top:6px;">
    <div class="stitle">Historial de Aprobaci&oacute;n</div>
    @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $ap)
    @php $rc = match($ap->accion) { 'Aprobado'=>'ap-ok','Rechazado'=>'ap-no', default=>'' }; @endphp
    <table class="ap-tbl {{ $rc }}" cellpadding="0" cellspacing="0">
        <tr>
            <td style="font-weight:700;width:35%;">{{ $ap->aprobador->name ?? '—' }}</td>
            <td style="text-align:center;width:20%;"><span class="badge badge-{{ $ap->accion === 'Aprobado' ? 'Aprobado' : ($ap->accion === 'Rechazado' ? 'Rechazado' : 'Pendiente') }}">{{ $ap->accion }}</span></td>
            <td style="text-align:right;color:#6b7280;width:45%;">{{ $ap->fecha ? \Carbon\Carbon::parse($ap->fecha)->format('d/m/Y H:i') : '' }}</td>
        </tr>
        @if($ap->comentario)
        <tr><td colspan="3" style="font-style:italic;color:#374151;">"{{ $ap->comentario }}"</td></tr>
        @endif
    </table>
    @endforeach
</div>
@endif

{{-- VERIFICACION --}}
<div class="verif">
    <div class="verif-title">Registro de Verificaci&oacute;n Digital</div>
    <table class="verif-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td class="verif-lbl">Folio</td>
            <td class="verif-val">{{ $folio }}</td>
            <td class="verif-lbl">Hash</td>
            <td class="verif-val">{{ $hash }}</td>
        </tr>
        <tr>
            <td class="verif-lbl">Generado</td>
            <td class="verif-val">{{ $fechaEmision }}</td>
            <td class="verif-lbl">Por</td>
            <td class="verif-val">{{ auth()->user()->name ?? 'Sistema' }}</td>
        </tr>
    </table>
    <div style="margin-top:4px;font-size:6px;color:#94a3b8;line-height:1.3;">
        Documento generado electr&oacute;nicamente por SAEP. El hash de integridad permite verificar autenticidad. Modificaci&oacute;n posterior invalida este registro.
    </div>
</div>

{{-- FOOTER --}}
<table class="footer" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:60%;">
            <strong style="color:#fff;font-size:7.5px;">SAEP Platform</strong> — Sistema Automatizado de Ejecuci&oacute;n y Prevenci&oacute;n<br>
            {{ $fechaEmision }} &middot; {{ $folio }}
        </td>
        <td style="text-align:right;width:40%;">
            {{ $hash }} &middot; saep.bmachero.com
        </td>
    </tr>
</table>

</body>
</html>