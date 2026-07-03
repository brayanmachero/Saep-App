@extends('layouts.app')
@section('title','Dotación Contenedores')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Dotación</h2>
            <p class="page-subheading">Trabajadores Talana disponibles para descargas y programación.</p>
        </div>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')

    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-person-check"></i></div>
            <div class="stat-info"><h3>{{ $stats['activos'] }}</h3><p>Activos Talana</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-person-dash"></i></div>
            <div class="stat-info"><h3>{{ $stats['inactivos'] }}</h3><p>Inactivos Talana</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-building"></i></div>
            <div class="stat-info"><h3>{{ $stats['centros'] }}</h3><p>Centros con dotación</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-person-badge"></i></div>
            <div class="stat-info"><h3>{{ $stats['cargos'] }}</h3><p>Cargos clasificados</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-people"></i></div>
            <div class="stat-info"><h3>{{ $stats['participantes'] }}</h3><p>Usados en descargas</p></div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('descarga-contenedores.dotacion') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Nombre, RUT, cargo o centro...">
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
                <label style="font-size:.75rem;color:var(--text-muted)">Cargo</label>
                <select name="cargo" class="form-control">
                    <option value="">Todos</option>
                    @foreach($cargos as $cargo)
                        <option value="{{ $cargo }}" {{ request('cargo') === $cargo ? 'selected' : '' }}>{{ $cargo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="activos" {{ request('estado') === 'activos' ? 'selected' : '' }}>Activos</option>
                    <option value="inactivos" {{ request('estado') === 'inactivos' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <button type="submit" class="btn-premium"><i class="bi bi-search"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','centro_costo_id','cargo','estado']))
                <a href="{{ route('descarga-contenedores.dotacion') }}" class="btn-secondary"><i class="bi bi-x-lg"></i> Limpiar</a>
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
                        <th>Estado</th>
                        <th>Descargas</th>
                        <th>Monto ref.</th>
                        <th>Última</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trabajadores as $trabajador)
                    @php($uso = $participacion->get($trabajador->id))
                    <tr>
                        <td><strong>{{ $trabajador->nombre_completo ?: $trabajador->nombre }}</strong></td>
                        <td>{{ $trabajador->rut ?: '—' }}</td>
                        <td>{{ $trabajador->cargo?->nombre ?: $trabajador->cargo_nombre ?: '—' }}</td>
                        <td>{{ $trabajador->centroCosto?->nombre ?: $trabajador->centro_costo_nombre ?: '—' }}</td>
                        <td>
                            <span class="{{ $trabajador->activo ? 'badge-success' : 'badge-warning' }}">
                                {{ $trabajador->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>{{ $uso?->descargas ?? 0 }} <span style="color:var(--text-muted);font-size:.75rem">({{ $uso?->participaciones ?? 0 }} part.)</span></td>
                        <td>${{ number_format((float) ($uso?->monto_total ?? 0), 0, ',', '.') }}</td>
                        <td>{{ $uso?->ultima_descarga ? \Carbon\Carbon::parse($uso->ultima_descarga)->format('d/m/Y') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">No hay trabajadores para el filtro seleccionado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($trabajadores->hasPages())
            <div style="padding:1rem 0">{{ $trabajadores->links() }}</div>
        @endif
    </div>
</div>
@endsection
