@extends('layouts.app')
@section('title', 'Reporte de Cotizaciones')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Reporte de Cotizaciones</h2>
            <p class="page-subheading">Análisis y estadísticas de cotizaciones generadas</p>
        </div>
        <form method="POST" action="{{ route('comercial.reportes.exportExcel') }}">
            @csrf
            <input type="hidden" name="cliente_id" value="{{ request('cliente_id') }}">
            <input type="hidden" name="estado" value="{{ request('estado') }}">
            <input type="hidden" name="fecha_desde" value="{{ request('fecha_desde') }}">
            <input type="hidden" name="fecha_hasta" value="{{ request('fecha_hasta') }}">
            <button type="submit" class="btn-secondary">
                <i class="bi bi-file-earmark-excel-fill"></i> Exportar Excel
            </button>
        </form>
    </div>

    @include('partials._alerts')

    {{-- Filtros --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <form method="GET" action="{{ route('comercial.reportes.cotizaciones') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
            <div>
                <label style="display:block;font-size:.875rem;margin-bottom:.5rem"><i class="bi bi-person"></i> Cliente</label>
                <select name="cliente_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                        {{ $cliente->nombre_comercial ?? $cliente->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block;font-size:.875rem;margin-bottom:.5rem"><i class="bi bi-file-earmark"></i> Estado</label>
                <select name="estado" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="en_cotizacion" {{ request('estado') === 'en_cotizacion' ? 'selected' : '' }}>En Cotización</option>
                    <option value="aprobada" {{ request('estado') === 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                    <option value="vigente" {{ request('estado') === 'vigente' ? 'selected' : '' }}>Vigente</option>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:.875rem;margin-bottom:.5rem"><i class="bi bi-calendar3"></i> Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}" onchange="this.form.submit()">
            </div>

            <div>
                <label style="display:block;font-size:.875rem;margin-bottom:.5rem"><i class="bi bi-calendar3"></i> Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    {{-- Estadísticas Rápidas --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem">
        <div class="glass-card" style="border-left:4px solid var(--accent-primary)">
            <div style="font-size:.85rem;color:var(--text-muted)">Total Cotizaciones</div>
            <div style="font-size:2rem;font-weight:700;margin-top:.5rem">{{ $totalCotizaciones }}</div>
        </div>

        <div class="glass-card" style="border-left:4px solid var(--success-color)">
            <div style="font-size:.85rem;color:var(--text-muted)">Vigentes</div>
            <div style="font-size:2rem;font-weight:700;margin-top:.5rem">{{ $vigentes }}</div>
        </div>

        <div class="glass-card" style="border-left:4px solid var(--warning-color)">
            <div style="font-size:.85rem;color:var(--text-muted)">Valor Total</div>
            <div style="font-size:1.8rem;font-weight:700;margin-top:.5rem;color:var(--accent-primary)">
                ${{ number_format($valorTotal, 0, ',', '.') }}
            </div>
        </div>

        <div class="glass-card" style="border-left:4px solid var(--accent-secondary)">
            <div style="font-size:.85rem;color:var(--text-muted)">Valor Promedio</div>
            <div style="font-size:1.8rem;font-weight:700;margin-top:.5rem">
                ${{ number_format($valorPromedio, 0, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- Tabla de Cotizaciones --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Modalidad</th>
                        <th>Remuneraciones</th>
                        <th>Cotizaciones</th>
                        <th>Provisiones</th>
                        <th>Precio Venta</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cotizaciones as $cotizacion)
                    <tr>
                        <td><code>{{ $cotizacion->numero }}</code></td>
                        <td>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</td>
                        <td>
                            <span class="badge {{ $cotizacion->modalidad->codigo === 'EST' ? 'badge-info' : 'badge-warning' }}">
                                {{ $cotizacion->modalidad->codigo }}
                            </span>
                        </td>
                        <td>${{ number_format($cotizacion->total_remuneraciones, 0, ',', '.') }}</td>
                        <td>${{ number_format($cotizacion->total_cotizaciones, 0, ',', '.') }}</td>
                        <td>${{ number_format($cotizacion->total_provisiones, 0, ',', '.') }}</td>
                        <td style="font-weight:600;color:var(--accent-primary)">${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge {{
                                $cotizacion->estado === 'vigente' ? 'badge-success' :
                                ($cotizacion->estado === 'aprobada' ? 'badge-info' : 'badge-warning')
                            }}">
                                {{ ucfirst(str_replace('_', ' ', $cotizacion->estado)) }}
                            </span>
                        </td>
                        <td style="font-size:.9rem">{{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="icon-btn" title="Ver">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            No hay cotizaciones que coincidan con los filtros aplicados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding:1.5rem;text-align:center">
            {{ $cotizaciones->links() }}
        </div>
    </div>
</div>
@endsection
