@extends('layouts.app')
@section('title','Carta Gantt SST')
@section('content')
<style>
.gantt-row-title{display:flex;flex-direction:column;gap:.25rem}
.gantt-row-role{display:inline-flex;align-items:center;gap:.25rem;color:var(--text-muted);font-size:.7rem;font-weight:600}
.gantt-row-insights{display:flex;align-items:center;gap:.3rem;flex-wrap:wrap;margin-top:.2rem}
.gantt-row-chip{display:inline-flex;align-items:center;gap:.25rem;border:1px solid var(--border-color,#e5e7eb);border-radius:999px;padding:.13rem .42rem;font-size:.66rem;font-weight:700;color:var(--text-muted);background:var(--surface-bg,#f8fafc);white-space:nowrap}
.gantt-row-chip.is-ok{color:#047857;background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.18)}
.gantt-row-chip.is-warn{color:#b45309;background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.2)}
.gantt-row-chip.is-danger{color:#b91c1c;background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.2)}
@media(max-width:768px){.gantt-row-insights{gap:.25rem}.gantt-row-chip{font-size:.62rem}}
</style>
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-calendar3" style="color:var(--primary-color)"></i> Carta Gantt SST</h2>
            <p class="page-subheading">Programas anuales de Seguridad y Salud en el Trabajo</p>
        </div>
        @if(auth()->user()->tieneAcceso('carta_gantt', 'puede_crear'))
        <a href="{{ route('carta-gantt.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Programa
        </a>
        @endif
    </div>

    @include('partials._alerts')

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Programas</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary-color);">{{ $stats['total'] }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Activos</div>
            <div style="font-size:1.8rem;font-weight:700;color:#16a34a;">{{ $stats['activos'] }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Act. Vencidas</div>
            <div style="font-size:1.8rem;font-weight:700;color:{{ $stats['vencidas'] > 0 ? '#dc2626' : '#16a34a' }};">{{ $stats['vencidas'] }}</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('carta-gantt.index') }}" class="filter-form">
            <div class="filter-group">
                <label>Año</label>
                <select name="anio" class="form-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($anios as $a)
                        <option value="{{ $a }}" {{ request('anio') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Estado</label>
                <select name="estado" class="form-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="ACTIVO" {{ request('estado') === 'ACTIVO' ? 'selected' : '' }}>Activo</option>
                    <option value="BORRADOR" {{ request('estado') === 'BORRADOR' ? 'selected' : '' }}>Borrador</option>
                    <option value="CERRADO" {{ request('estado') === 'CERRADO' ? 'selected' : '' }}>Cerrado</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Centro de Costo</label>
                <select name="centro_costo_id" class="form-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($centros as $cc)
                        <option value="{{ $cc->id }}" {{ request('centro_costo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['anio','estado','centro_costo_id']))
                <a href="{{ route('carta-gantt.index') }}" class="btn-ghost" style="align-self:flex-end;"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    {{-- Tabla --}}
    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead><tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Año</th>
                    <th>Centro Costo</th>
                    <th>Responsable</th>
                    <th>Avance</th>
                    <th>Estado</th>
                    <th style="width:120px;">Acciones</th>
                </tr></thead>
                <tbody>
                @forelse($programas as $prog)
                @php
                    $puedeAdministrarFila = ($puedeAccesoGlobal ?? false) || auth()->id() === $prog->creado_por;
                    $equipoAsignado = $prog->asignados->pluck('nombre_completo')->filter()->values();
                    $resumenOperativo = $prog->resumen_operativo ?? [];
                @endphp
                <tr>
                    <td><code style="background:var(--surface-bg);padding:.15rem .4rem;border-radius:4px;font-size:.8rem;font-weight:600;">{{ $prog->codigo ?? '—' }}</code></td>
                    <td>
                        <div class="gantt-row-title">
                            <strong>{{ $prog->nombre }}</strong>
                            @if(!empty($resumenOperativo['rol']))
                            <span class="gantt-row-role"><i class="bi bi-person-check"></i> Mi rol: {{ $resumenOperativo['rol'] }}</span>
                            @endif
                            <div class="gantt-row-insights" aria-label="Resumen operativo del programa">
                                <span class="gantt-row-chip">
                                    <i class="bi bi-list-check"></i> {{ $resumenOperativo['total_actividades'] ?? 0 }} act.
                                </span>
                                @if(($resumenOperativo['vencidas'] ?? 0) > 0)
                                <span class="gantt-row-chip is-danger" title="Actividades programadas en meses anteriores sin avance">
                                    <i class="bi bi-exclamation-triangle"></i> Vencidas: {{ $resumenOperativo['vencidas'] }}
                                </span>
                                @endif
                                @if(($resumenOperativo['pendientes_mes'] ?? 0) > 0)
                                <span class="gantt-row-chip is-warn" title="Actividades programadas para el mes actual sin cierre">
                                    <i class="bi bi-calendar-event"></i> Mes actual: {{ $resumenOperativo['pendientes_mes'] }}
                                </span>
                                @endif
                                @if(($resumenOperativo['parciales_mes'] ?? 0) > 0)
                                <span class="gantt-row-chip is-warn" title="Actividades del mes actual con avance parcial">
                                    <i class="bi bi-hourglass-split"></i> Parciales: {{ $resumenOperativo['parciales_mes'] }}
                                </span>
                                @endif
                                @if(($resumenOperativo['total_actividades'] ?? 0) > 0 && ($resumenOperativo['vencidas'] ?? 0) === 0 && ($resumenOperativo['pendientes_mes'] ?? 0) === 0)
                                <span class="gantt-row-chip is-ok">
                                    <i class="bi bi-check2-circle"></i> Sin críticos
                                </span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $prog->anio }}</td>
                    <td>{{ $prog->centroCosto->nombre ?? '—' }}</td>
                    <td>
                        <div>{{ $prog->responsable->nombre_completo ?? '—' }}</div>
                        @if($equipoAsignado->isNotEmpty())
                        <small style="display:block;color:var(--text-muted);font-size:.68rem;margin-top:.15rem">
                            <i class="bi bi-people"></i>
                            {{ $equipoAsignado->take(2)->join(', ') }}{{ $equipoAsignado->count() > 2 ? ' +' . ($equipoAsignado->count() - 2) : '' }}
                        </small>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="flex:1;background:#e5e7eb;border-radius:9999px;height:8px;min-width:80px;">
                                <div style="width:{{ $prog->porcentajeRealizado }}%;background:linear-gradient(90deg,var(--primary-color),var(--accent-color,#f97316));height:8px;border-radius:9999px;transition:width .3s;"></div>
                            </div>
                            <span style="font-size:.8rem;font-weight:600;min-width:35px;">{{ $prog->porcentajeRealizado }}%</span>
                        </div>
                    </td>
                    <td><span class="badge {{ $prog->estadoBadge }}">{{ ucfirst(strtolower($prog->estado)) }}</span></td>
                    <td>
                        <div style="display:flex;gap:.35rem;">
                            <a href="{{ route('carta-gantt.show', $prog) }}" class="icon-btn" title="Ver Gantt"><i class="bi bi-grid-3x3-gap-fill"></i></a>
                            <a href="{{ route('carta-gantt.reporte-pdf', $prog) }}" class="icon-btn" title="Reporte PDF" target="_blank"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                            @if($puedeAdministrarFila && auth()->user()->tieneAcceso('carta_gantt', 'puede_editar'))
                            <a href="{{ route('carta-gantt.edit', $prog) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            @endif
                            @if($puedeAdministrarFila && auth()->user()->tieneAcceso('carta_gantt', 'puede_eliminar'))
                            <form method="POST" action="{{ route('carta-gantt.destroy', $prog) }}" style="display:inline" onsubmit="return confirm('¿Cerrar este programa?')">
                                @csrf @method('DELETE')
                                <button class="icon-btn danger" title="Cerrar"><i class="bi bi-archive-fill"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">
                    No hay programas SST. <a href="{{ route('carta-gantt.create') }}">Crear el primero</a>
                </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
