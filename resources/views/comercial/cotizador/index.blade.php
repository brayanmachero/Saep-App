@extends('layouts.app')
@section('title', 'Cotizaciones - Módulo Comercial')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Cotizaciones</h2>
            <p class="page-subheading">Gestión de cotizaciones de servicios EST y SUB</p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('comercial.cotizaciones.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nueva Cotización
            </a>
        </div>
    </div>

    @include('partials._alerts')

    {{-- Filtros --}}
    <div class="glass-card" style="margin-bottom:1rem">
        <form method="GET" action="{{ route('comercial.cotizaciones.index') }}" style="display:flex;gap:1rem;flex-wrap:wrap">
            <div style="flex:1;min-width:250px">
                <label style="display:block;font-size:.875rem;margin-bottom:.5rem"><i class="bi bi-search"></i> Cliente</label>
                <select name="cliente_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                        {{ $cliente->nombre_comercial ?? $cliente->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:250px">
                <label style="display:block;font-size:.875rem;margin-bottom:.5rem"><i class="bi bi-file-earmark"></i> Estado</label>
                <select name="estado" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="en_cotizacion" {{ request('estado') === 'en_cotizacion' ? 'selected' : '' }}>En Cotización</option>
                    <option value="aprobada" {{ request('estado') === 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                    <option value="vigente" {{ request('estado') === 'vigente' ? 'selected' : '' }}>Vigente</option>
                    <option value="no_vigente" {{ request('estado') === 'no_vigente' ? 'selected' : '' }}>No Vigente</option>
                    <option value="rechazada" {{ request('estado') === 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                    <option value="cancelada" {{ request('estado') === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Tabla de Cotizaciones --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Cargo</th>
                        <th>Modalidad</th>
                        <th>Precio Venta</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cotizaciones as $cotizacion)
                    <tr>
                        <td>
                            <code style="font-size:.85rem">{{ $cotizacion->numero }}</code>
                        </td>
                        <td>
                            <strong>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</strong>
                            <div style="font-size:.8rem;color:var(--text-muted)">{{ $cotizacion->cliente->rut }}</div>
                        </td>
                        <td>
                            <strong>{{ $cotizacion->cargo }}</strong>
                            @if($cotizacion->titulo)
                            <div style="font-size:.8rem;color:var(--text-muted)">{{ $cotizacion->titulo }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $cotizacion->modalidad->codigo === 'EST' ? 'badge-info' : 'badge-warning' }}">
                                {{ $cotizacion->modalidad->codigo }}
                            </span>
                        </td>
                        <td>
                            <strong>${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</strong>
                        </td>
                        <td>
                            <span class="badge {{
                                $cotizacion->estado === 'vigente' ? 'badge-success' :
                                ($cotizacion->estado === 'aprobada' ? 'badge-info' :
                                ($cotizacion->estado === 'en_cotizacion' ? 'badge-warning' : 'badge-danger'))
                            }}">
                                {{ ucfirst(str_replace('_', ' ', $cotizacion->estado)) }}
                            </span>
                        </td>
                        <td style="font-size:.9rem;color:var(--text-muted)">
                            {{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}
                        </td>
                        <td>
                            <div style="display:flex;gap:.25rem">
                                <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="icon-btn" title="Ver Detalles">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                @if($cotizacion->estado === 'en_cotizacion')
                                <a href="{{ route('comercial.cotizaciones.edit', $cotizacion) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                @endif
                                <a href="{{ route('comercial.cotizaciones.pdf', $cotizacion) }}" class="icon-btn" title="Descargar PDF" target="_blank">
                                    <i class="bi bi-file-pdf-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            No hay cotizaciones registradas. <a href="{{ route('comercial.cotizaciones.create') }}">Crear la primera</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div style="padding:1.5rem;text-align:center">
            {{ $cotizaciones->links() }}
        </div>
    </div>
</div>
@endsection
