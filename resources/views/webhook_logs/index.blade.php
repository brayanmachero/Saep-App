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
    <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-activity"></i></div>
            <div class="stat-info">
                <h3>{{ $stats['total'] }}</h3>
                <p>Total</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info">
                <h3>{{ $stats['success'] }}</h3>
                <p>Exitosos</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-info">
                <h3>{{ $stats['error'] }}</h3>
                <p>Errores</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-dash-circle-fill"></i></div>
            <div class="stat-info">
                <h3>{{ $stats['ignored'] }}</h3>
                <p>Ignorados</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:#6366f1;"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-info">
                <h3>{{ $stats['hoy'] }}</h3>
                <p>Hoy</p>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('webhook-logs.index') }}" class="filter-form glass-card" style="margin-bottom:1.25rem;">
        <div class="filter-group">
            <label>Tipo</label>
            <select name="tipo" class="form-input">
                <option value="">Todos</option>
                @foreach($tipos as $tipo)
                <option value="{{ $tipo }}" {{ request('tipo') == $tipo ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($tipo)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Estado</label>
            <select name="estado" class="form-input">
                <option value="">Todos</option>
                <option value="success" {{ request('estado') == 'success' ? 'selected' : '' }}>Exitoso</option>
                <option value="error" {{ request('estado') == 'error' ? 'selected' : '' }}>Error</option>
                <option value="ignored" {{ request('estado') == 'ignored' ? 'selected' : '' }}>Ignorado</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Desde</label>
            <input type="date" name="desde" class="form-input" value="{{ request('desde', request()->hasAny(['tipo','estado','desde','hasta','buscar']) ? '' : now()->format('Y-m-d')) }}">
        </div>
        <div class="filter-group">
            <label>Hasta</label>
            <input type="date" name="hasta" class="form-input" value="{{ request('hasta') }}">
        </div>
        <div class="filter-group">
            <label>Buscar</label>
            <input type="text" name="buscar" class="form-input" value="{{ request('buscar') }}" placeholder="Archivo, patente, título...">
        </div>
        <div class="filter-group" style="align-self:flex-end;">
            <button type="submit" class="btn-secondary"><i class="bi bi-search"></i> Buscar</button>
            <a href="{{ route('webhook-logs.index') }}" class="btn-ghost">Limpiar</a>
        </div>
    </form>

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
                            'observacion_conducta' => 'badge-primary',
                            'inspeccion_sst' => 'badge-secondary',
                            'vehiculo' => 'badge-info',
                        ];
                        $tipoLabels = [
                            'vehiculo_entrega' => 'Entrega Vehículo',
                            'vehiculo_devolucion' => 'Devolución Vehículo',
                            'charla_sst' => 'Charla SST',
                            'observacion_conducta' => 'Obs. Conducta',
                            'inspeccion_sst' => 'Inspección SST',
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
