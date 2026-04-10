@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="dashboard-container">

    <!-- Saludo -->
    <div style="margin-bottom:1.5rem;">
        <h2 style="font-size:1.4rem;font-weight:700;color:var(--text-color);">
            Hola, {{ explode(' ', auth()->user()->name ?? 'Usuario')[0] }} 👋
        </h2>
        <p style="color:var(--text-muted);font-size:.9rem;">Resumen de tus formularios asignados</p>
    </div>

    <!-- Stats Row -->
    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon primary">
                <i class="bi bi-clipboard-data-fill"></i>
            </div>
            <div class="stat-info">
                <h3>{{ $stats['total'] }}</h3>
                <p>Total Asignados</p>
            </div>
        </div>
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

    <!-- Formularios Pendientes -->
    <div class="glass-card" style="margin-top:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h2 style="font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-clipboard-check-fill" style="color:#f97316"></i>
                Formularios pendientes por completar
            </h2>
            <a href="{{ route('mis-formularios.index') }}" class="btn-secondary" style="font-size:.8rem;padding:.4rem .75rem;">
                Ver todos <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        @if($mis_pendientes->count() > 0)
        <div style="display:flex;flex-direction:column;gap:.5rem;">
            @foreach($mis_pendientes as $pf)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.65rem .85rem;
                    background:rgba(249,115,22,.04);border:1px solid rgba(249,115,22,.12);border-radius:10px;flex-wrap:wrap;gap:.5rem;">
                    <div style="display:flex;align-items:center;gap:.75rem;flex:1;min-width:0;">
                        <span style="font-size:.72rem;background:rgba(249,115,22,.12);color:#f97316;
                            padding:.2rem .5rem;border-radius:6px;font-weight:600;white-space:nowrap;">{{ $pf->codigo }}</span>
                        <div style="min-width:0;">
                            <span style="font-weight:500;font-size:.9rem;display:block;">{{ $pf->nombre }}</span>
                            @if($pf->descripcion)
                                <small style="color:var(--text-muted);font-size:.75rem;">{{ Str::limit($pf->descripcion, 50) }}</small>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        @if($pf->fecha_limite)
                            @php
                                $dias = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($pf->fecha_limite)->startOfDay(), false);
                            @endphp
                            <small style="color:{{ $dias <= 2 ? '#ef4444' : 'var(--text-muted)' }};font-size:.78rem;white-space:nowrap;">
                                @if($dias < 0) Vencido
                                @elseif($dias === 0) Vence hoy
                                @else {{ $dias }} día(s)
                                @endif
                            </small>
                        @else
                            <small style="color:var(--text-muted);font-size:.75rem;">Vigente</small>
                        @endif
                        <a href="{{ route('respuestas.create', ['formulario_id' => $pf->formulario_id]) }}"
                            class="btn-premium" style="font-size:.76rem;padding:.3rem .7rem;white-space:nowrap;">
                            <i class="bi bi-pencil-square"></i> Completar
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
        @else
        <div style="text-align:center;padding:2.5rem 1rem;color:var(--text-muted);">
            <i class="bi bi-check-circle" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:.75rem;"></i>
            <p style="font-size:1rem;font-weight:500;">No tienes formularios pendientes</p>
            <p style="font-size:.85rem;margin-top:.25rem;">Todos tus formularios están al día</p>
        </div>
        @endif
    </div>

</div>
@endsection
