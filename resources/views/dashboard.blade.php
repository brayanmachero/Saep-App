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
