{{-- Kanban Task Card --}}
@php
    $pColors = ['ALTA' => '#dc2626', 'MEDIA' => '#f59e0b', 'BAJA' => '#16a34a'];
    $pColor = $pColors[$tarea->prioridad] ?? '#6b7280';
    $clProgreso = $tarea->checklistProgreso;
    $coverImage = $tarea->adjuntos->first(fn ($a) => $a->esImagen());
@endphp
<div class="kanban-card" data-tarea-id="{{ $tarea->id }}" onclick="abrirDetalle({{ $tarea->id }})" style="background:var(--card-bg);border:1px solid var(--border-color);border-left:3px solid {{ $pColor }};border-radius:10px;padding:0;margin-bottom:.6rem;cursor:grab;transition:box-shadow .2s,transform .15s;box-shadow:0 1px 3px rgba(0,0,0,.08);" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,.12)';this.style.transform='translateY(-1px)';" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,.08)';this.style.transform='';">

    {{-- Cover Image --}}
    @if($coverImage)
    <div style="width:100%;height:120px;overflow:hidden;border-radius:10px 10px 0 0;border-bottom:1px solid var(--border-color);">
        <img src="{{ Storage::url($coverImage->ruta) }}" alt="" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
    </div>
    @endif

    <div style="padding:.6rem .75rem;">
        {{-- Etiquetas --}}
        @if($tarea->etiquetas->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:.25rem;margin-bottom:.4rem;">
            @foreach($tarea->etiquetas as $et)
            <span style="font-size:.6rem;padding:.15rem .4rem;border-radius:4px;background:{{ $et->color }}20;color:{{ $et->color }};font-weight:700;letter-spacing:.02em;">{{ $et->nombre }}</span>
            @endforeach
        </div>
        @endif

        {{-- Título --}}
        <div style="font-size:.84rem;font-weight:600;color:var(--text-primary);line-height:1.35;margin-bottom:.3rem;">{{ $tarea->titulo }}</div>

        {{-- Descripción (preview) --}}
        @if($tarea->descripcion)
        <div style="font-size:.72rem;color:var(--text-muted);line-height:1.4;margin-bottom:.35rem;">{{ Str::limit($tarea->descripcion, 80) }}</div>
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

        {{-- Footer: meta badges --}}
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:.68rem;color:var(--text-muted);margin-top:.3rem;padding-top:.3rem;border-top:1px solid var(--border-color);">
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
</div>
