{{-- Activity row for the annual Gantt table --}}
@php
    $seg = $act->seguimiento_por_mes;
    $priColors = ['ALTA' => '#ef4444', 'MEDIA' => '#f59e0b', 'BAJA' => '#10b981'];
    $priLabels = ['ALTA' => 'Alta', 'MEDIA' => 'Media', 'BAJA' => 'Baja'];
    $estColors = ['PENDIENTE'=>'#94a3b8','EN_PROGRESO'=>'#f59e0b','COMPLETADA'=>'#10b981','CANCELADA'=>'#ef4444'];
    $estLabels = \App\Models\SstActividad::estadosMap();
@endphp
<tr class="sst-act-row" data-actividad-id="{{ $act->id }}"
    data-act="{{ json_encode([
        'id' => $act->id,
        'nombre' => $act->nombre,
        'descripcion' => $act->descripcion,
        'responsable_id' => $act->responsable_id,
        'prioridad' => $act->prioridad,
        'estado' => $act->estado,
        'periodicidad' => $act->periodicidad,
        'cantidad_programada' => (int) ($act->cantidad_programada ?? 1),
        'fecha_inicio' => $act->fecha_inicio ? $act->fecha_inicio->format('Y-m-d') : '',
        'fecha_fin' => $act->fecha_fin ? $act->fecha_fin->format('Y-m-d') : '',
        'meses_prog' => collect($seg)->filter(fn($s) => $s['programado'])->keys()->values()->all(),
    ]) }}">
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
    @php $cantProg = max(1, (int) ($act->cantidad_programada ?? 1)); @endphp
    @for($m = 1; $m <= 12; $m++)
    @php
        $s = $seg[$m] ?? null;
        $prog = $s ? $s['programado'] : false;
        $real = $s ? $s['realizado'] : false;
        $cantReal = $s ? (int) ($s['cantidad_realizada'] ?? 0) : 0;
        $vencido = $prog && !$real && $m < $mesActual;
        $parcial = $prog && !$real && $cantReal > 0;
    @endphp
    <td class="sst-td-mes {{ $m === $mesActual ? 'sst-mes-actual' : '' }}">
        @if($prog)
        @if($cantProg > 1)
        <button class="gantt-cell {{ $real ? 'gantt-done' : ($vencido ? 'gantt-overdue' : ($parcial ? 'gantt-partial' : 'gantt-plan')) }}"
                onclick="toggleSeguimiento({{ $act->id }}, {{ $m }}, this)"
                title="{{ $cantReal }}/{{ $cantProg }} — clic para {{ $real ? 'resetear' : 'avanzar' }}">
            {{ $real ? '✓' : ($cantReal > 0 ? $cantReal.'/'.$cantProg : '0/'.$cantProg) }}
        </button>
        @else
        <button class="gantt-cell {{ $real ? 'gantt-done' : ($vencido ? 'gantt-overdue' : 'gantt-plan') }}"
                onclick="toggleSeguimiento({{ $act->id }}, {{ $m }}, this)"
                title="{{ $real ? 'Realizado' : ($vencido ? 'Vencido — clic para marcar' : 'Programado — clic para marcar') }}">
            {{ $real ? '✓' : ($vencido ? '!' : '○') }}
        </button>
        @endif
        @endif
    </td>
    @endfor
    {{-- Acciones --}}
    @php
        $user = auth()->user();
        $esSuperAdmin = $user->rol && $user->rol->codigo === 'SUPER_ADMIN';
        $esCreador = $user->id === $cartaGantt->creado_por;
        $esResponsable = $user->id === $act->responsable_id;
        $puedeEditar = $esSuperAdmin || $esCreador;
        // Meses vencidos (programado, no realizado, mes pasado)
        $mesesVencidos = collect($seg)->filter(fn($s, $m) => $s['programado'] && !$s['realizado'] && $m < $mesActual)->keys()->all();
    @endphp
    <td style="text-align:right;white-space:nowrap">
        <div style="display:flex;gap:.2rem;justify-content:flex-end">
            @if($puedeEditar)
            <button class="sst-icon-btn sst-icon-btn-xs" onclick="openEditModal(this.closest('tr'))" title="Editar">
                <i class="bi bi-pencil"></i>
            </button>
            @endif
            <button class="sst-icon-btn sst-icon-btn-xs" onclick="togglePlanes({{ $act->id }})" title="Planes de acción">
                <i class="bi bi-clipboard-check"></i>
            </button>
            @if(count($mesesVencidos) > 0 && ($esResponsable || $puedeEditar))
            <button class="sst-icon-btn sst-icon-btn-xs" style="color:#6366f1" onclick="openReprogramar({{ $act->id }}, {{ json_encode($mesesVencidos) }})" title="Reprogramar">
                <i class="bi bi-calendar2-range"></i>
            </button>
            @endif
            @if($act->reprogramaciones->count() > 0)
            <button class="sst-icon-btn sst-icon-btn-xs" style="color:#8b5cf6" onclick="toggleReprogramaciones({{ $act->id }})" title="Historial reprogramaciones ({{ $act->reprogramaciones->count() }})">
                <i class="bi bi-clock-history"></i>
                <span style="font-size:.6rem;position:absolute;top:-2px;right:-4px;background:#8b5cf6;color:#fff;border-radius:50%;width:14px;height:14px;display:flex;align-items:center;justify-content:center;font-weight:700;">{{ $act->reprogramaciones->count() }}</span>
            </button>
            @endif
            @if($puedeEditar)
            <form method="POST" action="{{ route('carta-gantt.actividades.destroy', $act) }}"
                  onsubmit="return confirm('¿Eliminar esta actividad?')" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="sst-icon-btn sst-icon-btn-xs sst-icon-btn-danger" title="Eliminar">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
            @endif
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

