{{-- Activity row for the annual Gantt table --}}
@php
    $seg = $act->seguimiento_por_mes;
    $priColors = ['ALTA' => '#ef4444', 'MEDIA' => '#f59e0b', 'BAJA' => '#10b981'];
    $priLabels = ['ALTA' => 'Alta', 'MEDIA' => 'Media', 'BAJA' => 'Baja'];
    $estColors = ['PENDIENTE'=>'#94a3b8','EN_PROGRESO'=>'#f59e0b','COMPLETADA'=>'#10b981','CANCELADA'=>'#ef4444'];
    $estLabels = \App\Models\SstActividad::estadosMap();
@endphp
<tr class="sst-act-row" data-actividad-id="{{ $act->id }}"
    data-act="{{ htmlspecialchars(json_encode([
        'id' => $act->id,
        'nombre' => $act->nombre,
        'descripcion' => $act->descripcion,
        'responsable_id' => $act->responsable_id,
        'prioridad' => $act->prioridad,
        'estado' => $act->estado,
        'periodicidad' => $act->periodicidad,
        'fecha_inicio' => $act->fecha_inicio ? $act->fecha_inicio->format('Y-m-d') : '',
        'fecha_fin' => $act->fecha_fin ? $act->fecha_fin->format('Y-m-d') : '',
        'meses_prog' => collect($seg)->filter(fn($s) => $s['programado'])->keys()->values()->all(),
    ]), ENT_QUOTES) }}">
    {{-- Nombre con acciones --}}
    <td class="sst-th-sticky" style="font-weight:600;font-size:.82rem">
        <div style="display:flex;align-items:center;gap:.35rem">
            <button class="sst-icon-btn sst-icon-btn-xs" onclick="openDetail(this.closest('tr'))" title="Ver detalle">
                <i class="bi bi-eye"></i>
            </button>
            <span class="sst-act-name" style="flex:1;cursor:pointer" onclick="openDetail(this.closest('tr'))">{{ $act->nombre }}</span>
        </div>
    </td>
    {{-- Responsable --}}
    <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap">
        @if($act->responsableUser)
            <span title="{{ $act->responsableUser->email }}">{{ Str::limit($act->nombre_responsable, 18) }}</span>
        @else
            <span style="opacity:.4">—</span>
        @endif
    </td>
    {{-- Prioridad --}}
    <td style="text-align:center">
        <span style="display:inline-block;padding:.15rem .45rem;border-radius:6px;font-size:.7rem;font-weight:700;
                      background:{{ $priColors[$act->prioridad] ?? '#94a3b8' }}20;color:{{ $priColors[$act->prioridad] ?? '#94a3b8' }}">
            {{ $priLabels[$act->prioridad] ?? '—' }}
        </span>
    </td>
    {{-- Estado --}}
    <td style="text-align:center">
        <span style="display:inline-block;padding:.15rem .45rem;border-radius:6px;font-size:.68rem;font-weight:600;
                      background:{{ $estColors[$act->estado] ?? '#94a3b8' }}20;color:{{ $estColors[$act->estado] ?? '#94a3b8' }}">
            {{ Str::limit($estLabels[$act->estado] ?? '—', 10) }}
        </span>
    </td>
    {{-- 12 meses Gantt --}}
    @for($m = 1; $m <= 12; $m++)
    @php
        $s = $seg[$m] ?? null;
        $prog = $s ? $s['programado'] : false;
        $real = $s ? $s['realizado'] : false;
        $vencido = $prog && !$real && $m < $mesActual;
    @endphp
    <td class="sst-td-mes {{ $m === $mesActual ? 'sst-mes-actual' : '' }}">
        @if($prog)
        <button class="gantt-cell {{ $real ? 'gantt-done' : ($vencido ? 'gantt-overdue' : 'gantt-plan') }}"
                onclick="toggleSeguimiento({{ $act->id }}, {{ $m }}, this)"
                title="{{ $real ? 'Realizado' : ($vencido ? 'Vencido — clic para marcar' : 'Programado — clic para marcar') }}">
            {{ $real ? '✓' : ($vencido ? '!' : '○') }}
        </button>
        @endif
    </td>
    @endfor
    {{-- Acciones --}}
    <td style="text-align:right;white-space:nowrap">
        <div style="display:flex;gap:.2rem;justify-content:flex-end">
            <button class="sst-icon-btn sst-icon-btn-xs" onclick="openEditModal(this.closest('tr'))" title="Editar">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="sst-icon-btn sst-icon-btn-xs" onclick="togglePlanes({{ $act->id }})" title="Planes de acción">
                <i class="bi bi-clipboard-check"></i>
            </button>
            <form method="POST" action="{{ route('carta-gantt.actividades.destroy', $act) }}"
                  onsubmit="return confirm('¿Eliminar esta actividad?')" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="sst-icon-btn sst-icon-btn-xs sst-icon-btn-danger" title="Eliminar">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
        </div>
    </td>
