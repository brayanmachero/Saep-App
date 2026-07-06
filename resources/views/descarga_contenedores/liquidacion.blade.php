@extends('layouts.app')
@section('title','Liquidación Contenedores')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Liquidación</h2>
            <p class="page-subheading">Pago referencial agrupado por trabajador. Por defecto sólo considera registros validados.</p>
        </div>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')
    @include('descarga_contenedores._context_help', [
        'title' => 'Criterio de liquidación',
        'items' => [
            'Por defecto se consideran sólo registros validados para evitar pagar borradores incompletos.',
            'El monto por trabajador se calcula con el porcentaje registrado en cada descarga.',
            'Exportar CSV respeta los filtros activos de búsqueda, centro, estado y fechas.',
        ],
        'tone' => 'success',
    ])
    @php
        $registrosQuery = request()->only(['buscar', 'centro_costo_id', 'fecha_desde', 'fecha_hasta']);
        if ($estadoSeleccionado !== 'todos') {
            $registrosQuery['estado'] = $estadoSeleccionado;
        }
    @endphp
    <div class="liquidacion-scope">
        <div>
            <strong>Alcance actual</strong>
            <span>
                Estado: {{ $estadoSeleccionado === 'todos' ? 'todos los estados' : ucfirst($estadoSeleccionado) }}.
                {{ $stats['descargas'] }} descarga{{ $stats['descargas'] === 1 ? '' : 's' }} incluida{{ $stats['descargas'] === 1 ? '' : 's' }} en el cálculo.
            </span>
        </div>
        <a href="{{ route('descarga-contenedores.index', $registrosQuery) }}" class="btn-secondary">
            <i class="bi bi-list-ul"></i> Ver registros
        </a>
    </div>

    <div class="stats-grid">
        <div class="glass-card stat-item" title="Trabajadores únicos incluidos en el filtro de liquidación actual.">
            <div class="stat-icon primary"><i class="bi bi-people"></i></div>
            <div class="stat-info"><h3>{{ $stats['trabajadores'] }}</h3><p>Trabajadores</p></div>
        </div>
        <div class="glass-card stat-item" title="Descargas consideradas según estado y filtros aplicados.">
            <div class="stat-icon primary"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3>{{ $stats['descargas'] }}</h3><p>Descargas</p></div>
        </div>
        <div class="glass-card stat-item" title="Asignaciones de trabajadores dentro de las descargas filtradas.">
            <div class="stat-icon success"><i class="bi bi-list-check"></i></div>
            <div class="stat-info"><h3>{{ $stats['participaciones'] }}</h3><p>Participaciones</p></div>
        </div>
        <div class="glass-card stat-item" title="Suma de montos calculados para trabajadores según porcentajes.">
            <div class="stat-icon success"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info"><h3>${{ number_format((float) $stats['monto'], 0, ',', '.') }}</h3><p>Monto ref.</p></div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('descarga-contenedores.liquidacion') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Trabajador, RUT, contenedor o FACT...">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Centro</label>
                <select name="centro_costo_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($centros as $centro)
                        <option value="{{ $centro->id }}" {{ request('centro_costo_id') == $centro->id ? 'selected' : '' }}>{{ $centro->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Estado @include('descarga_contenedores._help_icon', ['text' => 'Validado es el estado recomendado para cálculo. Todos incluye borradores/liquidados según filtro.'])</label>
                <select name="estado" class="form-control">
                    <option value="validado" {{ $estadoSeleccionado === 'validado' ? 'selected' : '' }}>Validado</option>
                    <option value="todos" {{ $estadoSeleccionado === 'todos' ? 'selected' : '' }}>Todos</option>
                    @foreach(['borrador' => 'Borrador', 'validado' => 'Validado', 'cerrado' => 'Cerrado', 'liquidado' => 'Liquidado'] as $value => $label)
                        @continue($value === 'validado')
                        <option value="{{ $value }}" {{ $estadoSeleccionado === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="form-control">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="form-control">
            </div>
            <button type="submit" class="btn-premium"><i class="bi bi-search"></i> Filtrar</button>
            <a href="{{ route('descarga-contenedores.liquidacion.exportar', request()->query()) }}" class="btn-secondary" title="Descarga la liquidación con los filtros actualmente aplicados"><i class="bi bi-download"></i> Exportar CSV</a>
            @if(request()->hasAny(['buscar','centro_costo_id','estado','fecha_desde','fecha_hasta']))
                <a href="{{ route('descarga-contenedores.liquidacion') }}" class="btn-secondary"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Trabajador</th>
                        <th>RUT</th>
                        <th>Cargo</th>
                        <th>Centro</th>
                        <th>Periodo</th>
                        <th>Descargas</th>
                        <th title="Suma de porcentajes de participación acumulados en el periodo filtrado. Puede superar 100% si el trabajador participó en varias descargas.">% total</th>
                        <th title="Suma de montos calculados por descarga para este trabajador.">Monto ref.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($liquidaciones as $item)
                    <tr>
                        <td><strong>{{ $item->nombre_snapshot }}</strong></td>
                        <td>{{ $item->rut_snapshot ?: '—' }}</td>
                        <td>{{ $item->cargo_snapshot ?: '—' }}</td>
                        <td>{{ $item->centro_costo_snapshot ?: '—' }}</td>
                        <td>
                            {{ $item->fecha_desde ? \Carbon\Carbon::parse($item->fecha_desde)->format('d/m/Y') : '—' }}
                            <span style="color:var(--text-muted)">a</span>
                            {{ $item->fecha_hasta ? \Carbon\Carbon::parse($item->fecha_hasta)->format('d/m/Y') : '—' }}
                        </td>
                        <td>{{ $item->descargas_count }} <span style="color:var(--text-muted);font-size:.75rem">({{ $item->participaciones_count }} part.)</span></td>
                        <td>{{ number_format((float) $item->porcentaje_total, 2, ',', '.') }}%</td>
                        <td><strong>${{ number_format((float) $item->monto_total, 0, ',', '.') }}</strong></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">No hay liquidación para el filtro seleccionado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($liquidaciones->hasPages())
            <div style="padding:1rem 0">{{ $liquidaciones->links() }}</div>
        @endif
    </div>
</div>
<style>
.liquidacion-scope {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: .85rem 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-color);
}
.liquidacion-scope div {
    display: grid;
    gap: .15rem;
}
.liquidacion-scope strong {
    color: var(--text-main);
    font-size: .86rem;
}
.liquidacion-scope span {
    color: var(--text-muted);
    font-size: .8rem;
}
@media (max-width: 640px) {
    .liquidacion-scope {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
@endsection