{{-- Fila expandible: Historial de Reprogramaciones --}}
@if($act->reprogramaciones->count() > 0)
<tr id="reprog-{{ $act->id }}" style="display:none" class="sst-planes-row">
    <td colspan="17" style="padding:.5rem 1rem;background:var(--surface-color)">
        <h4 style="margin:0 0 .5rem;font-size:.82rem;font-weight:700;color:#8b5cf6">
            <i class="bi bi-clock-history"></i> Historial de Reprogramaciones — {{ $act->nombre }}
        </h4>
        @php $mesesNom = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']; @endphp
        <table style="width:100%;font-size:.78rem;border-collapse:collapse;">
            <thead><tr style="background:var(--bg-color)">
                <th style="padding:.3rem .5rem;text-align:left;font-weight:600">Fecha</th>
                <th style="padding:.3rem .5rem;width:100px">Mes Original</th>
                <th style="padding:.3rem .5rem;width:30px;text-align:center;">→</th>
                <th style="padding:.3rem .5rem;width:100px">Mes Nuevo</th>
                <th style="padding:.3rem .5rem;text-align:left;font-weight:600">Motivo</th>
                <th style="padding:.3rem .5rem;width:130px">Reprogramado por</th>
            </tr></thead>
            <tbody>
            @foreach($act->reprogramaciones->sortByDesc('created_at') as $reprog)
            <tr style="border-bottom:1px solid var(--surface-border)">
                <td style="padding:.3rem .5rem;color:var(--text-muted)">{{ $reprog->created_at->format('d/m/Y H:i') }}</td>
                <td style="padding:.3rem .5rem">
                    <span style="background:#ef444420;color:#ef4444;padding:.1rem .4rem;border-radius:4px;font-weight:600;font-size:.72rem">
                        {{ $mesesNom[$reprog->mes_original] ?? '?' }}
                    </span>
                </td>
                <td style="text-align:center;color:var(--text-muted);font-weight:700;">→</td>
                <td style="padding:.3rem .5rem">
                    <span style="background:#22c55e20;color:#22c55e;padding:.1rem .4rem;border-radius:4px;font-weight:600;font-size:.72rem">
                        {{ $mesesNom[$reprog->mes_nuevo] ?? '?' }}
                    </span>
                </td>
                <td style="padding:.3rem .5rem">{{ $reprog->motivo }}</td>
                <td style="padding:.3rem .5rem;color:var(--text-muted)">{{ $reprog->usuario?->nombre_completo ?? '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </td>
</tr>
@endif
