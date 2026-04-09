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
