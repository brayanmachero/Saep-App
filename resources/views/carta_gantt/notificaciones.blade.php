@extends('layouts.app')
@section('title','Notificaciones Carta Gantt')
@section('content')
<style>
.gantt-notif-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.8rem;margin-bottom:1rem}
.gantt-notif-kpi{padding:.9rem 1rem}
.gantt-notif-kpi span{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:800}
.gantt-notif-kpi strong{display:block;font-size:1.55rem;line-height:1.1;margin-top:.25rem}
.gantt-notif-filter{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr)) auto;gap:.65rem;align-items:end}
.gantt-notif-type{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.22rem .55rem;font-size:.68rem;font-weight:800;white-space:nowrap}
.gantt-notif-type.vencida{background:rgba(239,68,68,.12);color:#b91c1c}
.gantt-notif-type.vencimiento{background:rgba(245,158,11,.12);color:#b45309}
.gantt-notif-type.recordatorio{background:rgba(79,70,229,.12);color:#4338ca}
.gantt-notif-type.seguimiento_pendiente{background:rgba(234,88,12,.12);color:#c2410c}
.gantt-notif-type.asignacion{background:rgba(37,99,235,.12);color:#1d4ed8}
@media(max-width:1100px){.gantt-notif-filter{grid-template-columns:1fr 1fr}}
@media(max-width:720px){.gantt-notif-filter{grid-template-columns:1fr}.glass-table-container{overflow:auto}}
</style>

<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-envelope-check" style="color:var(--primary-color)"></i> Notificaciones Carta Gantt</h2>
            <p class="page-subheading">Auditoría de avisos enviados por asignación, vencimiento, resumen diario y seguimiento.</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('carta-gantt.mis-tareas') }}" class="btn-ghost"><i class="bi bi-list-task"></i> Mis tareas</a>
            <a href="{{ route('carta-gantt.index') }}" class="btn-ghost"><i class="bi bi-arrow-left"></i> Programas</a>
        </div>
    </div>

    @include('partials._alerts')

    <div class="gantt-notif-kpis">
        <div class="glass-card gantt-notif-kpi">
            <span>Hoy</span>
            <strong style="color:var(--primary-color)">{{ $stats['hoy'] }}</strong>
        </div>
        <div class="glass-card gantt-notif-kpi">
            <span>Últimos 7 días</span>
            <strong style="color:#2563eb">{{ $stats['semana'] }}</strong>
        </div>
        <div class="glass-card gantt-notif-kpi">
            <span>Vencidas</span>
            <strong style="color:#dc2626">{{ $stats['vencidas'] }}</strong>
        </div>
        <div class="glass-card gantt-notif-kpi">
            <span>Total histórico</span>
            <strong style="color:#64748b">{{ $stats['total'] }}</strong>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('carta-gantt.notificaciones') }}" class="gantt-notif-filter">
            <div class="filter-group">
                <label>Tipo</label>
                <select name="tipo" class="form-input">
                    <option value="">Todos</option>
                    <option value="asignacion" {{ request('tipo') === 'asignacion' ? 'selected' : '' }}>Asignación</option>
                    <option value="vencimiento" {{ request('tipo') === 'vencimiento' ? 'selected' : '' }}>Por vencer</option>
                    <option value="vencida" {{ request('tipo') === 'vencida' ? 'selected' : '' }}>Vencida</option>
                    <option value="recordatorio" {{ request('tipo') === 'recordatorio' ? 'selected' : '' }}>Recordatorio</option>
                    <option value="seguimiento_pendiente" {{ request('tipo') === 'seguimiento_pendiente' ? 'selected' : '' }}>Seguimiento pendiente</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Programa</label>
                <select name="programa_id" class="form-input">
                    <option value="">Todos</option>
                    @foreach($programas as $programa)
                        <option value="{{ $programa->id }}" {{ request('programa_id') == $programa->id ? 'selected' : '' }}>
                            {{ $programa->codigo ?? 'SST' }} · {{ \Illuminate\Support\Str::limit($programa->nombre, 32) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Email</label>
                <input type="text" name="email" value="{{ request('email') }}" class="form-input" placeholder="destinatario@saep.cl">
            </div>
            <div class="filter-group">
                <label>Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-input">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-input">
            </div>
            <button class="btn-premium" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
            @if(request()->query())
                <a href="{{ route('carta-gantt.notificaciones') }}" class="btn-ghost"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Destinatario</th>
                        <th>Rol</th>
                        <th>Actividad</th>
                        <th>Programa</th>
                        <th>Mes</th>
                        <th style="width:90px;text-align:right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    @php
                        $actividad = $log->actividad;
                        $programa = $actividad?->categoria?->programa;
                        $tipoLabels = [
                            'asignacion' => 'Asignación',
                            'vencimiento' => 'Por vencer',
                            'vencida' => 'Vencida',
                            'recordatorio' => 'Recordatorio',
                            'seguimiento_pendiente' => 'Seguimiento',
                        ];
                    @endphp
                    <tr>
                        <td>
                            <div style="font-size:.78rem;font-weight:700">{{ $log->created_at?->format('d/m/Y') }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ $log->created_at?->format('H:i') }}</div>
                        </td>
                        <td>
                            <span class="gantt-notif-type {{ $log->tipo }}">{{ $tipoLabels[$log->tipo] ?? $log->tipo }}</span>
                        </td>
                        <td>
                            <div style="font-size:.78rem;font-weight:700">{{ $log->user?->nombre_completo ?? 'Sin usuario' }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ $log->email }}</div>
                        </td>
                        <td>{{ ucfirst($log->rol_destinatario ?? '—') }}</td>
                        <td>
                            <div style="font-size:.8rem;font-weight:700">{{ $actividad?->nombre ?? 'Actividad eliminada' }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ $actividad?->responsableUser?->nombre_completo ?? $actividad?->responsable ?? '—' }}</div>
                        </td>
                        <td>
                            <div style="font-size:.78rem;font-weight:700">{{ $programa?->nombre ?? '—' }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ $programa?->codigo ?? '—' }}</div>
                        </td>
                        <td>{{ $log->mes ?? '—' }}</td>
                        <td style="text-align:right">
                            @if($programa)
                                <a href="{{ route('carta-gantt.show', $programa) }}{{ $actividad ? '#actividad-' . $actividad->id : '' }}" class="icon-btn" title="Abrir actividad"><i class="bi bi-eye"></i></a>
                            @else
                                <span style="color:var(--text-muted)">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-muted)">
                            No hay notificaciones con estos filtros.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:1rem 0 0">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
