@extends('layouts.app')
@section('title','Mis tareas Carta Gantt')
@section('content')
<style>
.gantt-task-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.8rem;margin-bottom:1rem}
.gantt-task-kpi{padding:.9rem 1rem;text-align:left}
.gantt-task-kpi span{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:800}
.gantt-task-kpi strong{display:block;font-size:1.6rem;line-height:1.1;margin-top:.25rem}
.gantt-task-filter{display:grid;grid-template-columns:minmax(220px,1.5fr) repeat(4,minmax(140px,1fr)) auto;gap:.65rem;align-items:end}
.gantt-task-state{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.25rem .55rem;font-size:.7rem;font-weight:800;white-space:nowrap}
.gantt-task-state.danger{background:rgba(239,68,68,.12);color:#b91c1c}
.gantt-task-state.warning{background:rgba(245,158,11,.12);color:#b45309}
.gantt-task-state.info{background:rgba(59,130,246,.12);color:#1d4ed8}
.gantt-task-state.success{background:rgba(16,185,129,.12);color:#047857}
.gantt-task-name{display:flex;flex-direction:column;gap:.2rem;min-width:220px}
.gantt-task-name strong{font-size:.84rem;color:var(--text-main)}
.gantt-task-name small{font-size:.7rem;color:var(--text-muted);line-height:1.35}
.gantt-task-muted{font-size:.72rem;color:var(--text-muted)}
.gantt-task-actions{display:flex;gap:.35rem;justify-content:flex-end;flex-wrap:wrap}
.gantt-task-action-btn{display:inline-flex;align-items:center;gap:.3rem;border:1px solid var(--surface-border);background:var(--surface-color);color:var(--text-main);border-radius:8px;padding:.42rem .55rem;font-size:.72rem;font-weight:800;text-decoration:none;white-space:nowrap}
.gantt-task-action-btn:hover{border-color:rgba(99,102,241,.45);color:var(--primary-color)}
.gantt-task-action-btn.is-primary{background:var(--primary-color);border-color:var(--primary-color);color:#fff}
.gantt-task-action-note{font-size:.68rem;color:var(--text-muted);white-space:nowrap}
.gantt-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;z-index:9999;padding:1rem}
.gantt-modal-card{background:var(--card-bg,#fff);border:1px solid var(--surface-border);border-radius:12px;width:min(520px,100%);box-shadow:0 18px 60px rgba(15,23,42,.28);overflow:hidden}
.gantt-modal-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1rem 1.1rem;border-bottom:1px solid var(--surface-border)}
.gantt-modal-head strong{font-size:.95rem}
.gantt-modal-body{padding:1rem 1.1rem}
.gantt-modal-foot{display:flex;justify-content:flex-end;gap:.5rem;padding:0 1.1rem 1.1rem}
@media(max-width:1100px){.gantt-task-filter{grid-template-columns:1fr 1fr}.gantt-task-filter .btn-premium,.gantt-task-filter .btn-ghost{width:100%;justify-content:center}}
@media(max-width:720px){.gantt-task-filter{grid-template-columns:1fr}.gantt-task-actions{justify-content:flex-start}.glass-table-container{overflow:auto}}
</style>

<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-list-task" style="color:var(--primary-color)"></i> Mis tareas Carta Gantt</h2>
            <p class="page-subheading">Actividades pendientes, vencidas y próximas a vencer según tu alcance operativo.</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('carta-gantt.notificaciones') }}" class="btn-ghost"><i class="bi bi-envelope-check"></i> Notificaciones</a>
            <a href="{{ route('carta-gantt.index') }}" class="btn-ghost"><i class="bi bi-arrow-left"></i> Programas</a>
        </div>
    </div>

    @include('partials._alerts')

    <div class="gantt-task-kpis">
        <div class="glass-card gantt-task-kpi">
            <span>Total visible</span>
            <strong style="color:var(--primary-color)">{{ $stats['total'] }}</strong>
        </div>
        <div class="glass-card gantt-task-kpi">
            <span>Vencidas</span>
            <strong style="color:#dc2626">{{ $stats['vencidas'] }}</strong>
        </div>
        <div class="glass-card gantt-task-kpi">
            <span>Por vencer</span>
            <strong style="color:#d97706">{{ $stats['proximas'] }}</strong>
        </div>
        <div class="glass-card gantt-task-kpi">
            <span>Mes actual</span>
            <strong style="color:#2563eb">{{ $stats['pendientes_mes'] + $stats['parciales_mes'] }}</strong>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('carta-gantt.mis-tareas') }}" class="gantt-task-filter">
            <div class="filter-group">
                <label>Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-input" placeholder="Actividad, programa, responsable...">
            </div>
            <div class="filter-group">
                <label>Estado operativo</label>
                <select name="estado_operativo" class="form-input">
                    <option value="">Todos</option>
                    <option value="vencida" {{ request('estado_operativo') === 'vencida' ? 'selected' : '' }}>Vencidas</option>
                    <option value="proxima" {{ request('estado_operativo') === 'proxima' ? 'selected' : '' }}>Por vencer</option>
                    <option value="pendiente_mes" {{ request('estado_operativo') === 'pendiente_mes' ? 'selected' : '' }}>Pendientes mes</option>
                    <option value="parcial_mes" {{ request('estado_operativo') === 'parcial_mes' ? 'selected' : '' }}>Parciales mes</option>
                    <option value="al_dia" {{ request('estado_operativo') === 'al_dia' ? 'selected' : '' }}>Sin críticos</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Programa</label>
                <select name="programa_id" class="form-input">
                    <option value="">Todos</option>
                    @foreach($programas as $programa)
                        <option value="{{ $programa->id }}" {{ request('programa_id') == $programa->id ? 'selected' : '' }}>
                            {{ $programa->codigo ?? 'SST' }} · {{ \Illuminate\Support\Str::limit($programa->nombre, 34) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if($puedeAccesoGlobal)
            <div class="filter-group">
                <label>Responsable</label>
                <select name="responsable_id" class="form-input">
                    <option value="">Todos</option>
                    @foreach($responsables as $responsable)
                        <option value="{{ $responsable->id }}" {{ request('responsable_id') == $responsable->id ? 'selected' : '' }}>{{ $responsable->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="filter-group">
                <label>Alcance</label>
                <select name="alcance" class="form-input">
                    <option value="">Todo mi alcance</option>
                    <option value="responsable" {{ request('alcance') === 'responsable' ? 'selected' : '' }}>Solo responsable directo</option>
                </select>
            </div>
            @endif
            <div class="filter-group">
                <label>Prioridad</label>
                <select name="prioridad" class="form-input">
                    <option value="">Todas</option>
                    <option value="ALTA" {{ request('prioridad') === 'ALTA' ? 'selected' : '' }}>Alta</option>
                    <option value="MEDIA" {{ request('prioridad') === 'MEDIA' ? 'selected' : '' }}>Media</option>
                    <option value="BAJA" {{ request('prioridad') === 'BAJA' ? 'selected' : '' }}>Baja</option>
                </select>
            </div>
            <button class="btn-premium" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
            @if(request()->query())
                <a href="{{ route('carta-gantt.mis-tareas') }}" class="btn-ghost"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Actividad</th>
                        <th>Programa</th>
                        <th>Responsable</th>
                        <th>Prioridad</th>
                        <th>Fecha límite</th>
                        <th>Avance mes</th>
                        <th style="width:260px;text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($actividades as $actividad)
                    @php
                        $estado = $actividad->estado_operativo;
                        $programa = $actividad->categoria?->programa;
                        $showUrl = $programa ? route('carta-gantt.show', $programa) . '#actividad-' . $actividad->id : route('carta-gantt.index');
                        $prioridadColor = ['ALTA' => '#dc2626', 'MEDIA' => '#d97706', 'BAJA' => '#059669'][$actividad->prioridad] ?? '#64748b';
                        $puedeGestionar = (bool) $actividad->puede_gestionar;
                        $mesActual = (int) now()->format('n');
                        $seguimientoMesActual = $actividad->seguimiento->firstWhere('mes', $mesActual);
                        $puedeAvanzarMes = $puedeGestionar && $seguimientoMesActual && $seguimientoMesActual->programado && !$seguimientoMesActual->realizado;
                        $mesesVencidos = collect($estado['meses_vencidos'] ?? [])->map(fn($mes) => (int) $mes)->values();
                        $ultimoLog = $actividad->logs->first();
                    @endphp
                    <tr>
                        <td>
                            <span class="gantt-task-state {{ $estado['badge'] }}">
                                <i class="bi {{ $estado['icono'] }}"></i> {{ $estado['label'] }}
                            </span>
                            <div class="gantt-task-muted" style="margin-top:.25rem">{{ $estado['detalle'] }}</div>
                        </td>
                        <td>
                            <div class="gantt-task-name">
                                <strong>{{ $actividad->nombre }}</strong>
                                <small>{{ $actividad->categoria?->nombre ?? 'Sin categoría' }}</small>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:.8rem;font-weight:700">{{ $programa?->nombre ?? '—' }}</div>
                            <div class="gantt-task-muted">{{ $programa?->codigo ?? '—' }} · {{ $programa?->anio ?? '—' }}</div>
                        </td>
                        <td>
                            <div style="font-size:.78rem;font-weight:700">{{ $actividad->responsableUser?->nombre_completo ?? $actividad->responsable ?? '—' }}</div>
                            @if($actividad->responsableUser?->email)
                                <div class="gantt-task-muted">{{ $actividad->responsableUser->email }}</div>
                            @endif
                        </td>
                        <td>
                            <span style="display:inline-flex;background:{{ $prioridadColor }}20;color:{{ $prioridadColor }};border-radius:999px;padding:.2rem .55rem;font-size:.7rem;font-weight:800">
                                {{ ucfirst(strtolower($actividad->prioridad ?? 'media')) }}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:.8rem;font-weight:700">{{ $actividad->fecha_fin?->format('d/m/Y') ?? 'Sin fecha' }}</div>
                            @if($estado['dias'] !== null)
                                <div class="gantt-task-muted">{{ $estado['dias'] < 0 ? abs($estado['dias']) . ' día(s) vencida' : $estado['dias'] . ' día(s)' }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="gantt-task-muted">{{ $estado['avance_mes'] ?? 'Sin pendiente mensual' }}</span>
                            @if($ultimoLog)
                                <div class="gantt-task-muted" title="{{ $ultimoLog->resumen }}">
                                    Último: {{ str_replace('_', ' ', $ultimoLog->accion) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="gantt-task-actions">
                                @if($puedeAvanzarMes)
                                    <button type="button" class="gantt-task-action-btn is-primary" onclick="quickAdvance({{ $actividad->id }}, {{ $mesActual }}, this)">
                                        <i class="bi bi-check2-circle"></i> Avanzar
                                    </button>
                                @endif
                                @if($puedeGestionar)
                                    <button type="button" class="gantt-task-action-btn" title="Comentar actividad" onclick="openCommentModal({{ $actividad->id }}, @js($actividad->nombre))">
                                        <i class="bi bi-chat-left-text"></i> Comentar
                                    </button>
                                @endif
                                @if($puedeGestionar && $mesesVencidos->isNotEmpty())
                                    <button type="button" class="gantt-task-action-btn" title="Reprogramar meses vencidos" onclick="openReprogramModal({{ $actividad->id }}, @js($actividad->nombre), @js($mesesVencidos->all()))">
                                        <i class="bi bi-calendar2-range"></i> Reprogramar
                                    </button>
                                @endif
                                <a href="{{ $showUrl }}" class="gantt-task-action-btn" title="Abrir actividad"><i class="bi bi-eye"></i> Abrir</a>
                                @unless($puedeGestionar)
                                    <span class="gantt-task-action-note">Solo lectura</span>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-muted)">
                            No hay tareas con estos filtros.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="gantt-modal" id="commentModal" role="dialog" aria-modal="true" aria-labelledby="commentModalTitle">
    <div class="gantt-modal-card">
        <form method="POST" id="commentForm">
            @csrf
            <div class="gantt-modal-head">
                <div>
                    <strong id="commentModalTitle">Comentar actividad</strong>
                    <div class="gantt-task-muted" id="commentActivityName"></div>
                </div>
                <button type="button" class="icon-btn" onclick="closeCommentModal()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="gantt-modal-body">
                <label class="filter-group" style="display:block">
                    <span style="font-size:.72rem;color:var(--text-muted);font-weight:800;text-transform:uppercase">Comentario</span>
                    <textarea name="comentario" required maxlength="1000" class="form-input" rows="4" placeholder="Novedad, bloqueo, acuerdo o evidencia pendiente..." style="margin-top:.35rem;resize:vertical"></textarea>
                </label>
            </div>
            <div class="gantt-modal-foot">
                <button type="button" class="btn-ghost" onclick="closeCommentModal()">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-send"></i> Comentar</button>
            </div>
        </form>
    </div>
</div>

<div class="gantt-modal" id="reprogramModal" role="dialog" aria-modal="true" aria-labelledby="reprogramModalTitle">
    <div class="gantt-modal-card">
        <form method="POST" id="reprogramForm">
            @csrf
            <div class="gantt-modal-head">
                <div>
                    <strong id="reprogramModalTitle">Reprogramar actividad</strong>
                    <div class="gantt-task-muted" id="reprogramActivityName"></div>
                </div>
                <button type="button" class="icon-btn" onclick="closeReprogramModal()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="gantt-modal-body" style="display:grid;gap:.75rem">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
                    <div class="filter-group">
                        <label>Mes original</label>
                        <select name="mes_original" id="reprogramMesOriginal" required class="form-input"></select>
                    </div>
                    <div class="filter-group">
                        <label>Mes nuevo</label>
                        <select name="mes_nuevo" id="reprogramMesNuevo" required class="form-input"></select>
                    </div>
                </div>
                <div class="filter-group">
                    <label>Motivo</label>
                    <textarea name="motivo" id="reprogramMotivo" required maxlength="1000" class="form-input" rows="3" placeholder="Motivo de reprogramación..." style="resize:vertical"></textarea>
                </div>
            </div>
            <div class="gantt-modal-foot">
                <button type="button" class="btn-ghost" onclick="closeReprogramModal()">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-calendar2-range"></i> Reprogramar</button>
            </div>
        </form>
    </div>
</div>

<script>
const GANTT_CSRF = @js(csrf_token());
const GANTT_MONTHS = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const GANTT_CURRENT_MONTH = {{ (int) now()->format('n') }};

function quickAdvance(activityId, month, button) {
    if (!button) return;
    button.disabled = true;
    button.style.opacity = '.65';

    fetch("{{ url('carta-gantt/actividades') }}/" + activityId + "/seguimiento", {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': GANTT_CSRF,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ mes: month })
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    })
    .then(() => window.location.reload())
    .catch(() => {
        button.disabled = false;
        button.style.opacity = '1';
        alert('No se pudo actualizar el avance. Revisa permisos o intenta desde el detalle de la Carta Gantt.');
    });
}

function openCommentModal(activityId, activityName) {
    const modal = document.getElementById('commentModal');
    const form = document.getElementById('commentForm');
    form.action = "{{ url('carta-gantt/actividades') }}/" + activityId + "/comentarios";
    form.reset();
    document.getElementById('commentActivityName').textContent = activityName || '';
    modal.style.display = 'flex';
    setTimeout(() => form.querySelector('textarea')?.focus(), 80);
}

function closeCommentModal() {
    document.getElementById('commentModal').style.display = 'none';
}

function openReprogramModal(activityId, activityName, overdueMonths) {
    const modal = document.getElementById('reprogramModal');
    const form = document.getElementById('reprogramForm');
    const original = document.getElementById('reprogramMesOriginal');
    const next = document.getElementById('reprogramMesNuevo');

    form.action = "{{ url('carta-gantt/actividades') }}/" + activityId + "/reprogramar";
    form.reset();
    document.getElementById('reprogramActivityName').textContent = activityName || '';

    original.innerHTML = '<option value="">Seleccione...</option>';
    (overdueMonths || []).forEach(month => {
        original.insertAdjacentHTML('beforeend', `<option value="${month}">${GANTT_MONTHS[month] || ('Mes ' + month)}</option>`);
    });

    next.innerHTML = '<option value="">Seleccione...</option>';
    for (let month = GANTT_CURRENT_MONTH; month <= 12; month++) {
        next.insertAdjacentHTML('beforeend', `<option value="${month}">${GANTT_MONTHS[month]}</option>`);
    }

    modal.style.display = 'flex';
    setTimeout(() => original.focus(), 80);
}

function closeReprogramModal() {
    document.getElementById('reprogramModal').style.display = 'none';
}

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    closeCommentModal();
    closeReprogramModal();
});

document.querySelectorAll('.gantt-modal').forEach(modal => {
    modal.addEventListener('click', event => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>
@endsection
