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
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #1f2937;
        background: #fff;
        position: relative;
    }

    /* Watermark */
    .watermark {
        position: fixed;
        top: 38%;
        left: 15%;
        font-size: 80px;
        color: rgba(15, 27, 76, 0.035);
        font-weight: 900;
        letter-spacing: 14px;
        white-space: nowrap;
        z-index: 0;
        pointer-events: none;
        transform: rotate(-35deg);
    }

    /* Header */
    .header {
        background: #0f1b4c;
        color: #fff;
        padding: 18px 30px;
        position: relative;
        overflow: hidden;
    }
    .header-stripe {
        position: absolute;
        right: 0;
        top: 0;
        width: 100px;
        height: 100%;
        background: #f97316;
    }
    .header-content {
        width: 100%;
        position: relative;
        z-index: 1;
    }
    .header h1 {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 3px;
        margin-bottom: 2px;
        display: inline;
    }
    .header .subtitle {
        font-size: 7.5px;
        opacity: 0.6;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        display: block;
        margin-top: 2px;
    }
    .folio-box {
        float: right;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 6px;
        padding: 6px 14px;
        text-align: center;
    }
    .folio-box .folio-label {
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.6;
    }
    .folio-box .folio-number {
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 1px;
    }

    /* Body */
    .body { padding: 20px 30px; position: relative; z-index: 1; }

    /* Status bar */
    .status-bar {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 16px;
    }

    /* Badge */
    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-Pendiente  { background: #fef3c7; color: #92400e; }
    .badge-Aprobado   { background: #d1fae5; color: #065f46; }
    .badge-Rechazado  { background: #fee2e2; color: #991b1b; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #dbeafe; color: #1e40af; }

    /* Sections */
    .section { margin-bottom: 16px; }
    .section-title {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #0f1b4c;
        border-bottom: 2px solid #0f1b4c;
        padding-bottom: 4px;
        margin-bottom: 10px;
    }

    /* Info table */
    .info-grid { width: 100%; border-collapse: collapse; }
    .info-grid td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
    .info-label { color: #6b7280; font-size: 9px; width: 22%; vertical-align: top; }
    .info-value { color: #1f2937; font-weight: 500; width: 28%; }

    /* Form fields */
    .field-block { margin-bottom: 10px; }
    .field-label {
        font-size: 8px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 3px;
        font-weight: 600;
    }
    .field-value {
        font-size: 10px;
        border: 1px solid #e5e7eb;
        border-radius: 5px;
        padding: 6px 9px;
        min-height: 24px;
        background: #fafbfc;
        line-height: 1.4;
    }
    .field-divider {
        text-align: center;
        color: #94a3b8;
        font-size: 8px;
        margin: 10px 0;
        border-top: 1px dashed #cbd5e1;
        padding-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Signature */
    .signature-box {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
        text-align: center;
        padding: 6px;
    }
    .signature-box img { max-height: 55px; }

    /* Approval history */
    .approval-row {
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 6px;
    }
    .approval-Aprobado  { border-left: 4px solid #059669; background: #f0fdf4; }
    .approval-Rechazado { border-left: 4px solid #dc2626; background: #fef2f2; }

    /* Footer */
    .footer {
        background: #0f1b4c;
        color: rgba(255,255,255,0.65);
        padding: 10px 30px;
        font-size: 7.5px;
    }

    /* Verification block */
    .verification {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 16px;
    }
    .verification-title {
        font-size: 8px;
        font-weight: 700;
        color: #0f1b4c;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 6px;
    }
    .verification-grid { width: 100%; border-collapse: collapse; }
    .verification-grid td { padding: 3px 6px; font-size: 8px; }
    .verification-label { color: #6b7280; width: 22%; }
    .verification-value { font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; font-weight: 600; width: 28%; }
</style>
</head>
<body>

<div class="watermark">SAEP</div>

{{-- HEADER --}}
<div class="header">
    <div class="header-stripe"></div>
    <div class="header-content">
        <div class="folio-box">
            <div class="folio-label">Folio</div>
            <div class="folio-number">{{ $folio }}</div>
        </div>
        <h1>SAEP</h1>
        <span class="subtitle">Sistema Automatizado de Ejecución y Prevención</span>
    </div>
</div>

{{-- BODY --}}
<div class="body">
    {{-- Status Bar --}}
    <table class="status-bar" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:50%;">
                <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Formulario:</span><br>
                <strong style="font-size:11px;">{{ $respuesta->formulario->nombre }}</strong>
            </td>
            <td style="text-align:center;width:25%;">
                <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Fecha Emisión:</span><br>
                <strong style="font-size:10px;">{{ $respuesta->created_at->format('d/m/Y') }}</strong>
            </td>
            <td style="text-align:right;width:25%;">
                <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Estado:</span><br>
                <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
            </td>
        </tr>
    </table>

    {{-- Información del Solicitante --}}
    <div class="section">
        <div class="section-title">Información del Solicitante</div>
        <table class="info-grid">
            <tr>
                <td class="info-label">Nombre completo</td>
                <td class="info-value">{{ $respuesta->usuario->name ?? '—' }}</td>
                <td class="info-label">Correo electrónico</td>
                <td class="info-value">{{ $respuesta->usuario->email ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Departamento</td>
                <td class="info-value">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</td>
                <td class="info-label">Cargo</td>
                <td class="info-value">{{ $respuesta->usuario->cargo->nombre ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Fecha de envío</td>
                <td class="info-value">{{ $respuesta->created_at->format('d/m/Y H:i:s') }}</td>
                <td class="info-label">Versión formulario</td>
                <td class="info-value">v{{ $respuesta->version_form }}</td>
            </tr>
            @if($respuesta->fecha_resolucion)
            <tr>
                <td class="info-label">Fecha resolución</td>
                <td class="info-value">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</td>
                <td class="info-label">Última actualización</td>
                <td class="info-value">{{ $respuesta->updated_at->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Datos del Formulario --}}
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
                            <div style="font-size:7px;color:#6b7280;margin-top:2px;">Firma electrónica del solicitante</div>
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

    {{-- Historial de Aprobación --}}
    @if($respuesta->aprobaciones->count() > 0)
    <div class="section">
        <div class="section-title">Historial de Aprobación</div>
        @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $ap)
        @php
            $apClass = match($ap->accion) {
                'Aprobado' => 'approval-Aprobado',
                'Rechazado' => 'approval-Rechazado',
                default => '',
            };
        @endphp
        <div class="approval-row {{ $apClass }}">
            <table style="width:100%;">
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
            </table>
            @if($ap->comentario)
                <div style="margin-top:4px;font-size:9px;color:#374151;font-style:italic;padding-left:4px;">"{{ $ap->comentario }}"</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- Verificación Digital --}}
    <div class="verification">
        <div class="verification-title">Registro de Verificación Digital</div>
        <table class="verification-grid">
            <tr>
                <td class="verification-label">Folio documento</td>
                <td class="verification-value">{{ $folio }}</td>
                <td class="verification-label">Hash de integridad</td>
                <td class="verification-value">{{ $hash }}</td>
            </tr>
            <tr>
                <td class="verification-label">Fecha generación</td>
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
            Este documento ha sido generado electrónicamente por la plataforma SAEP y constituye un registro digital
            de la solicitud identificada. El hash de integridad permite verificar la autenticidad del documento.
            Cualquier modificación posterior invalida este registro.
        </div>
    </div>
</div>

{{-- FOOTER --}}
<div class="footer">
    <table style="width:100%;">
        <tr>
            <td style="width:60%;">
                <strong style="color:#fff;font-size:9px;">SAEP Platform</strong> — Sistema Automatizado de Ejecución y Prevención<br>
                Documento generado el {{ $fechaEmision }} · {{ $folio }}
            </td>
            <td style="text-align:right;width:40%;">
                <span style="font-size:7px;">Verificación: {{ $hash }}</span><br>
                <span style="font-size:7px;">saep.bmachero.com</span>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
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
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #1f2937;
        background: #fff;
        position: relative;
    }

    /* Watermark */
    .watermark {
        position: fixed;
        top: 35%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-35deg);
        font-size: 72px;
        color: rgba(15, 27, 76, 0.04);
        font-weight: 900;
        letter-spacing: 12px;
        white-space: nowrap;
        z-index: 0;
        pointer-events: none;
    }

    /* Header */
    .header {
        background: #0f1b4c;
        color: #fff;
        padding: 18px 30px;
        position: relative;
        overflow: hidden;
    }
    .header::after {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        width: 120px;
        height: 100%;
        background: #f97316;
        clip-path: polygon(30% 0, 100% 0, 100% 100%, 0% 100%);
    }
    .header-content {
        display: table;
        width: 100%;
    }
    .header-left {
        display: table-cell;
        vertical-align: middle;
        width: 60%;
    }
    .header-right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        width: 40%;
        position: relative;
        z-index: 1;
    }
    .header h1 {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 2px;
        margin-bottom: 2px;
    }
    .header .subtitle {
        font-size: 8px;
        opacity: 0.7;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }
    .folio-box {
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 6px;
        padding: 6px 12px;
        display: inline-block;
        text-align: center;
    }
    .folio-box .folio-label {
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.7;
    }
    .folio-box .folio-number {
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 1px;
    }

    /* Body */
    .body { padding: 20px 30px; position: relative; z-index: 1; }

    /* Status bar */
    .status-bar {
        display: table;
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 16px;
    }
    .status-bar-cell {
        display: table-cell;
        vertical-align: middle;
    }

    /* Badge */
    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-Pendiente  { background: #fef3c7; color: #92400e; }
    .badge-Aprobado   { background: #d1fae5; color: #065f46; }
    .badge-Rechazado  { background: #fee2e2; color: #991b1b; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #dbeafe; color: #1e40af; }
    .badge-Revisión   { background: #fef3c7; color: #92400e; }

    /* Sections */
    .section { margin-bottom: 16px; }
    .section-title {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #0f1b4c;
        border-bottom: 2px solid #0f1b4c;
        padding-bottom: 4px;
        margin-bottom: 10px;
    }

    /* Info table */
    .info-grid { width: 100%; border-collapse: collapse; }
    .info-grid td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
    .info-label { color: #6b7280; font-size: 9px; width: 30%; vertical-align: top; }
    .info-value { color: #1f2937; font-weight: 500; }

    /* Form fields */
    .fields-grid { width: 100%; border-collapse: collapse; }
    .field-block { margin-bottom: 10px; page-break-inside: avoid; }
    .field-label {
        font-size: 8px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 3px;
        font-weight: 600;
    }
    .field-value {
        font-size: 10px;
        border: 1px solid #e5e7eb;
        border-radius: 5px;
        padding: 6px 9px;
        min-height: 24px;
        background: #fafbfc;
        line-height: 1.4;
    }
    .field-divider {
        text-align: center;
        color: #94a3b8;
        font-size: 8px;
        margin: 10px 0;
        border-top: 1px dashed #cbd5e1;
        padding-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Signature */
    .signature-box {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
        text-align: center;
        padding: 6px;
    }
    .signature-box img { max-height: 55px; }

    /* Approval history */
    .approval-row {
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 6px;
        page-break-inside: avoid;
    }
    .approval-Aprobado  { border-left: 4px solid #059669; background: #f0fdf4; }
    .approval-Rechazado { border-left: 4px solid #dc2626; background: #fef2f2; }
    .approval-Revisión  { border-left: 4px solid #d97706; background: #fffbeb; }
    .approval-Comentario { border-left: 4px solid #6b7280; background: #f9fafb; }

    /* Footer */
    .footer {
        background: #0f1b4c;
        color: rgba(255,255,255,0.7);
        padding: 12px 30px;
        font-size: 8px;
        position: relative;
    }
    .footer-content {
        display: table;
        width: 100%;
    }
    .footer-left {
        display: table-cell;
        vertical-align: middle;
        width: 60%;
    }
    .footer-right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        width: 40%;
    }

    /* Verification block */
    .verification {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 16px;
        page-break-inside: avoid;
    }
    .verification-title {
        font-size: 8px;
        font-weight: 700;
        color: #0f1b4c;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 6px;
    }
    .verification-grid { width: 100%; border-collapse: collapse; }
    .verification-grid td { padding: 3px 6px; font-size: 8px; }
    .verification-label { color: #6b7280; width: 25%; }
    .verification-value { font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; font-weight: 600; }
</style>
</head>
<body>

<div class="watermark">SAEP</div>

{{-- ═══ HEADER ═══ --}}
<div class="header">
    <div class="header-content">
        <div class="header-left">
            <h1>SAEP</h1>
            <div class="subtitle">Sistema Automatizado de Ejecución y Prevención</div>
        </div>
        <div class="header-right">
            <div class="folio-box">
                <div class="folio-label">Folio</div>
                <div class="folio-number">{{ $folio }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══ STATUS BAR ═══ --}}
<div class="body">
    <div class="status-bar">
        <div class="status-bar-cell" style="width:50%;">
            <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Formulario:</span><br>
            <strong style="font-size:11px;">{{ $respuesta->formulario->nombre }}</strong>
        </div>
        <div class="status-bar-cell" style="text-align:center;width:25%;">
            <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Fecha Emisión:</span><br>
            <strong style="font-size:10px;">{{ $respuesta->created_at->format('d/m/Y') }}</strong>
        </div>
        <div class="status-bar-cell" style="text-align:right;width:25%;">
            <span style="font-size:8px;color:#6b7280;text-transform:uppercase;">Estado:</span><br>
            <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
        </div>
    </div>

    {{-- ═══ INFORMACIÓN DEL SOLICITANTE ═══ --}}
    <div class="section">
        <div class="section-title">Información del Solicitante</div>
        <table class="info-grid">
            <tr>
                <td class="info-label">Nombre completo</td>
                <td class="info-value">{{ $respuesta->usuario->name ?? '—' }}</td>
                <td class="info-label">Correo electrónico</td>
                <td class="info-value">{{ $respuesta->usuario->email ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Departamento</td>
                <td class="info-value">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</td>
                <td class="info-label">Cargo</td>
                <td class="info-value">{{ $respuesta->usuario->cargo->nombre ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">Fecha de envío</td>
                <td class="info-value">{{ $respuesta->created_at->format('d/m/Y H:i:s') }}</td>
                <td class="info-label">Versión formulario</td>
                <td class="info-value">v{{ $respuesta->version_form }}</td>
            </tr>
            @if($respuesta->fecha_resolucion)
            <tr>
                <td class="info-label">Fecha resolución</td>
                <td class="info-value">{{ \Carbon\Carbon::parse($respuesta->fecha_resolucion)->format('d/m/Y H:i') }}</td>
                <td class="info-label">Última actualización</td>
                <td class="info-value">{{ $respuesta->updated_at->format('d/m/Y H:i') }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ═══ DATOS DEL FORMULARIO ═══ --}}
    <div class="section">
        <div class="section-title">Datos del Formulario — {{ $respuesta->formulario->nombre }}</div>
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
                            <div style="font-size:7px;color:#6b7280;margin-top:2px;">Firma electrónica del solicitante</div>
                        </div>
                    @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                        <div class="field-value">
                            📎 {{ $val['name'] }}
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

    {{-- ═══ HISTORIAL DE APROBACIÓN ═══ --}}
    @if($respuesta->aprobaciones->count() > 0)
    <div class="section">
        <div class="section-title">Historial de Aprobación</div>
        @foreach($respuesta->aprobaciones->sortByDesc('fecha') as $ap)
        <div class="approval-row approval-{{ $ap->accion }}">
            <table style="width:100%;">
                <tr>
                    <td style="font-size:10px;font-weight:700;">{{ $ap->aprobador->name ?? '—' }}</td>
                    <td style="text-align:center;">
                        <span class="badge badge-{{ $ap->accion === 'Aprobado' ? 'Aprobado' : ($ap->accion === 'Rechazado' ? 'Rechazado' : 'Revisión') }}">
                            {{ $ap->accion }}
                        </span>
                    </td>
                    <td style="text-align:right;font-size:9px;color:#6b7280;">
                        {{ $ap->fecha ? \Carbon\Carbon::parse($ap->fecha)->format('d/m/Y H:i') : '' }}
                    </td>
                </tr>
            </table>
            @if($ap->comentario)
                <div style="margin-top:4px;font-size:9px;color:#374151;font-style:italic;padding-left:4px;">"{{ $ap->comentario }}"</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- ═══ VERIFICACIÓN DIGITAL ═══ --}}
    <div class="verification">
        <div class="verification-title">🔒 Registro de Verificación Digital</div>
        <table class="verification-grid">
            <tr>
                <td class="verification-label">Folio documento</td>
                <td class="verification-value">{{ $folio }}</td>
                <td class="verification-label">Hash de integridad</td>
                <td class="verification-value">{{ $hash }}</td>
            </tr>
            <tr>
                <td class="verification-label">Fecha generación</td>
                <td class="verification-value">{{ $fechaEmision }}</td>
                <td class="verification-label">Generado por</td>
                <td class="verification-value">{{ auth()->user()->name ?? 'Sistema' }}</td>
            </tr>
            <tr>
                <td class="verification-label">Plataforma</td>
                <td class="verification-value">SAEP v1.0 — saep.bmachero.com</td>
                <td class="verification-label">IP solicitante</td>
                <td class="verification-value">{{ request()->ip() ?? '—' }}</td>
            </tr>
        </table>
        <div style="margin-top:6px;font-size:7px;color:#94a3b8;line-height:1.4;">
            Este documento ha sido generado electrónicamente por la plataforma SAEP y constituye un registro digital
            de la solicitud identificada. El hash de integridad permite verificar la autenticidad del documento.
            Cualquier modificación posterior invalida este registro.
        </div>
    </div>
</div>

{{-- ═══ FOOTER ═══ --}}
<div class="footer">
    <div class="footer-content">
        <div class="footer-left">
            <strong style="color:#fff;font-size:9px;">SAEP Platform</strong> — Sistema Automatizado de Ejecución y Prevención<br>
            Documento generado el {{ $fechaEmision }} · {{ $folio }}
        </div>
        <div class="footer-right">
            <span style="font-size:7px;">Verificación: {{ $hash }}</span><br>
            <span style="font-size:7px;">saep.bmachero.com</span>
        </div>
    </div>
</div>

</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Solicitud #{{ str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e1e2e; background: #fff; }
    .header { background: #4f46e5; color: white; padding: 20px 30px; display: flex; align-items: center; justify-content: space-between; }
    .header h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .header p { font-size: 10px; opacity: 0.85; }
    .header-right { text-align: right; font-size: 10px; }
    .body { padding: 24px 30px; }
    .section { margin-bottom: 20px; }
    .section-title {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 6px; margin-bottom: 12px;
    }
    .info-grid { display: table; width: 100%; }
    .info-row { display: table-row; }
    .info-label { display: table-cell; width: 35%; color: #6b7280; font-size: 10px; padding: 4px 0; vertical-align: top; }
    .info-value { display: table-cell; font-size: 11px; padding: 4px 0; }
    .badge {
        display: inline-block; padding: 2px 8px; border-radius: 50px; font-size: 9px; font-weight: 700;
    }
    .badge-Pendiente  { background: #fef3c7; color: #d97706; }
    .badge-Aprobado   { background: #dcfce7; color: #16a34a; }
    .badge-Rechazado  { background: #fee2e2; color: #dc2626; }
    .badge-Borrador   { background: #f3f4f6; color: #6b7280; }
    .badge-Completado { background: #e0e7ff; color: #4f46e5; }

    .field-block { margin-bottom: 14px; }
    .field-label { font-size: 10px; color: #6b7280; margin-bottom: 4px; }
    .field-value {
        font-size: 11px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 7px 10px;
        min-height: 28px;
        background: #f9fafb;
    }
    .signature-box { border: 1px solid #d1d5db; border-radius: 6px; background: white; text-align: center; padding: 4px; }
    .signature-box img { max-height: 60px; }

    .approval-row { padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; }
    .approval-approved { border-left: 4px solid #16a34a; }
    .approval-rejected { border-left: 4px solid #dc2626; }

    .footer { background: #f3f4f6; padding: 10px 30px; text-align: center; font-size: 9px; color: #9ca3af; margin-top: 30px; }
    .watermark { color: #e5e7eb; font-size: 9px; }
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>SAEP Platform</h1>
        <p>Sistema de Administración de Equipos de Protección</p>
    </div>
    <div class="header-right">
        <strong style="font-size:13px;">Solicitud #{{ str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) }}</strong><br>
        Generado: {{ now()->format('d/m/Y H:i') }}<br>
        <span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span>
    </div>
</div>

<div class="body">

    <div class="section">
        <div class="section-title">Información de la Solicitud</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Formulario</div>
                <div class="info-value">{{ $respuesta->formulario->nombre }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Solicitante</div>
                <div class="info-value">{{ $respuesta->usuario->name ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de envío</div>
                <div class="info-value">{{ $respuesta->created_at->format('d/m/Y H:i') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado</div>
                <div class="info-value"><span class="badge badge-{{ $respuesta->estado }}">{{ $respuesta->estado }}</span></div>
            </div>
            @if($respuesta->updated_at != $respuesta->created_at)
            <div class="info-row">
                <div class="info-label">Última actualización</div>
                <div class="info-value">{{ $respuesta->updated_at->format('d/m/Y H:i') }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Datos del Formulario</div>
        @foreach($schema as $field)
            @if($field['type'] === 'divider')
                <div style="text-align:center;color:#9ca3af;font-size:9px;margin:12px 0;border-top:1px dashed #e5e7eb;padding-top:6px;">{{ $field['label'] }}</div>
            @else
                <div class="field-block">
                    <div class="field-label">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</div>
                    @php $val = $datos[$field['id']] ?? null; @endphp

                    @if($field['type'] === 'signature' && $val && str_starts_with($val, 'data:image'))
                        <div class="signature-box">
                            <img src="{{ $val }}" alt="Firma digital">
                        </div>
                    @elseif($field['type'] === 'file' && is_array($val) && isset($val['name']))
                        <div class="field-value">📎 {{ $val['name'] }} ({{ isset($val['size']) ? number_format($val['size']/1024, 0) . ' KB' : '' }})</div>
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
        <div class="section-title">Historial de Aprobación</div>
        @foreach($respuesta->aprobaciones as $ap)
        <div class="approval-row {{ $ap->accion === 'Aprobar' ? 'approval-approved' : 'approval-rejected' }}">
            <strong style="font-size:11px;">{{ $ap->aprobador->name ?? '—' }}</strong>
            — <span class="badge {{ $ap->accion === 'Aprobar' ? 'badge-Aprobado' : 'badge-Rechazado' }}">{{ $ap->accion }}</span>
            <span style="color:#6b7280;font-size:10px;"> — {{ $ap->fecha?->format('d/m/Y H:i') ?? '' }}</span>
            @if($ap->comentario)
                <div style="margin-top:4px;font-size:10px;color:#374151;">"{{ $ap->comentario }}"</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

</div>

<div class="footer">
    SAEP Platform &bull; Documento generado automáticamente el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
    &bull; Solicitud #{{ str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) }}
</div>
</body>
</html>
