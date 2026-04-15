@extends('layouts.app')
@section('title','Búsqueda Kanban')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-search" style="color:var(--primary-color)"></i> Búsqueda Global</h2>
            <p class="page-subheading">Buscar tareas en todos tus tableros</p>
        </div>
        <a href="{{ route('kanban.index') }}" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
            <i class="bi bi-arrow-left"></i> Tableros
        </a>
    </div>

    @include('partials._alerts')

    {{-- Search form --}}
    <div class="glass-card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('kanban.buscar') }}" style="display:flex;gap:.5rem;align-items:center;">
            <div style="flex:1;position:relative;">
                <i class="bi bi-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.85rem;"></i>
                <input type="text" name="q" value="{{ $q }}" class="form-input" placeholder="Buscar por título o descripción (mín. 2 caracteres)..." style="padding-left:2.2rem;font-size:.88rem;" autofocus>
            </div>
            <button type="submit" class="btn-premium" style="padding:.5rem 1.2rem;font-size:.85rem;">
                <i class="bi bi-search"></i> Buscar
            </button>
        </form>
    </div>

    {{-- Results --}}
    @if(strlen($q) >= 2)
        @if($tareas->isEmpty())
            <div class="glass-card" style="padding:2.5rem;text-align:center;">
                <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--text-muted);opacity:.4;"></i>
                <p style="margin-top:.75rem;color:var(--text-muted);font-size:.9rem;">No se encontraron tareas para "<strong>{{ $q }}</strong>"</p>
            </div>
        @else
            <div style="margin-bottom:.75rem;font-size:.82rem;color:var(--text-muted);">
                <i class="bi bi-list-check"></i> {{ $tareas->count() }} resultado{{ $tareas->count() !== 1 ? 's' : '' }} para "<strong>{{ $q }}</strong>"
            </div>
            <div style="display:flex;flex-direction:column;gap:.75rem;">
                @foreach($tareas as $tarea)
                @php
                    $pColors = ['ALTA' => '#dc2626', 'MEDIA' => '#f59e0b', 'BAJA' => '#16a34a'];
                    $pColor = $pColors[$tarea->prioridad] ?? '#6b7280';
                @endphp
                <a href="{{ route('kanban.show', $tarea->tablero_id) }}" class="glass-card" style="padding:1rem 1.25rem;text-decoration:none;color:inherit;border-left:4px solid {{ $pColor }};transition:transform .12s;" onmouseover="this.style.transform='translateX(4px)'" onmouseout="this.style.transform=''">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.35rem;">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span style="font-size:.88rem;font-weight:700;color:var(--text-primary);">{{ $tarea->titulo }}</span>
                            <span style="font-size:.68rem;font-weight:700;color:{{ $pColor }};padding:.1rem .35rem;border-radius:4px;background:{{ $pColor }}15;">{{ $tarea->prioridad }}</span>
                        </div>
                        @if($tarea->fecha_vencimiento)
                        <span style="font-size:.72rem;color:{{ $tarea->estaVencida ? '#dc2626' : 'var(--text-muted)' }};font-weight:{{ $tarea->estaVencida ? '700' : '400' }};">
                            <i class="bi bi-clock"></i> {{ $tarea->fecha_vencimiento->format('d/m/Y') }}
                        </span>
                        @endif
                    </div>
                    @if($tarea->descripcion)
                    <p style="font-size:.78rem;color:var(--text-muted);margin:0 0 .5rem;line-height:1.4;">{{ Str::limit($tarea->descripcion, 150) }}</p>
                    @endif
                    <div style="display:flex;align-items:center;gap:.75rem;font-size:.72rem;color:var(--text-muted);">
                        <span><i class="bi bi-kanban"></i> {{ $tarea->tablero?->nombre }}</span>
                        <span><i class="bi bi-columns-gap"></i> {{ $tarea->columna?->nombre }}</span>
                        @if($tarea->asignados->isNotEmpty())
                        <span><i class="bi bi-people"></i> {{ $tarea->asignados->pluck('name')->join(', ') }}</span>
                        @endif
                        @if($tarea->etiquetas->isNotEmpty())
                        <div style="display:flex;gap:.2rem;">
                            @foreach($tarea->etiquetas->take(3) as $et)
                            <span style="font-size:.6rem;padding:.1rem .3rem;border-radius:3px;background:{{ $et->color }}20;color:{{ $et->color }};font-weight:700;">{{ $et->nombre }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    @elseif(strlen($q) > 0)
        <div class="glass-card" style="padding:2rem;text-align:center;">
            <p style="color:var(--text-muted);font-size:.85rem;">Ingresa al menos 2 caracteres para buscar.</p>
        </div>
    @endif
</div>
@endsection
