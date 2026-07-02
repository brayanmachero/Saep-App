@extends('layouts.app')
@section('title','Liquidación Contenedores')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Liquidación</h2>
            <p class="page-subheading">Productividad y pago referencial agrupado por trabajador.</p>
        </div>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')

    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-people"></i></div>
            <div class="stat-info"><h3>{{ $stats['trabajadores'] }}</h3><p>Trabajadores</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3>{{ $stats['descargas'] }}</h3><p>Descargas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-list-check"></i></div>
            <div class="stat-info"><h3>{{ $stats['participaciones'] }}</h3><p>Participaciones</p></div>
        </div>
        <div class="glass-card stat-item">
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
                <label style="font-size:.75rem;color:var(--text-muted)">Estado</label>
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
                        <th>% total</th>
                        <th>Monto ref.</th>
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
@endsection
