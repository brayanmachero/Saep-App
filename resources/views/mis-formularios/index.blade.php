@extends('layouts.app')

@section('title', 'Mis Formularios')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Mis Formularios</h2>
            <p class="page-subheading">Formularios asignados para completar</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:1.5rem;">
        <div class="glass-card stat-item">
            <div class="stat-icon warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['pendientes'] }}</h3>
                <p>Pendientes</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['completados'] }}</h3>
                <p>Completados</p>
            </div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon danger">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['vencidos'] }}</h3>
                <p>Vencidos</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('mis-formularios.index') }}" class="filter-form">
            <div class="filter-group">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    class="form-input" placeholder="Buscar formulario...">
            </div>
            <div class="filter-group">
                <select name="estado" class="form-input">
                    <option value="">Todos los estados</option>
                    <option value="Pendiente" {{ request('estado') === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="Completado" {{ request('estado') === 'Completado' ? 'selected' : '' }}>Completado</option>
                    <option value="Vencido" {{ request('estado') === 'Vencido' ? 'selected' : '' }}>Vencido</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','estado']))
                <a href="{{ route('mis-formularios.index') }}" class="btn-secondary" style="color:var(--text-muted)">
                    <i class="bi bi-x-lg"></i> Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Tabla -->
    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Formulario</th>
                        <th>Fecha Límite</th>
                        <th>Estado</th>
                        <th>Completado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($asignaciones as $a)
                    <tr>
                        <td>
                            <span style="font-weight:600;color:var(--primary-color)">{{ $a->codigo }}</span>
                        </td>
                        <td>
                            <div style="font-weight:500">{{ $a->nombre }}</div>
                            @if($a->descripcion)
                                <small style="color:var(--text-muted)">{{ Str::limit($a->descripcion, 60) }}</small>
                            @endif
                        </td>
                        <td>
                            @if($a->fecha_limite)
                                @php
                                    $limite = \Carbon\Carbon::parse($a->fecha_limite);
                                    $diasRestantes = now()->startOfDay()->diffInDays($limite->startOfDay(), false);
                                @endphp
                                <span style="{{ $diasRestantes <= 2 && $a->estado === 'Pendiente' ? 'color:#ef4444;font-weight:600' : '' }}">
                                    {{ $limite->format('d/m/Y') }}
                                </span>
                                @if($a->estado === 'Pendiente')
                                    <br><small style="color:{{ $diasRestantes <= 0 ? '#ef4444' : ($diasRestantes <= 2 ? '#f59e0b' : 'var(--text-muted)') }}">
                                        @if($diasRestantes < 0)
                                            Vencido hace {{ abs($diasRestantes) }} día(s)
                                        @elseif($diasRestantes === 0)
                                            Vence hoy
                                        @else
                                            {{ $diasRestantes }} día(s) restante(s)
                                        @endif
                                    </small>
                                @endif
                            @else
                                <span style="color:var(--text-muted)">Sin fecha límite</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeMap = ['Pendiente'=>'warning','Completado'=>'success','Vencido'=>'danger'];
                            @endphp
                            <span class="badge {{ $badgeMap[$a->estado] ?? '' }}">{{ $a->estado }}</span>
                        </td>
                        <td>
                            @if($a->completado_at)
                                {{ \Carbon\Carbon::parse($a->completado_at)->format('d/m/Y H:i') }}
                            @else
                                <span style="color:var(--text-muted)">—</span>
                            @endif
                        </td>
                        <td>
                            @if($a->estado === 'Pendiente')
                                <a href="{{ route('respuestas.create', ['formulario_id' => $a->formulario_id]) }}"
                                    class="btn-premium" style="font-size:.78rem;padding:.35rem .75rem;">
                                    <i class="bi bi-pencil-square"></i> Completar
                                </a>
                            @elseif($a->estado === 'Completado')
                                <span style="color:var(--text-muted);font-size:.82rem"><i class="bi bi-check-lg"></i> Enviado</span>
                            @else
                                <span style="color:#ef4444;font-size:.82rem"><i class="bi bi-clock-history"></i> Vencido</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--text-muted);padding:3rem;">
                            <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:.75rem;"></i>
                            No tienes formularios asignados
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($asignaciones->hasPages())
            <div style="margin-top:1.25rem;">
                {{ $asignaciones->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