</tr>

{{-- Fila expandible: Planes de Acción --}}
<tr id="planes-{{ $act->id }}" style="display:none" class="sst-planes-row">
    <td colspan="17" style="padding:.5rem 1rem;background:var(--surface-color)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <h4 style="margin:0;font-size:.82rem;font-weight:700;color:var(--text-main)"><i class="bi bi-clipboard-check"></i> Planes de Acción — {{ $act->nombre }}</h4>
        </div>
        @if($act->planesAccion->count())
        <table style="width:100%;font-size:.78rem;border-collapse:collapse;margin-bottom:.5rem">
            <thead><tr style="background:var(--bg-color)">
                <th style="padding:.3rem .5rem;text-align:left;font-weight:600">Acción</th>
                <th style="padding:.3rem .5rem;width:110px">Responsable</th>
                <th style="padding:.3rem .5rem;width:100px">Compromiso</th>
                <th style="padding:.3rem .5rem;width:80px">Estado</th>
                <th style="padding:.3rem .5rem;width:50px"></th>
            </tr></thead>
            <tbody>
            @foreach($act->planesAccion as $plan)
            <tr style="border-bottom:1px solid var(--surface-border)">
                <td style="padding:.3rem .5rem">{{ $plan->accion }}</td>
                <td style="padding:.3rem .5rem;color:var(--text-muted)">{{ $plan->responsable ?? '—' }}</td>
                <td style="padding:.3rem .5rem;color:var(--text-muted)">{{ $plan->fecha_compromiso?->format('d/m/Y') ?? '—' }}</td>
                <td style="padding:.3rem .5rem"><span class="badge {{ $plan->estado_badge }}">{{ $plan->estado_label }}</span></td>
                <td style="padding:.3rem .5rem;text-align:right">
                    <form method="POST" action="{{ route('carta-gantt.plan-accion.destroy', $plan) }}"
                          onsubmit="return confirm('¿Eliminar este plan?')" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="sst-icon-btn sst-icon-btn-xs sst-icon-btn-danger"><i class="bi bi-x-lg"></i></button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p style="color:var(--text-muted);font-size:.78rem;font-style:italic;margin:0 0 .5rem">Sin planes de acción.</p>
        @endif
        {{-- Agregar plan --}}
        <form method="POST" action="{{ route('carta-gantt.plan-accion.store', $act) }}"
              style="display:flex;gap:.4rem;align-items:flex-end;flex-wrap:wrap">
            @csrf
            <div style="flex:2;min-width:160px"><label class="sst-label">Acción *</label><input type="text" name="accion" required class="form-input" placeholder="¿Qué hacer?" style="font-size:.78rem"></div>
            <div style="flex:1;min-width:120px"><label class="sst-label">Responsable</label><input type="text" name="responsable" class="form-input" placeholder="Nombre" style="font-size:.78rem"></div>
            <div style="width:120px"><label class="sst-label">Fecha compromiso</label><input type="date" name="fecha_compromiso" class="form-input" style="font-size:.78rem"></div>
            <button type="submit" class="sst-btn sst-btn-sm sst-btn-primary" style="font-size:.75rem"><i class="bi bi-plus-lg"></i> Agregar</button>
        </form>
    </td>
</tr>
