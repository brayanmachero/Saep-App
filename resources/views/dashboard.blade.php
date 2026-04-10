@extends('layouts.app')

@section('title', 'Panel Principal')

@section('content')
<div class="dashboard-container">
    
    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon primary">
                <i class="bi bi-file-earmark-check-fill"></i>
            </div>
            <div class="stat-info">
                <h3>{{ number_format($stats['total_solicitudes']) }}</h3>
                <p>Total Solicitudes</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['pendientes_aprobacion'] }}</h3>
                <p>Pendientes Aprobación</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['empleados_activos'] }}</h3>
                <p>Empleados Activos</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['accion_requerida'] }}</h3>
                <p>Acción Requerida</p>
            </div>
        </div>
    </div>

    <!-- Mis Formularios Pendientes -->
    @if($mis_pendientes->count() > 0)
    <div class="glass-card" style="margin-bottom:1.5rem;border-left:4px solid #f97316;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h2 style="font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-clipboard-check-fill" style="color:#f97316"></i>
                Formularios pendientes por completar
            </h2>
            <a href="{{ route('mis-formularios.index') }}" class="btn-secondary" style="font-size:.8rem;padding:.4rem .75rem;">
                Ver todos <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div style="display:flex;flex-direction:column;gap:.5rem;">
            @foreach($mis_pendientes as $pf)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.65rem .85rem;
                    background:rgba(249,115,22,.04);border:1px solid rgba(249,115,22,.12);border-radius:10px;">
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <span style="font-size:.72rem;background:rgba(249,115,22,.12);color:#f97316;
                            padding:.2rem .5rem;border-radius:6px;font-weight:600;">{{ $pf->codigo }}</span>
                        <span style="font-weight:500;font-size:.9rem;">{{ $pf->nombre }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        @if($pf->fecha_limite)
                            @php
                                $dias = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($pf->fecha_limite)->startOfDay(), false);
                            @endphp
                            <small style="color:{{ $dias <= 2 ? '#ef4444' : 'var(--text-muted)' }};font-size:.78rem;">
                                @if($dias < 0) Vencido
                                @elseif($dias === 0) Vence hoy
                                @else {{ $dias }} día(s)
                                @endif
                            </small>
                        @endif
                        <a href="{{ route('respuestas.create', ['formulario_id' => $pf->formulario_id]) }}"
                            class="btn-premium" style="font-size:.76rem;padding:.3rem .7rem;">
                            <i class="bi bi-pencil-square"></i> Completar
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Recent Activity Table -->
    <div class="glass-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem;">
            <h2 style="font-size: 1.2rem;">Solicitudes Recientes</h2>
            <a href="{{ route('respuestas.index') }}" class="btn-premium" style="font-size:0.8rem; padding:0.5rem 1rem;">
                Ver todas
            </a>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Solicitante</th>
                        <th>Departamento</th>
                        <th>Formulario</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($solicitudes_recientes as $solicitud)
                    <tr>
                        <td>#REQ-{{ str_pad($solicitud->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $solicitud->usuario->name ?? 'N/A' }}</td>
                        <td>{{ $solicitud->usuario->departamento->nombre ?? '—' }}</td>
                        <td>{{ $solicitud->formulario->nombre ?? 'N/A' }}</td>
                        <td>{{ $solicitud->created_at->format('d/m/Y') }}</td>
                        <td>
                            @php
                                $badgeMap = [
                                    'Pendiente' => 'warning',
                                    'Aprobado'  => 'success',
                                    'Rechazado' => 'danger',
                                    'Borrador'  => '',
                                    'Revisión'  => 'warning',
                                ];
                                $cls = $badgeMap[$solicitud->estado] ?? '';
                            @endphp
                            <span class="badge {{ $cls }}">{{ $solicitud->estado }}</span>
                        </td>
                        <td>
                            <a href="{{ route('respuestas.show', $solicitud->id) }}" class="icon-btn" style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">
                            <i class="bi bi-inbox" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                            No hay solicitudes aún
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
