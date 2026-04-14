@extends('layouts.app')
@section('title','Dashboard Kanban')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-graph-up-arrow" style="color:var(--primary-color)"></i> Dashboard Kanban</h2>
            <p class="page-subheading">Métricas y analytics de todos tus tableros</p>
        </div>
        <a href="{{ route('kanban.index') }}" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
            <i class="bi bi-arrow-left"></i> Tableros
        </a>
    </div>

    @include('partials._alerts')

    {{-- KPI Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Tareas</div>
            <div style="font-size:2rem;font-weight:700;color:var(--primary-color);">{{ $totalTareas }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Prioridad Alta</div>
            <div style="font-size:2rem;font-weight:700;color:#dc2626;">{{ $tareasAlta }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Vencidas</div>
            <div style="font-size:2rem;font-weight:700;color:#f59e0b;">{{ $tareasVencidas }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Mis Tareas</div>
            <div style="font-size:2rem;font-weight:700;color:#3b82f6;">{{ $misTareas }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Completadas (30d)</div>
            <div style="font-size:2rem;font-weight:700;color:#16a34a;">{{ $completadasMes }}</div>
        </div>
    </div>

    {{-- Charts row --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">

        {{-- Por columna --}}
        <div class="glass-card" style="padding:1.25rem;">
            <h4 style="font-size:.88rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-bar-chart" style="color:var(--primary-color);"></i> Tareas por Columna</h4>
            @if($porColumna->isEmpty())
                <p style="font-size:.82rem;color:var(--text-muted);text-align:center;padding:1rem;">Sin datos</p>
            @else
                @php $maxCol = $porColumna->max('total') ?: 1; @endphp
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    @foreach($porColumna as $col)
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem;">
                            <span style="font-size:.78rem;font-weight:600;color:var(--text-primary);">
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $col->color }};margin-right:.3rem;vertical-align:middle;"></span>
                                {{ $col->nombre }}
                            </span>
                            <span style="font-size:.75rem;font-weight:700;color:var(--text-muted);">{{ $col->total }}</span>
                        </div>
                        <div style="height:8px;background:var(--border-color);border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:{{ round($col->total / $maxCol * 100) }}%;background:{{ $col->color }};border-radius:4px;transition:width .5s;"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Por prioridad --}}
        <div class="glass-card" style="padding:1.25rem;">
            <h4 style="font-size:.88rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-flag" style="color:var(--primary-color);"></i> Tareas por Prioridad</h4>
            @php
                $prioColors = ['ALTA' => '#dc2626', 'MEDIA' => '#f59e0b', 'BAJA' => '#16a34a'];
                $prioLabels = ['ALTA' => 'Alta', 'MEDIA' => 'Media', 'BAJA' => 'Baja'];
                $prioTotal  = $porPrioridad->sum() ?: 1;
            @endphp
            <div style="display:flex;justify-content:center;align-items:center;gap:2rem;margin-bottom:1rem;">
                {{-- Donut chart via CSS --}}
                @php
                    $alta  = $porPrioridad->get('ALTA', 0);
                    $media = $porPrioridad->get('MEDIA', 0);
                    $baja  = $porPrioridad->get('BAJA', 0);
                    $pctAlta  = round($alta / $prioTotal * 100);
                    $pctMedia = round($media / $prioTotal * 100);
                @endphp
                <div style="width:120px;height:120px;border-radius:50%;background:conic-gradient(#dc2626 0% {{ $pctAlta }}%, #f59e0b {{ $pctAlta }}% {{ $pctAlta + $pctMedia }}%, #16a34a {{ $pctAlta + $pctMedia }}% 100%);display:flex;align-items:center;justify-content:center;position:relative;">
                    <div style="width:72px;height:72px;border-radius:50%;background:var(--card-bg);display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:1.2rem;font-weight:700;color:var(--text-primary);">{{ $prioTotal }}</span>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:.4rem;">
                    @foreach(['ALTA', 'MEDIA', 'BAJA'] as $p)
                    <div style="display:flex;align-items:center;gap:.35rem;font-size:.8rem;">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $prioColors[$p] }};"></span>
                        <span style="font-weight:600;">{{ $prioLabels[$p] }}</span>
                        <span style="color:var(--text-muted);">({{ $porPrioridad->get($p, 0) }})</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Carga por usuario --}}
    <div class="glass-card" style="padding:1.25rem;margin-bottom:1.5rem;">
        <h4 style="font-size:.88rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-people" style="color:var(--primary-color);"></i> Carga por Usuario (Top 10)</h4>
        @if($cargaUsuarios->isEmpty())
            <p style="font-size:.82rem;color:var(--text-muted);text-align:center;padding:1rem;">Sin datos</p>
        @else
            @php $maxUser = $cargaUsuarios->max('total') ?: 1; @endphp
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem .75rem;">
                @foreach($cargaUsuarios as $u)
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.15rem;">
                        <span style="font-size:.78rem;font-weight:600;">
                            <span style="display:inline-flex;width:22px;height:22px;border-radius:50%;background:var(--primary-color);color:#fff;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;margin-right:.3rem;vertical-align:middle;">
                                {{ strtoupper(substr($u->name, 0, 2)) }}
                            </span>
                            {{ $u->name }}
                        </span>
                        <span style="font-size:.75rem;font-weight:700;color:var(--primary-color);">{{ $u->total }}</span>
                    </div>
                    <div style="height:6px;background:var(--border-color);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:{{ round($u->total / $maxUser * 100) }}%;background:var(--primary-color);border-radius:3px;"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Bottom row: próximas a vencer + actividad --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

        {{-- Próximas a vencer --}}
        <div class="glass-card" style="padding:1.25rem;">
            <h4 style="font-size:.88rem;font-weight:700;margin-bottom:.75rem;"><i class="bi bi-exclamation-triangle" style="color:#f59e0b;"></i> Próximas a Vencer (7 días)</h4>
            @if($proximasVencer->isEmpty())
                <p style="font-size:.82rem;color:var(--text-muted);text-align:center;padding:1rem;">Sin tareas próximas a vencer 🎉</p>
            @else
                <div style="max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;">
                    @foreach($proximasVencer as $tarea)
                    @php
                        $dias = (int) now()->startOfDay()->diffInDays($tarea->fecha_vencimiento, false);
                        $urgencia = $dias <= 1 ? '#dc2626' : ($dias <= 3 ? '#f59e0b' : '#3b82f6');
                    @endphp
                    <a href="{{ route('kanban.show', $tarea->tablero_id) }}" style="display:flex;align-items:center;gap:.6rem;padding:.5rem .65rem;border-radius:8px;border:1px solid var(--border-color);text-decoration:none;color:inherit;transition:background .12s;" onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='transparent'">
                        <div style="width:36px;height:36px;border-radius:50%;background:{{ $urgencia }}15;color:{{ $urgencia }};display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">
                            {{ $dias <= 0 ? '!' : $dias.'d' }}
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.8rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tarea->titulo }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted);">{{ $tarea->tablero?->nombre }} · {{ $tarea->columna?->nombre }}</div>
                        </div>
                        <span style="font-size:.7rem;color:{{ $urgencia }};font-weight:700;white-space:nowrap;">{{ $tarea->fecha_vencimiento->format('d/m') }}</span>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Actividad reciente --}}
        <div class="glass-card" style="padding:1.25rem;">
            <h4 style="font-size:.88rem;font-weight:700;margin-bottom:.75rem;"><i class="bi bi-clock-history" style="color:var(--primary-color);"></i> Actividad Reciente</h4>
            @if($actividadReciente->isEmpty())
                <p style="font-size:.82rem;color:var(--text-muted);text-align:center;padding:1rem;">Sin actividad reciente</p>
            @else
                <div style="max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:.4rem;">
                    @foreach($actividadReciente as $act)
                    @php
                        $iconMap = ['created' => 'bi-plus-circle', 'updated' => 'bi-pencil', 'deleted' => 'bi-trash', 'moved' => 'bi-arrows-move'];
                        $colorMap = ['created' => '#16a34a', 'updated' => '#3b82f6', 'deleted' => '#dc2626', 'moved' => '#8b5cf6'];
                        $icon = $iconMap[$act->accion] ?? 'bi-activity';
                        $color = $colorMap[$act->accion] ?? '#6b7280';
                    @endphp
                    <div style="display:flex;align-items:flex-start;gap:.5rem;padding:.4rem .5rem;border-radius:6px;border-left:3px solid {{ $color }};">
                        <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:.75rem;margin-top:.15rem;flex-shrink:0;"></i>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.75rem;color:var(--text-primary);line-height:1.4;">
                                <strong>{{ $act->usuario?->name ?? 'Sistema' }}</strong>
                                {{ Str::limit($act->descripcion, 80) }}
                            </div>
                            <div style="font-size:.65rem;color:var(--text-muted);">{{ $act->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
