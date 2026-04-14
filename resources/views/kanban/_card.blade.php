{{-- Kanban Task Card --}}
@php
    $pColors = ['ALTA' => '#dc2626', 'MEDIA' => '#f59e0b', 'BAJA' => '#16a34a'];
    $pColor = $pColors[$tarea->prioridad] ?? '#6b7280';
    $clProgreso = $tarea->checklistProgreso;
@endphp
<div class="kanban-card" data-tarea-id="{{ $tarea->id }}" onclick="abrirDetalle({{ $tarea->id }})" style="background:var(--card-bg);border:1px solid var(--border-color);border-left:3px solid {{ $pColor }};border-radius:8px;padding:.6rem .75rem;margin-bottom:.5rem;cursor:grab;transition:box-shadow .15s;">

    {{-- Etiquetas --}}
    @if($tarea->etiquetas->isNotEmpty())
    <div style="display:flex;flex-wrap:wrap;gap:.25rem;margin-bottom:.35rem;">
        @foreach($tarea->etiquetas as $et)
        <span style="font-size:.6rem;padding:.1rem .3rem;border-radius:3px;background:{{ $et->color }}20;color:{{ $et->color }};font-weight:700;letter-spacing:.02em;">{{ $et->nombre }}</span>
        @endforeach
    </div>
    @endif

    {{-- Título --}}
    <div style="font-size:.82rem;font-weight:600;color:var(--text-primary);line-height:1.3;margin-bottom:.35rem;">{{ $tarea->titulo }}</div>

    {{-- Descripción (preview) --}}
    @if($tarea->descripcion)
    <div style="font-size:.72rem;color:var(--text-muted);line-height:1.3;margin-bottom:.35rem;">{{ Str::limit($tarea->descripcion, 80) }}</div>
    @endif

    {{-- Checklist progress bar --}}
    @if($clProgreso['total'] > 0)
    <div style="margin-bottom:.35rem;">
        <div style="display:flex;align-items:center;gap:.35rem;font-size:.65rem;color:var(--text-muted);margin-bottom:.15rem;">
            <i class="bi bi-check2-square"></i>
            <span>{{ $clProgreso['completados'] }}/{{ $clProgreso['total'] }}</span>
        </div>
        <div style="height:3px;background:var(--border-color);border-radius:2px;overflow:hidden;">
            <div style="height:100%;background:#10b981;width:{{ $clProgreso['total'] > 0 ? round($clProgreso['completados'] / $clProgreso['total'] * 100) : 0 }}%;border-radius:2px;transition:width .3s;"></div>
        </div>
    </div>
    @endif

    {{-- Footer: meta info --}}
    <div style="display:flex;align-items:center;justify-content:space-between;font-size:.68rem;color:var(--text-muted);margin-top:.25rem;">
        <div style="display:flex;align-items:center;gap:.5rem;">
            {{-- Prioridad --}}
            <span style="color:{{ $pColor }};font-weight:700;">{{ $tarea->prioridad }}</span>

            {{-- Fecha --}}
            @if($tarea->fecha_vencimiento)
            <span style="{{ $tarea->estaVencida ? 'color:#dc2626;font-weight:600;' : '' }}">
                <i class="bi bi-clock"></i> {{ $tarea->fecha_vencimiento->format('d/m') }}
            </span>
            @endif

            {{-- Indicadores --}}
            @if($tarea->comentarios_count ?? $tarea->comentarios->count() ?? 0)
            <span><i class="bi bi-chat-dots"></i> {{ $tarea->comentarios->count() }}</span>
            @endif
            @if($tarea->adjuntos_count ?? $tarea->adjuntos->count() ?? 0)
            <span><i class="bi bi-paperclip"></i> {{ $tarea->adjuntos->count() }}</span>
            @endif
        </div>

        {{-- Avatar asignado --}}
        @if($tarea->asignado)
        <span title="{{ $tarea->asignado->name }}" style="background:var(--primary-color);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;">
            {{ strtoupper(substr($tarea->asignado->name, 0, 2)) }}
        </span>
        @endif
    </div>
</div>
