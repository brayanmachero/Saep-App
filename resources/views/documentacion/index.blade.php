@extends('layouts.app')

@section('title', 'Documentación')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">Documentación de la Plataforma</h2>
            <p class="page-subheading">Guías de uso de cada módulo del sistema SAEP</p>
        </div>
    </div>

    {{-- Estadísticas --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
        @php
            $completos = collect($modulos)->where('estado', 'completo')->count();
            $pendientes = collect($modulos)->where('estado', 'pendiente')->count();
            $total = count($modulos);
        @endphp
        <div class="glass-card" style="text-align:center;padding:1.25rem;">
            <span style="font-size:2rem;font-weight:700;color:var(--primary-color);">{{ $total }}</span>
            <span style="display:block;font-size:.85rem;color:var(--text-muted);">Módulos totales</span>
        </div>
        <div class="glass-card" style="text-align:center;padding:1.25rem;">
            <span style="font-size:2rem;font-weight:700;color:#10b981;">{{ $completos }}</span>
            <span style="display:block;font-size:.85rem;color:var(--text-muted);">Documentados</span>
        </div>
        <div class="glass-card" style="text-align:center;padding:1.25rem;">
            <span style="font-size:2rem;font-weight:700;color:#f59e0b;">{{ $pendientes }}</span>
            <span style="display:block;font-size:.85rem;color:var(--text-muted);">Pendientes</span>
        </div>
    </div>

    {{-- Grid de módulos --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;">
        @foreach($modulos as $slug => $mod)
        <a href="{{ route('documentacion.show', $slug) }}"
           class="glass-card" 
           style="text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s;display:flex;flex-direction:column;gap:.75rem;"
           onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 25px rgba(0,0,0,0.12)'"
           onmouseleave="this.style.transform='none';this.style.boxShadow='none'">
            
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:44px;height:44px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;
                    {{ $mod['estado'] === 'completo' 
                        ? 'background:rgba(16,185,129,0.1);color:#10b981;' 
                        : 'background:var(--surface-bg);color:var(--text-muted);' }}">
                    <i class="bi {{ $mod['icono'] }}"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <h3 style="margin:0;font-size:1rem;font-weight:600;">{{ $mod['titulo'] }}</h3>
                    @if($mod['version'])
                        <span style="font-size:.75rem;color:var(--text-muted);">v{{ $mod['version'] }}</span>
                    @endif
                </div>
                <span class="badge {{ $mod['estado'] === 'completo' ? 'success' : 'warning' }}" style="font-size:.75rem;flex-shrink:0;">
                    {{ $mod['estado'] === 'completo' ? 'Documentado' : 'Pendiente' }}
                </span>
            </div>

            <p style="margin:0;font-size:.875rem;color:var(--text-muted);line-height:1.4;">
                {{ $mod['descripcion'] }}
            </p>

            <div style="margin-top:auto;display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--primary-color);font-weight:500;">
                @if($mod['estado'] === 'completo')
                    <i class="bi bi-book"></i> Ver documentación
                @else
                    <i class="bi bi-clock"></i> Próximamente
                @endif
            </div>
        </a>
        @endforeach
    </div>
</div>
@endsection
