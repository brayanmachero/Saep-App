@extends('layouts.app')
@section('title','Tareas Archivadas')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-archive" style="color:var(--primary-color)"></i> Tareas Archivadas</h2>
            <p class="page-subheading">{{ $kanban->nombre }} — {{ $tareas->count() }} tarea{{ $tareas->count() !== 1 ? 's' : '' }} archivada{{ $tareas->count() !== 1 ? 's' : '' }}</p>
        </div>
        <a href="{{ route('kanban.show', $kanban) }}" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
            <i class="bi bi-arrow-left"></i> Volver al Tablero
        </a>
    </div>

    @include('partials._alerts')

    @if($tareas->isEmpty())
        <div class="glass-card" style="padding:3rem;text-align:center;">
            <i class="bi bi-archive" style="font-size:2.5rem;color:var(--text-muted);opacity:.4;"></i>
            <p style="margin-top:.75rem;color:var(--text-muted);">No hay tareas archivadas en este tablero.</p>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            @foreach($tareas as $tarea)
            @php
                $pColors = ['ALTA' => '#dc2626', 'MEDIA' => '#f59e0b', 'BAJA' => '#16a34a'];
                $pColor = $pColors[$tarea->prioridad] ?? '#6b7280';
            @endphp
            <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid {{ $pColor }};opacity:.85;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.35rem;">
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <span style="font-size:.88rem;font-weight:700;color:var(--text-primary);">{{ $tarea->titulo }}</span>
                        <span style="font-size:.68rem;font-weight:700;color:{{ $pColor }};padding:.1rem .35rem;border-radius:4px;background:{{ $pColor }}15;">{{ $tarea->prioridad }}</span>
                    </div>
                    <button onclick="desarchivar({{ $tarea->id }}, this)" class="btn-premium" style="padding:.3rem .65rem;font-size:.75rem;">
                        <i class="bi bi-arrow-counterclockwise"></i> Desarchivar
                    </button>
                </div>
                @if($tarea->descripcion)
                <p style="font-size:.78rem;color:var(--text-muted);margin:0 0 .5rem;line-height:1.4;">{{ Str::limit($tarea->descripcion, 200) }}</p>
                @endif
                <div style="display:flex;align-items:center;gap:.75rem;font-size:.72rem;color:var(--text-muted);">
                    <span><i class="bi bi-columns-gap"></i> {{ $tarea->columna?->nombre }}</span>
                    @if($tarea->asignados->isNotEmpty())
                    <span><i class="bi bi-people"></i> {{ $tarea->asignados->pluck('name')->join(', ') }}</span>
                    @endif
                    @if($tarea->fecha_vencimiento)
                    <span><i class="bi bi-clock"></i> {{ $tarea->fecha_vencimiento->format('d/m/Y') }}</span>
                    @endif
                    <span><i class="bi bi-calendar3"></i> Archivada {{ $tarea->updated_at->diffForHumans() }}</span>
                    @if($tarea->etiquetas->isNotEmpty())
                    <div style="display:flex;gap:.2rem;">
                        @foreach($tarea->etiquetas as $et)
                        <span style="font-size:.6rem;padding:.1rem .3rem;border-radius:3px;background:{{ $et->color }}20;color:{{ $et->color }};font-weight:700;">{{ $et->nombre }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function desarchivar(tareaId, btn) {
    if (!confirm('¿Desarchivar esta tarea?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> ...';
    fetch(`/kanban/tareas/${tareaId}/desarchivar`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.closest('.glass-card').style.transition = 'opacity .3s, transform .3s';
            btn.closest('.glass-card').style.opacity = '0';
            btn.closest('.glass-card').style.transform = 'translateX(20px)';
            setTimeout(() => btn.closest('.glass-card').remove(), 300);
        } else {
            alert('Error al desarchivar');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Desarchivar';
        }
    })
    .catch(() => {
        alert('Error de conexión');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Desarchivar';
    });
}
</script>
@endsection
