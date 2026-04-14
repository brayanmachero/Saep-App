@extends('layouts.app')
@section('title','Tablero Kanban')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-kanban" style="color:var(--primary-color)"></i> Tableros Kanban</h2>
            <p class="page-subheading">Gestión visual de tareas con arrastrar y soltar</p>
        </div>
        @if(auth()->user()->tieneAcceso('kanban', 'puede_crear'))
        <a href="{{ route('kanban.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Tablero
        </a>
        @endif
    </div>

    @include('partials._alerts')

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Tableros</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary-color);">{{ $tableros->count() }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Tareas</div>
            <div style="font-size:1.8rem;font-weight:700;color:#3b82f6;">{{ $tableros->sum('tareas_count') }}</div>
        </div>
    </div>

    {{-- Grid de tableros --}}
    @if($tableros->isEmpty())
        <div class="glass-card" style="padding:3rem;text-align:center;">
            <i class="bi bi-kanban" style="font-size:3rem;color:var(--text-muted);opacity:.4;"></i>
            <p style="margin-top:1rem;color:var(--text-muted);">No hay tableros creados aún.</p>
            @if(auth()->user()->tieneAcceso('kanban', 'puede_crear'))
            <a href="{{ route('kanban.create') }}" class="btn-premium" style="margin-top:1rem;display:inline-flex;">
                <i class="bi bi-plus-lg"></i> Crear primer tablero
            </a>
            @endif
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;">
            @foreach($tableros as $tablero)
            <a href="{{ route('kanban.show', $tablero) }}" class="glass-card" style="padding:1.25rem;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s;cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 25px rgba(0,0,0,.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
                    <h3 style="font-size:1.05rem;font-weight:700;margin:0;color:var(--text-primary);">
                        <i class="bi bi-kanban" style="color:var(--primary-color);margin-right:.4rem;"></i>
                        {{ $tablero->nombre }}
                    </h3>
                    <span style="font-size:.7rem;background:var(--primary-color);color:#fff;padding:.15rem .5rem;border-radius:10px;font-weight:600;">
                        {{ $tablero->tareas_count }} tareas
                    </span>
                </div>
                @if($tablero->descripcion)
                <p style="font-size:.82rem;color:var(--text-muted);margin:0 0 .75rem;line-height:1.4;">
                    {{ Str::limit($tablero->descripcion, 100) }}
                </p>
                @endif
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:.75rem;color:var(--text-muted);">
                    <span>
                        <i class="bi bi-person"></i> {{ $tablero->creador?->name ?? 'Sistema' }}
                    </span>
                    @if($tablero->centroCosto)
                    <span>
                        <i class="bi bi-building"></i> {{ $tablero->centroCosto->nombre }}
                    </span>
                    @endif
                    <span>{{ $tablero->updated_at->diffForHumans() }}</span>
                </div>
            </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
