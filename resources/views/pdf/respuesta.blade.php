<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Solicitud #{{ str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) }}</title>
@php
    $folio = 'SAEP-' . str_pad($respuesta->id, 6, '0', STR_PAD_LEFT);
    $hash = strtoupper(substr(hash('sha256', $respuesta->id . $respuesta->created_at . ($respuesta->usuario->email ?? '')), 0, 16));
    $fechaEmision = now()->format('d/m/Y H:i:s');
@endphp
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; background: #fff; }

    .watermark {
        position: fixed; top: 38%; left: 15%;
        font-size: 80px; color: rgba(15, 27, 76, 0.035);
        font-weight: 900; letter-spacing: 14px;
        white-space: nowrap; z-index: 0;
        pointer-events: none; transform: rotate(-35deg);
    }

    .header-table { width: 100%; border-collapse: collapse; background: #0f1b4c; }
    .header-table td { padding: 18px 30px; color: #fff; vertical-align: middle; }
    .header-table h1 { font-size: 20px; font-weight: 800; letter-spacing: 3px; margin: 0; }
    .header-table .subtitle { font-size: 7.5px; opacity: 0.6; text-transform: uppercase; letter-spacing: 1.5px; }
    .folio-cell { text-align: right; }
    .folio-inner { display: inline-block; border: 1px solid rgba(255,255,255,0.25); padding: 6px 14px; text-align: center; }
    .folio-label { font-size: 7px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; }
    .folio-number { font-size: 13px; font-weight: 800; letter-spacing: 1px; }
    .accent-bar { width: 100%; height: 4px; background: #f97316; }

    .body { padding: 20px 30px; position: relative; z-index: 1; }

    .status-table { width: 100%; border-collapse: collapse; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 16px; }
    .status-table td { padding: 10px 14px; vertical-align: middle; }

    .badge { display: inline-block; padding: 3px 10px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-Pendiente  { background: #fef3c7; color: #92400e; }
    .badge-Aprobado   { background: #d1fae5; color: #065f46; }
    .badge-Rechazado  { background: #fee2e2; color: #991b1b; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #dbeafe; color: #1e40af; }

    .section { margin-bottom: 16px; }
    .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #0f1b4c; border-bottom: 2px solid #0f1b4c; padding-bottom: 4px; margin-bottom: 10px; }

    .info-grid { width: 100%; border-collapse: collapse; }
    .info-grid td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
    .info-label { color: #6b7280; font-size: 9px; width: 22%; vertical-align: top; }
    .info-value { color: #1f2937; font-weight: 500; width: 28%; }

    .field-block { margin-bottom: 10px; }
    .field-label { font-size: 8px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px; font-weight: 600; }
    .field-value { font-size: 10px; border: 1px solid #e5e7eb; padding: 6px 9px; min-height: 24px; background: #fafbfc; line-height: 1.4; }
    .field-divider { text-align: center; color: #94a3b8; font-size: 8px; margin: 10px 0; border-top: 1px dashed #cbd5e1; padding-top: 5px; text-transform: uppercase; letter-spacing: 0.05em; }

    .signature-box { border: 1px solid #d1d5db; background: #fff; text-align: center; padding: 6px; }
    .signature-box img { max-height: 55px; }

    .approval-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .approval-table td { padding: 8px 10px; border: 1px solid #e5e7eb; }
    .approval-approved td { border-left: 4px solid #059669; background: #f0fdf4; }
    .approval-rejected td { border-left: 4px solid #dc2626; background: #fef2f2; }

    .footer-table { width: 100%; border-collapse: collapse; background: #0f1b4c; }
    .footer-table td { padding: 10px 30px; color: rgba(255,255,255,0.65); font-size: 7.5px; vertical-align: middle; }

    .verification-wrap { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; margin-top: 16px; }
    .verification-title { font-size: 8px; font-weight: 700; color: #0f1b4c; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
    .verification-grid { width: 100%; border-collapse: collapse; }
    .verification-grid td { padding: 3px 6px; font-size: 8px; }
    .verification-label { color: #6b7280; width: 22%; }
    .verification-value { font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; font-weight: 600; width: 28%; }
</style>
</head>
<body>

<div class="watermark">SAEP</div>

<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <h1>SAEP</h1>
            <div class="subtitle">Sistema Automatizado de Ejecuci&oacute;n y Prevenci&oacute;n</div>
        </td>
        <td class="folio-cell">
            <div class="folio-inner">
                <div class="folio-label">Folio</div>
                <div class="folio-number">{{ $folio }}</div>
            </div>
        </td>
    </tr>
</table>
<div class="accent-bar"></div>

<div class="body">

    <table class="status-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:50%;">
                <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Formulario:</span><br>
                <strong style="font-size:11px;">{{ $respuesta->formulario->nombre }}</strong>
            </td>
            <td style="text-align:center;width:25%;">
                <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Fecha Emisi&oacute;n:</span><br>
                <strong style="font-size:10px;">{{ $respuesta->created_at->format('d/m/Y') }}</strong>
            </td>
            <td style="text-align:right;width:25%;">
                <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Estado:</span><br>
                <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Informaci&oacute;n del Solicitante</div>
        <table class="info-grid" cellpadding="0" cellspacing="0">
            <tr>
                <td class="info-label">Nombre completo</td>
                <td class="info-value">{{ $respuesta->usuario->name ?? '—' }}</td>
                <td class="info-label">Correo electr&oacute;nico</td>
                <td class="info-value">{{ $respuesta->usuario->email ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Departamento</td>
                <td class="info-value">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</td>
                <td class="info-label">Cargo</td>
                <td class="info-value">{{ $respuesta->usuario->cargo->nombre ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Fecha de env&iacute;o</td>
                <td class="info-value">{{ $respuesta->created_at->format('d/m/Y H:i:s') }}</td>
                <td class="info-label">Versi&oacute;n formulario</td>
                <td class="info-value">v{{ $respuesta->version_form }}</td>
            </tr>
            @if($respuesta->fecha_resolucion)
            <tr>
                <td class="info-label">Fecha resoluci&oacute;n</td>
                <td class="info-value">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</td>
                <td class="info-label">&Uacute;ltima actualizaci&oacute;n</td>
                <td class="info-value">{{ $respuesta->updated_at->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Datos del Formulario</div>
        @foreach($schema as $field)
            @if($field['type'] === 'divider')
                <div class="field-divider">{{ $field['label'] }}</div>
            @else
                <div class="field-block">
                    <div class="field-label">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</div>
                    @php $val = $datos[$field['id']] ?? null; @endphp

                    @if($field['type'] === 'signature' && $val && str_starts_with($val, 'data:image'))
                        <div class="signature-box">
                            <img src="{{ $val }}" alt="Firma digital">
                            <div style="font-size:7px;color:#6b7280;margin-top:2px;">Firma electr&oacute;nica del solicitante</div>
                        </div>
                    @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                        <div class="field-value">
                            {{ $val['name'] }}
                            @if(isset($val['size']))
                                <span style="color:#6b7280;font-size:9px;">({{ number_format($val['size']/1024, 0) }} KB)</span>
                            @endif
                        </div>
                    @elseif($field['type'] === 'textarea')
                        <div class="field-value" style="white-space:pre-line;">{{ $val ?: '—' }}</div>
                    @elseif(is_array($val))
                        <div class="field-value">{{ implode(', ', $val) }}</div>
                    @else
                        <div class="field-value">{{ $val ?: '—' }}</div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    @if($respuesta->aprobaciones->count() > 0)
    <div class="section">
        <div class="section-title">Historial de Aprobaci&oacute;n</div>
        @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $ap)
        @php
            $rowClass = match($ap->accion) {
                'Aprobado' => 'approval-approved',
                'Rechazado' => 'approval-rejected',
                default => '',
            };
        @endphp
        <table class="approval-table {{ $rowClass }}" cellpadding="0" cellspacing="0">
            <tr>
                <td style="font-size:10px;font-weight:700;width:40%;">{{ $ap->aprobador->name ?? '—' }}</td>
                <td style="text-align:center;width:20%;">
                    <span class="badge badge-{{ $ap->accion === 'Aprobado' ? 'Aprobado' : ($ap->accion === 'Rechazado' ? 'Rechazado' : 'Pendiente') }}">
                        {{ $ap->accion }}
                    </span>
                </td>
                <td style="text-align:right;font-size:9px;color:#6b7280;width:40%;">
                    {{ $ap->fecha ? \Carbon\Carbon::parse($ap->fecha)->format('d/m/Y H:i') : '' }}
                </td>
            </tr>
            @if($ap->comentario)
            <tr>
                <td colspan="3" style="font-size:9px;color:#374151;font-style:italic;padding-top:0;">"{{ $ap->comentario }}"</td>
            </tr>
            @endif
        </table>
        @endforeach
    </div>
    @endif

    <div class="verification-wrap">
        <div class="verification-title">Registro de Verificaci&oacute;n Digital</div>
        <table class="verification-grid" cellpadding="0" cellspacing="0">
            <tr>
                <td class="verification-label">Folio documento</td>
                <td class="verification-value">{{ $folio }}</td>
                <td class="verification-label">Hash de integridad</td>
                <td class="verification-value">{{ $hash }}</td>
            </tr>
            <tr>
                <td class="verification-label">Fecha generaci&oacute;n</td>
                <td class="verification-value">{{ $fechaEmision }}</td>
                <td class="verification-label">Generado por</td>
                <td class="verification-value">{{ auth()->user()->name ?? 'Sistema' }}</td>
            </tr>
            <tr>
                <td class="verification-label">Plataforma</td>
                <td class="verification-value">SAEP v1.0</td>
                <td class="verification-label">IP solicitante</td>
                <td class="verification-value">{{ request()->ip() ?? '—' }}</td>
            </tr>
        </table>
        <div style="margin-top:6px;font-size:7px;color:#94a3b8;line-height:1.4;">
            Este documento ha sido generado electr&oacute;nicamente por la plataforma SAEP y constituye un registro digital
            de la solicitud identificada. El hash de integridad permite verificar la autenticidad del documento.
            Cualquier modificaci&oacute;n posterior invalida este registro.
        </div>
    </div>
</div>

<table class="footer-table" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:60%;">
            <strong style="color:#fff;font-size:9px;">SAEP Platform</strong> — Sistema Automatizado de Ejecuci&oacute;n y Prevenci&oacute;n<br>
            Documento generado el {{ $fechaEmision }} · {{ $folio }}
        </td>
        <td style="text-align:right;width:40%;">
            <span style="font-size:7px;">Verificaci&oacute;n: {{ $hash }}</span><br>
            <span style="font-size:7px;">saep.bmachero.com</span>
        </td>
    </tr>
</table>

</body>
</html>