@extends('layouts.app')
@section('title','Webhook Monitor')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Webhook Monitor</h2>
            <p class="page-subheading">Seguimiento de webhooks recibidos y documentos procesados</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:24px;">
        <div class="glass-card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:var(--text-primary)">{{ $stats['total'] }}</div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Total</div>
        </div>
        <div class="glass-card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#16a34a">{{ $stats['success'] }}</div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Exitosos</div>
        </div>
        <div class="glass-card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#dc2626">{{ $stats['error'] }}</div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Errores</div>
        </div>
        <div class="glass-card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#f59e0b">{{ $stats['ignored'] }}</div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Ignorados</div>
        </div>
        <div class="glass-card" style="padding:16px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#6366f1">{{ $stats['hoy'] }}</div>
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Hoy</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="glass-card" style="padding:16px;margin-bottom:20px;">
        <form method="GET" action="{{ route('webhook-logs.index') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;">
            <div style="flex:1;min-width:160px;">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Tipo</label>
                <select name="tipo" class="form-select" style="width:100%;">
                    <option value="">Todos</option>
                    @foreach($tipos as $tipo)
                    <option value="{{ $tipo }}" {{ request('tipo') == $tipo ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($tipo)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:0 0 130px;">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Estado</label>
                <select name="estado" class="form-select" style="width:100%;">
                    <option value="">Todos</option>
                    <option value="success" {{ request('estado') == 'success' ? 'selected' : '' }}>Exitoso</option>
                    <option value="error" {{ request('estado') == 'error' ? 'selected' : '' }}>Error</option>
                    <option value="ignored" {{ request('estado') == 'ignored' ? 'selected' : '' }}>Ignorado</option>
                </select>
            </div>
            <div style="flex:0 0 150px;">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Desde</label>
                <input type="date" name="desde" class="form-control" value="{{ request('desde') }}" style="width:100%;">
            </div>
            <div style="flex:0 0 150px;">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}" style="width:100%;">
            </div>
            <div style="flex:1;min-width:180px;">
                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Buscar</label>
                <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Archivo, patente, título...">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn-premium" style="padding:8px 16px;font-size:13px;"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('webhook-logs.index') }}" class="btn-premium" style="padding:8px 16px;font-size:13px;background:var(--bg-secondary);color:var(--text-primary);"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    {{-- Tabla de logs --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th style="width:160px;">Fecha</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Resumen</th>
                <th>Archivo</th>
                <th>SharePoint</th>
                <th style="width:60px;">Email</th>
                <th style="width:50px;"></th>
            </tr></thead>
            <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size:13px;white-space:nowrap;">
                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                </td>
                <td>
                    @php
                        $tipoColors = [
                            'vehiculo_entrega' => 'badge-info',
                            'vehiculo_devolucion' => 'badge-warning',
                            'charla_sst' => 'badge-success',
                            'vehiculo' => 'badge-info',
                        ];
                        $tipoLabels = [
                            'vehiculo_entrega' => 'Entrega Vehículo',
                            'vehiculo_devolucion' => 'Devolución Vehículo',
                            'charla_sst' => 'Charla SST',
                            'no_registrado' => 'No Registrado',
                            'sin_identificar' => 'Sin Identificar',
                        ];
                    @endphp
                    <span class="badge {{ $tipoColors[$log->tipo] ?? 'badge-secondary' }}">
                        {{ $tipoLabels[$log->tipo] ?? str_replace('_', ' ', ucfirst($log->tipo)) }}
                    </span>
                </td>
                <td>
                    @if($log->estado === 'success')
                        <span class="badge badge-success"><i class="bi bi-check-circle"></i> OK</span>
                    @elseif($log->estado === 'error')
                        <span class="badge badge-danger"><i class="bi bi-x-circle"></i> Error</span>
                    @else
                        <span class="badge badge-warning"><i class="bi bi-dash-circle"></i> Ignorado</span>
                    @endif
                </td>
                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->resumen }}">
                    {{ $log->resumen ?? '-' }}
                </td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;" title="{{ $log->archivo }}">
                    @if($log->archivo)
                        <i class="bi bi-file-pdf" style="color:#dc2626;"></i> {{ $log->archivo }}
                    @else
                        <span style="color:var(--text-muted)">-</span>
                    @endif
                </td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;" title="{{ $log->sharepoint_path }}">
                    @if($log->sharepoint_path)
                        <i class="bi bi-cloud-check" style="color:#16a34a;"></i> {{ $log->sharepoint_path }}
                    @else
                        <span style="color:var(--text-muted)">-</span>
                    @endif
                </td>
                <td style="text-align:center;">
                    @if($log->email_enviado)
                        <i class="bi bi-envelope-check-fill" style="color:#16a34a;" title="Enviado a: {{ implode(', ', $log->destinatarios ?? []) }}"></i>
                    @else
                        <i class="bi bi-envelope-x" style="color:var(--text-muted);" title="No enviado"></i>
                    @endif
                </td>
                <td>
                    <button type="button" class="icon-btn" title="Ver detalle" onclick="toggleDetail({{ $log->id }})">
                        <i class="bi bi-chevron-down" id="icon-{{ $log->id }}"></i>
                    </button>
                </td>
            </tr>
            {{-- Fila detalle expandible --}}
            <tr id="detail-{{ $log->id }}" style="display:none;">
                <td colspan="8" style="background:var(--bg-secondary);padding:16px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
                        <div>
                            <strong>Form ID:</strong> {{ $log->form_id ?? '-' }}<br>
                            <strong>Data ID:</strong> {{ $log->data_id ?? '-' }}<br>
                            <strong>IP:</strong> {{ $log->ip ?? '-' }}<br>
                            <strong>Origen:</strong> {{ $log->origen }}
                        </div>
                        <div>
                            @if($log->metadata)
                                <strong>Metadata:</strong><br>
                                @foreach($log->metadata as $key => $val)
                                    <span style="color:var(--text-muted)">{{ $key }}:</span> {{ $val }}<br>
                                @endforeach
                            @endif
                        </div>
                        @if($log->destinatarios && count($log->destinatarios) > 0)
                        <div>
                            <strong>Destinatarios:</strong> {{ implode(', ', $log->destinatarios) }}
                        </div>
                        @endif
                        @if($log->error_message)
                        <div style="grid-column:1/-1;">
                            <strong style="color:#dc2626;">Error:</strong>
                            <code style="display:block;background:#fef2f2;padding:8px;border-radius:4px;margin-top:4px;white-space:pre-wrap;font-size:12px;color:#991b1b;">{{ $log->error_message }}</code>
                        </div>
                        @endif
                        @if($log->sharepoint_path)
                        <div style="grid-column:1/-1;">
                            <strong>Ruta completa SharePoint:</strong><br>
                            <code style="font-size:12px;">{{ $log->sharepoint_path }}</code>
                        </div>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay registros de webhooks aún. Los webhooks se registrarán automáticamente cuando Kizeo envíe datos.
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if($logs->hasPages())
        <div style="padding:16px;display:flex;justify-content:center;">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>

<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    const icon = document.getElementById('icon-' + id);
    if (row.style.display === 'none') {
        row.style.display = '';
        icon.className = 'bi bi-chevron-up';
    } else {
        row.style.display = 'none';
        icon.className = 'bi bi-chevron-down';
    }
}
</script>
@endsection
