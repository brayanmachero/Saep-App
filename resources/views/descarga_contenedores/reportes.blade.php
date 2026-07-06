@extends('layouts.app')
@section('title','Reportes Contenedores')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Reportes</h2>
            <p class="page-subheading">Resumen operativo por operación, centro, FACT y periodo.</p>
        </div>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')
    @include('descarga_contenedores._context_help', [
        'title' => 'Lectura de reportes',
        'items' => [
            'Los reportes usan los mismos filtros superiores para mantener una lectura consistente.',
            'Costo ref. y pago ref. corresponden a snapshots de tarifa guardados en cada registro.',
            'Los registros con tarifa por revisar pueden afectar totales económicos hasta que se corrijan.',
        ],
    ])

    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3>{{ $stats['registros'] }}</h3><p>Registros</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-info"><h3>{{ $stats['validadas'] }}</h3><p>Validadas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-boxes"></i></div>
            <div class="stat-info"><h3>{{ number_format((float) $stats['cajas'], 0, ',', '.') }}</h3><p>Cajas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-grid-3x3-gap"></i></div>
            <div class="stat-info"><h3>{{ number_format((float) $stats['pallets'], 1, ',', '.') }}</h3><p>Pallets</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-receipt"></i></div>
            <div class="stat-info"><h3>${{ number_format((float) $stats['costo'], 0, ',', '.') }}</h3><p>Costo ref.</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info"><h3>${{ number_format((float) $stats['pago'], 0, ',', '.') }}</h3><p>Pago ref.</p></div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('descarga-contenedores.reportes') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Contenedor, bodega, producto o FACT...">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Operación</label>
                <select name="operacion" class="form-control">
                    <option value="">Todas</option>
                    @foreach($operaciones as $operacion)
                        <option value="{{ $operacion }}" {{ request('operacion') === $operacion ? 'selected' : '' }}>{{ $operacion }}</option>
                    @endforeach
                </select>
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
                <label style="font-size:.75rem;color:var(--text-muted)">Estado @include('descarga_contenedores._help_icon', ['text' => 'Filtra el análisis por etapa del flujo: borrador, validado, cerrado o liquidado.'])</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach(['borrador' => 'Borrador', 'validado' => 'Validado', 'cerrado' => 'Cerrado', 'liquidado' => 'Liquidado'] as $value => $label)
                        <option value="{{ $value }}" {{ request('estado') === $value ? 'selected' : '' }}>{{ $label }}</option>
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
            @if(request()->hasAny(['buscar','operacion','centro_costo_id','estado','fecha_desde','fecha_hasta']))
                <a href="{{ route('descarga-contenedores.reportes') }}" class="btn-secondary"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="report-grid">
        <div class="glass-card">
            <h4 class="section-title">Por operación</h4>
            @include('descarga_contenedores._report_table', ['rows' => $porOperacion, 'nameLabel' => 'Operación'])
        </div>
        <div class="glass-card">
            <h4 class="section-title">Por centro</h4>
            @include('descarga_contenedores._report_table', ['rows' => $porCentro, 'nameLabel' => 'Centro'])
        </div>
        <div class="glass-card">
            <h4 class="section-title">Por FACT</h4>
            <div style="overflow-x:auto">
                <table class="data-table report-table">
                    <thead>
                        <tr>
                            <th>FACT</th>
                            <th>Proceso</th>
                            <th>Reg.</th>
                            <th>Cajas</th>
                            <th>Pago ref.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porFact as $row)
                        <tr>
                            <td><code>{{ $row->codigo }}</code></td>
                            <td>{{ $row->proceso }}</td>
                            <td>{{ $row->total }}</td>
                            <td>{{ number_format((float) $row->cajas, 0, ',', '.') }}</td>
                            <td>${{ number_format((float) $row->pago_total, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;padding:1.25rem;color:var(--text-muted)">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="glass-card">
            <h4 class="section-title">Por periodo</h4>
            <div style="overflow-x:auto">
                <table class="data-table report-table">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Reg.</th>
                            <th>Pago ref.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porPeriodo as $row)
                        <tr>
                            <td><strong>{{ $row->nombre }}</strong></td>
                            <td>{{ $row->total }}</td>
                            <td>${{ number_format((float) $row->pago_total, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="text-align:center;padding:1.25rem;color:var(--text-muted)">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.report-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; align-items: start; }
.report-table { min-width: 520px; }
.section-title {
    margin: 0 0 .75rem;
    color: var(--text-muted);
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--surface-border);
}
@media (max-width: 980px) {
    .report-grid { grid-template-columns: 1fr; }
}
</style>
@endsection
