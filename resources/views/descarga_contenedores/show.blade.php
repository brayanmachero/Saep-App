@extends('layouts.app')
@section('title','Detalle Descarga')
@section('content')
@php
    $puedeGestionarCostos = auth()->user()->puedeGestionarCostosDescargaContenedores();
    $participantesColspan = $puedeGestionarCostos ? 6 : 5;
@endphp
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $descarga->contenedor ?: 'Descarga #'.$descarga->id }}</h2>
            <p class="page-subheading">
                {{ $descarga->fecha?->format('d/m/Y') ?? 'Sin fecha' }}
                · {{ $descarga->bodega ?: ($descarga->centroCosto->nombre ?? 'Sin bodega') }}
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('descarga-contenedores.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            @if(auth()->user()->tieneAcceso('descarga_contenedores', 'puede_editar'))
            @if($descarga->estado === 'borrador')
            <form method="POST" action="{{ route('descarga-contenedores.validar', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Validar este registro de contenedor?')">
                @csrf @method('PATCH')
                <button class="btn-premium" type="submit"><i class="bi bi-check2-circle"></i> Validar</button>
            </form>
            @elseif($descarga->estado === 'validado')
            <form method="POST" action="{{ route('descarga-contenedores.volver-borrador', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Devolver este registro a borrador?')">
                @csrf @method('PATCH')
                <button class="btn-secondary" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Volver a borrador</button>
            </form>
            @endif
            <a href="{{ route('descarga-contenedores.edit', $descarga) }}" class="btn-premium">
                <i class="bi bi-pencil"></i> Editar
            </a>
            @endif
        </div>
    </div>

    @include('partials._alerts')

    <div class="detail-grid">
        <div class="glass-card">
            <h4 class="section-title">Resumen</h4>
            <dl class="detail-list">
                <div><dt>Estado</dt><dd><span class="{{ $descarga->estadoBadge['class'] }}">{{ $descarga->estadoBadge['label'] }}</span></dd></div>
                <div><dt>Operación</dt><dd>{{ $descarga->operacion ?: '—' }}</dd></div>
                <div><dt>Centro costo</dt><dd>{{ $descarga->centroCosto->nombre ?? '—' }}</dd></div>
                <div><dt>Bodega</dt><dd>{{ $descarga->bodega ?: '—' }}</dd></div>
                <div><dt>Supervisor sistema</dt><dd>{{ $descarga->supervisor?->nombre_completo ?: '—' }}</dd></div>
                <div><dt>Encargado texto</dt><dd>{{ $descarga->supervisor_nombre ?: '—' }}</dd></div>
                <div><dt>Facturación</dt><dd>{{ $descarga->facturacion_mes ?: '—' }}</dd></div>
                <div><dt>Equipo descarga</dt><dd>{{ $descarga->equipo_descarga ?: '—' }}</dd></div>
                <div><dt>Código FACT.</dt><dd><code>{{ $descarga->fact_codigo ?: '—' }}</code></dd></div>
                @if($puedeGestionarCostos)
                <div><dt>Tarifa</dt><dd>{{ $descarga->tarifa_cliente_snapshot ?: '—' }}{{ $descarga->tarifa_proceso_snapshot ? ' · '.$descarga->tarifa_proceso_snapshot : '' }}</dd></div>
                <div><dt>Costo unitario</dt><dd>{{ $descarga->costo_unitario_snapshot !== null ? '$'.number_format((float) $descarga->costo_unitario_snapshot, 0, ',', '.') : '—' }}</dd></div>
                <div><dt>Pago colaboradores</dt><dd>
                    @if($descarga->requiere_revision_tarifa)
                        <span class="badge warning">Revisar tarifa</span>
                    @elseif($descarga->pago_colaborador_snapshot !== null)
                        ${{ number_format((float) $descarga->pago_colaborador_snapshot, 0, ',', '.') }}
                    @else
                        —
                    @endif
                </dd></div>
                @endif
            </dl>
        </div>

        <div class="glass-card">
            <h4 class="section-title">Datos operativos</h4>
            <dl class="detail-list">
                <div><dt>Hora cita</dt><dd>{{ $descarga->hora_cita ? substr($descarga->hora_cita, 0, 5) : '—' }}</dd></div>
                <div><dt>Inicio descarga</dt><dd>{{ $descarga->hora_inicio_descarga ? substr($descarga->hora_inicio_descarga, 0, 5) : '—' }}</dd></div>
                <div><dt>Término descarga</dt><dd>{{ $descarga->hora_termino_descarga ? substr($descarga->hora_termino_descarga, 0, 5) : '—' }}</dd></div>
                <div><dt>Ítems</dt><dd>{{ $descarga->item ?? '—' }}</dd></div>
                <div><dt>Cajas</dt><dd>{{ $descarga->cajas !== null ? number_format($descarga->cajas, 0, ',', '.') : '—' }}</dd></div>
                <div><dt>Pallets</dt><dd>{{ $descarga->pallets !== null ? number_format((float) $descarga->pallets, 2, ',', '.') : '—' }}</dd></div>
                <div><dt>Producto</dt><dd>{{ $descarga->producto ?: '—' }}</dd></div>
                <div><dt>Origen</dt><dd>{{ ucfirst($descarga->origen) }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="glass-card" style="margin-top:1rem">
        <h4 class="section-title">Trabajadores participantes</h4>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Cargo</th>
                        <th>Centro origen</th>
                        <th>%</th>
                        @if($puedeGestionarCostos)
                        <th>Monto</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($descarga->participantes as $participante)
                    <tr>
                        <td><strong>{{ $participante->nombre_snapshot }}</strong></td>
                        <td>{{ $participante->rut_snapshot ?: '—' }}</td>
                        <td>{{ $participante->cargo_snapshot ?: '—' }}</td>
                        <td>{{ $participante->centro_costo_snapshot ?: '—' }}</td>
                        <td>{{ $participante->porcentaje_participacion !== null ? number_format((float) $participante->porcentaje_participacion, 2, ',', '.') . '%' : '—' }}</td>
                        @if($puedeGestionarCostos)
                        <td>{{ $participante->monto_calculado !== null ? '$'.number_format((float) $participante->monto_calculado, 0, ',', '.') : '—' }}</td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $participantesColspan }}" style="text-align:center;padding:1.5rem;color:var(--text-muted)">Sin trabajadores asociados.</td></tr>
                    @endforelse
                </tbody>
                @if($descarga->participantes->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="4" style="text-align:right">Total</th>
                        <th>{{ number_format((float) $descarga->participantes->sum('porcentaje_participacion'), 2, ',', '.') }}%</th>
                        @if($puedeGestionarCostos)
                        <th>${{ number_format((float) $descarga->participantes->sum('monto_calculado'), 0, ',', '.') }}</th>
                        @endif
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($puedeGestionarCostos && $tarifas->isNotEmpty())
    <div class="glass-card" style="margin-top:1rem">
        <h4 class="section-title">Tarifas relacionadas</h4>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead><tr><th>Cliente</th><th>Código</th><th>Proceso</th><th>Costo UN</th><th>Pago colaborador</th><th>Obs.</th></tr></thead>
                <tbody>
                    @foreach($tarifas as $tarifa)
                    <tr>
                        <td>{{ $tarifa->cliente }}</td>
                        <td><code>{{ $tarifa->codigo }}</code></td>
                        <td>{{ $tarifa->proceso }}</td>
                        <td>{{ $tarifa->requiere_revision ? 'Revisar cotización' : '$'.number_format((float) $tarifa->costo_unitario, 0, ',', '.') }}</td>
                        <td>{{ $tarifa->requiere_revision ? 'Revisar cotización' : '$'.number_format((float) $tarifa->pago_colaborador, 0, ',', '.') }}</td>
                        <td>{{ $tarifa->observaciones ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($descarga->observacion)
    <div class="glass-card" style="margin-top:1rem">
        <h4 class="section-title">Observación</h4>
        <p style="margin:0;line-height:1.55">{{ $descarga->observacion }}</p>
    </div>
    @endif
</div>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.section-title {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--surface-border);
}
.detail-list { display: grid; gap: .75rem; margin: 0; }
.detail-list div { display: grid; grid-template-columns: 150px 1fr; gap: .75rem; }
.detail-list dt { color: var(--text-muted); font-size: .82rem; }
.detail-list dd { margin: 0; font-weight: 600; }
@media (max-width: 760px) {
    .detail-grid { grid-template-columns: 1fr; }
    .detail-list div { grid-template-columns: 1fr; gap: .2rem; }
}
</style>
@endsection
