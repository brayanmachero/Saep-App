@php
    $puedeGestionarCostos = $puedeGestionarCostos ?? auth()->user()->puedeGestionarCostosDescargaContenedores();
    $blockers = isset($blockers) ? collect($blockers) : $descarga->validationBlockers();
    $checklist = collect($descarga->validationChecklist())
        ->filter(fn ($item) => $puedeGestionarCostos || (($item['restricted'] ?? null) !== 'costs'))
        ->values();
    $doneCount = $checklist->where('done', true)->count();
    $totalCount = max($checklist->count(), 1);
    $progress = $descarga->estado === 'liquidado'
        ? 100
        : (int) round($doneCount * 100 / $totalCount);
    $canEdit = auth()->user()->tieneAcceso('descarga_contenedores', 'puede_editar');
    $visibleBlockers = $blockers->map(function ($blocker) use ($puedeGestionarCostos) {
        if ($puedeGestionarCostos) {
            return $blocker;
        }

        return match ($blocker) {
            'falta pago colaborador' => 'tarifa FACT pendiente de cierre',
            'tarifa pendiente de revisión' => 'tarifa FACT pendiente de revisión',
            default => $blocker,
        };
    });
@endphp

<div class="workflow-card">
    <div class="workflow-header">
        <div>
            <h4>Estado del proceso</h4>
            <p>
                @if($descarga->estado === 'borrador')
                    {{ $blockers->isEmpty() ? 'Registro completo para validación.' : 'Registro en carga, con pendientes antes de validar.' }}
                @elseif($descarga->estado === 'validado')
                    Registro validado y disponible para {{ $puedeGestionarCostos ? 'liquidación.' : 'seguimiento de coordinación.' }}
                @elseif($descarga->estado === 'liquidado')
                    Registro liquidado y bloqueado para proteger el cierre.
                @else
                    Registro en seguimiento operativo.
                @endif
            </p>
        </div>
        <div class="workflow-progress-number">{{ $progress }}%</div>
    </div>

    <div class="workflow-progress" aria-label="Avance del registro">
        <span style="width: {{ $progress }}%"></span>
    </div>

    <div class="workflow-steps">
        @foreach($checklist as $item)
            <div class="workflow-step {{ $item['done'] ? 'done' : 'pending' }}">
                <i class="bi {{ $item['done'] ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                <div>
                    <strong>{{ $item['label'] }}</strong>
                    <small>{{ $item['detail'] }}</small>
                </div>
            </div>
        @endforeach
    </div>

    @if($descarga->estado === 'borrador')
        @if($blockers->isEmpty())
            <div class="workflow-action success">
                <span><strong>Listo para validar.</strong> La coordinación puede pasar este registro a estado validado.</span>
                @if($canEdit)
                    <form method="POST" action="{{ route('descarga-contenedores.validar', $descarga) }}" onsubmit="return confirm('¿Validar este registro de contenedor?')">
                        @csrf @method('PATCH')
                        <button class="btn-premium" type="submit"><i class="bi bi-check2-circle"></i> Validar</button>
                    </form>
                @endif
            </div>
        @else
            <div class="workflow-action warning">
                <span><strong>Antes de validar falta:</strong> {{ $visibleBlockers->implode(', ') }}.</span>
                @if($canEdit)
                    <a href="{{ route('descarga-contenedores.edit', $descarga) }}" class="btn-secondary"><i class="bi bi-pencil"></i> Completar registro</a>
                @endif
            </div>
        @endif
    @elseif($descarga->estado === 'validado')
        <div class="workflow-action success">
            <span><strong>Validado.</strong> {{ $puedeGestionarCostos ? 'Puede revisarse en liquidación o reabrirse si se detecta una corrección.' : 'Queda disponible para seguimiento operativo.' }}</span>
            @if($puedeGestionarCostos && $canEdit)
                <form method="POST" action="{{ route('descarga-contenedores.liquidar', $descarga) }}" onsubmit="return confirm('¿Marcar este registro como liquidado?')">
                    @csrf @method('PATCH')
                    <button class="btn-premium" type="submit"><i class="bi bi-cash-stack"></i> Liquidar</button>
                </form>
            @endif
        </div>
    @elseif($descarga->estado === 'liquidado')
        <div class="workflow-action locked">
            <span><strong>Liquidado.</strong> Para corregirlo, coordinación debe reabrirlo como validado.</span>
        </div>
    @endif
</div>

@once
<style>
.workflow-card {
    display: grid;
    gap: .85rem;
    margin-bottom: 1rem;
    padding: 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-color);
    box-shadow: 0 10px 22px rgba(15, 23, 42, .06);
}
.workflow-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
}
.workflow-header h4 {
    margin: 0 0 .2rem;
    color: var(--text-main);
    font-size: .95rem;
}
.workflow-header p {
    margin: 0;
    color: var(--text-muted);
    font-size: .82rem;
}
.workflow-progress-number {
    min-width: 58px;
    text-align: right;
    color: var(--primary-color);
    font-size: 1.35rem;
    font-weight: 800;
}
.workflow-progress {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(148, 163, 184, .22);
}
.workflow-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--primary-color), var(--success-color));
}
.workflow-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .6rem;
}
.workflow-step {
    display: flex;
    gap: .5rem;
    min-width: 0;
    padding: .6rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: rgba(148, 163, 184, .06);
}
.workflow-step i { margin-top: .1rem; }
.workflow-step.done i { color: var(--success-color); }
.workflow-step.pending i { color: #d97706; }
.workflow-step strong {
    display: block;
    color: var(--text-main);
    font-size: .8rem;
}
.workflow-step small {
    display: block;
    margin-top: .12rem;
    color: var(--text-muted);
    font-size: .73rem;
}
.workflow-action {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
    padding: .75rem .85rem;
    border-radius: 8px;
    font-size: .82rem;
}
.workflow-action.success {
    background: rgba(16, 185, 129, .10);
    color: var(--text-main);
}
.workflow-action.warning {
    background: rgba(217, 119, 6, .12);
    color: var(--text-main);
}
.workflow-action.locked {
    background: rgba(100, 116, 139, .14);
    color: var(--text-main);
}
.workflow-action form { margin: 0; }
@media (max-width: 980px) {
    .workflow-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .workflow-header,
    .workflow-action {
        flex-direction: column;
        align-items: stretch;
    }
    .workflow-progress-number { text-align: left; }
    .workflow-steps { grid-template-columns: 1fr; }
}
</style>
@endonce
