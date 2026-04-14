@extends('layouts.app')
@section('title','Mis Tareas Kanban')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-person-check" style="color:var(--primary-color)"></i> Mis Tareas</h2>
            <p class="page-subheading">Todas las tareas asignadas a ti en tableros Kanban</p>
        </div>
        <a href="{{ route('kanban.index') }}" class="btn-secondary" style="padding:.4rem .75rem;font-size:.82rem;">
            <i class="bi bi-arrow-left"></i> Tableros
        </a>
    </div>

    @include('partials._alerts')

    {{-- Stats --}}
    @php
        $total    = $tareas->count();
        $alta     = $tareas->where('prioridad', 'ALTA')->count();
        $vencidas = $tareas->filter(fn($t) => $t->estaVencida)->count();
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:.75rem 1rem;text-align:center;">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total</div>
            <div style="font-size:1.5rem;font-weight:700;color:var(--primary-color);">{{ $total }}</div>
        </div>
        <div class="glass-card" style="padding:.75rem 1rem;text-align:center;">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Prioridad Alta</div>
            <div style="font-size:1.5rem;font-weight:700;color:#ef4444;">{{ $alta }}</div>
        </div>
        <div class="glass-card" style="padding:.75rem 1rem;text-align:center;">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Vencidas</div>
            <div style="font-size:1.5rem;font-weight:700;color:#f59e0b;">{{ $vencidas }}</div>
        </div>
    </div>

    @if($tareas->isEmpty())
        <div class="glass-card" style="padding:3rem;text-align:center;">
            <i class="bi bi-check-circle" style="font-size:3rem;color:var(--text-muted);opacity:.4;"></i>
            <p style="margin-top:1rem;color:var(--text-muted);">No tienes tareas asignadas.</p>
        </div>
    @else
        @php $agrupadasPorTablero = $tareas->groupBy('tablero.nombre'); @endphp

        @foreach($agrupadasPorTablero as $nombreTablero => $tareasTablero)
        <div class="glass-card" style="margin-bottom:1.25rem;overflow:hidden;">
            <div style="padding:.75rem 1rem;background:var(--primary-color);color:#fff;font-weight:700;font-size:.85rem;">
                <i class="bi bi-kanban"></i> {{ $nombreTablero }}
                <span style="float:right;opacity:.7;font-weight:400;">{{ $tareasTablero->count() }} tareas</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="premium-table" style="margin:0;">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">Tarea</th>
                            <th style="width:90px;">Prioridad</th>
                            <th style="width:120px;">Columna</th>
                            <th style="width:110px;">Vencimiento</th>
                            <th style="width:100px;">Checklist</th>
                            <th style="width:80px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tareasTablero as $tarea)
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:.85rem;">{{ $tarea->titulo }}</div>
                                @if($tarea->etiquetas->count())
                                <div style="display:flex;gap:.25rem;margin-top:.25rem;flex-wrap:wrap;">
                                    @foreach($tarea->etiquetas as $etq)
                                    <span style="font-size:.65rem;background:{{ $etq->color }};color:#fff;padding:.1rem .35rem;border-radius:8px;">{{ $etq->nombre }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $prioColores = ['ALTA' => '#ef4444', 'MEDIA' => '#f59e0b', 'BAJA' => '#22c55e'];
                                @endphp
                                <span style="font-size:.7rem;font-weight:700;color:{{ $prioColores[$tarea->prioridad] ?? '#888' }};">
                                    {{ $tarea->prioridad }}
                                </span>
                            </td>
                            <td style="font-size:.78rem;">{{ $tarea->columna?->nombre }}</td>
                            <td style="font-size:.78rem;{{ $tarea->estaVencida ? 'color:#ef4444;font-weight:600;' : '' }}">
                                {{ $tarea->fecha_vencimiento ? \Carbon\Carbon::parse($tarea->fecha_vencimiento)->format('d/m/Y') : '—' }}
                            </td>
                            <td>
                                @php $p = $tarea->checklistProgreso; @endphp
                                @if($p['total'] > 0)
                                <div style="display:flex;align-items:center;gap:.35rem;font-size:.75rem;">
                                    <div style="flex:1;height:4px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                                        <div style="width:{{ $p['total'] > 0 ? round($p['completados']/$p['total']*100) : 0 }}%;height:100%;background:#22c55e;"></div>
                                    </div>
                                    {{ $p['completados'] }}/{{ $p['total'] }}
                                </div>
                                @else
                                    <span style="font-size:.75rem;color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('kanban.show', [$tarea->tablero_id, 'vista' => 'kanban']) }}"
                                   class="btn-secondary" style="padding:.25rem .5rem;font-size:.7rem;"
                                   title="Ir al tablero">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @endif
</div>
@endsection
