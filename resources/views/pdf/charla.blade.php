<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Charla SST #{{ str_pad($charla->id, 5, '0', STR_PAD_LEFT) }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e1e2e; background: #fff; }
    .header { background: #059669; color: white; padding: 20px 30px; }
    .header h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .header-meta { display: flex; justify-content: space-between; font-size: 10px; margin-top: 8px; opacity: 0.9; }
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
    table.asistentes { width: 100%; border-collapse: collapse; }
    table.asistentes th {
        background: #f3f4f6; font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.03em; color: #6b7280; padding: 8px 10px; text-align: left;
        border-bottom: 2px solid #e5e7eb;
    }
    table.asistentes td { padding: 10px; border-bottom: 1px solid #f3f4f6; font-size: 11px; vertical-align: middle; }
    table.asistentes tr:last-child td { border-bottom: none; }
    .status-firmado  { color: #16a34a; font-weight: 700; }
    .status-pendiente { color: #d97706; }
    .signature-cell img { max-height: 45px; max-width: 120px; background: white; border: 1px solid #e5e7eb; border-radius: 4px; padding: 2px; }
    .contenido-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; font-size: 11px; line-height: 1.6; white-space: pre-wrap; }
    .footer { background: #f3f4f6; padding: 10px 30px; text-align: center; font-size: 9px; color: #9ca3af; margin-top: 30px; }
    .badge-completada { background: #dcfce7; color: #16a34a; }
    .badge-programada { background: #fef3c7; color: #d97706; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 50px; font-size: 9px; font-weight: 700; }
</style>
</head>
<body>

<div class="header">
    <h1>Charla SST — {{ $charla->titulo }}</h1>
    <div class="header-meta">
        <span>Tipo: {{ $charla->tipo }}</span>
        <span>Fecha: {{ $charla->fecha_programada->format('d/m/Y H:i') }}</span>
        <span>Duración: {{ $charla->duracion_minutos }} min</span>
        <span>Estado:
            <span class="badge badge-{{ strtolower($charla->estado) }}">{{ $charla->estado }}</span>
        </span>
    </div>
</div>

<div class="body">

    <div class="section">
        <div class="section-title">Información de la Charla</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Lugar</div>
                <div class="info-value">{{ $charla->lugar ?: '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Supervisor / Relator</div>
                <div class="info-value">{{ $charla->supervisor->name ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Creado por</div>
                <div class="info-value">{{ $charla->creador->name ?? '—' }}</div>
            </div>
            @if($charla->fecha_dictado)
            <div class="info-row">
                <div class="info-label">Dictada el</div>
                <div class="info-value">{{ $charla->fecha_dictado->format('d/m/Y H:i') }}</div>
            </div>
            @endif
        </div>
    </div>

    @if($charla->contenido)
    <div class="section">
        <div class="section-title">Contenido / Temario</div>
        <div class="contenido-box">{{ $charla->contenido }}</div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Lista de Asistencia ({{ $charla->asistentes->count() }} convocados)</div>
        <table class="asistentes">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Cargo / Rol</th>
                    <th>Estado</th>
                    <th>Fecha Firma</th>
                    <th>Firma</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charla->asistentes as $i => $asistente)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $asistente->usuario->name ?? '—' }}</strong></td>
                    <td>{{ $asistente->usuario->rol->nombre ?? '—' }}</td>
                    <td class="{{ $asistente->estado === 'FIRMADO' ? 'status-firmado' : 'status-pendiente' }}">
                        {{ $asistente->estado }}
                    </td>
                    <td>{{ $asistente->fecha_firma?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="signature-cell">
                        @if($asistente->firma_imagen && str_starts_with($asistente->firma_imagen, 'data:image'))
                            <img src="{{ $asistente->firma_imagen }}" alt="Firma">
                        @else
                            <span style="color:#d1d5db;font-size:10px;">Sin firma</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#9ca3af;">Sin asistentes registrados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<div class="footer">
    SAEP Platform &bull; Charla SST #{{ str_pad($charla->id, 5, '0', STR_PAD_LEFT) }}
    &bull; Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
</div>
</body>
</html>
