@extends('layouts.app')
@section('title', 'Cotizaciones - Módulo Comercial')
@php
    $estadoLabels = [
        'en_cotizacion' => 'En cotización',
        'vigente' => 'Vigente/Aprobado',
        'no_vigente' => 'No vigente',
    ];
    $estadoOperativo = function ($estado) {
        return match ($estado) {
            'aprobada' => 'vigente',
            'rechazada', 'cancelada' => 'no_vigente',
            default => $estado,
        };
    };
    $estadoBadge = function ($estado) {
        return match (match ($estado) {
            'aprobada' => 'vigente',
            'rechazada', 'cancelada' => 'no_vigente',
            default => $estado,
        }) {
            'vigente' => 'badge-success',
            'en_cotizacion' => 'badge-warning',
            'no_vigente' => 'badge-secondary',
            default => 'badge-secondary',
        };
    };
    $puedeCrearComercial = auth()->user()->tieneAcceso('comercial', 'puede_crear');
    $hayFiltros = request()->hasAny([
        'q',
        'cliente_id',
        'centro_costo_id',
        'modalidad_id',
        'estado',
        'cargo',
        'fecha_desde',
        'fecha_hasta',
        'vigencia_desde',
        'vigencia_hasta',
        'vence_hasta',
    ]);
    $venceHasta = now()->addDays(30)->toDateString();
@endphp
@push('styles')
<style>
    .quote-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: .9rem;
        margin-bottom: 1rem;
    }

    .quote-kpi {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: 1rem;
        color: var(--text-primary);
        text-decoration: none;
        transition: transform .15s ease, border-color .15s ease;
    }

    .quote-kpi:hover {
        transform: translateY(-1px);
        border-color: var(--accent-primary);
    }

    .quote-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: var(--bg-tertiary);
        color: var(--accent-primary);
        font-size: 1.15rem;
        flex: 0 0 auto;
    }

    .quote-kpi-value {
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1;
    }

    .quote-kpi-label {
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .78rem;
    }

    .quote-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: .8rem;
        align-items: end;
    }

    .quote-actions {
        display: flex;
        gap: .25rem;
        align-items: center;
        flex-wrap: nowrap;
    }

    .quote-actions form {
        margin: 0;
    }
</style>
@endpush
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Cotizaciones</h2>
            <p class="page-subheading">Gestión de tarifas vigentes, versiones históricas y nuevas propuestas</p>
        </div>
        <div style="display:flex;gap:.5rem">
            @if($puedeCrearComercial)
            <a href="{{ route('comercial.cotizaciones.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nueva Cotización
            </a>
            @endif
        </div>
    </div>

    @include('partials._alerts')

    <div class="quote-kpis">
        <a class="glass-card quote-kpi" href="{{ route('comercial.cotizaciones.index', array_merge(request()->except(['page', 'vence_hasta']), ['estado' => 'vigente'])) }}">
            <span class="quote-kpi-icon"><i class="bi bi-check2-circle"></i></span>
            <span>
                <span class="quote-kpi-value">{{ $resumenEstados['vigentes'] ?? 0 }}</span>
                <span class="quote-kpi-label">Vigentes/Aprobadas</span>
            </span>
        </a>
        <a class="glass-card quote-kpi" href="{{ route('comercial.cotizaciones.index', array_merge(request()->except(['page', 'vence_hasta']), ['estado' => 'no_vigente'])) }}">
            <span class="quote-kpi-icon"><i class="bi bi-archive"></i></span>
            <span>
                <span class="quote-kpi-value">{{ $resumenEstados['no_vigentes'] ?? 0 }}</span>
                <span class="quote-kpi-label">No vigentes</span>
            </span>
        </a>
        <a class="glass-card quote-kpi" href="{{ route('comercial.cotizaciones.index', array_merge(request()->except(['page', 'vence_hasta']), ['estado' => 'en_cotizacion'])) }}">
            <span class="quote-kpi-icon"><i class="bi bi-pencil-square"></i></span>
            <span>
                <span class="quote-kpi-value">{{ $resumenEstados['en_cotizacion'] ?? 0 }}</span>
                <span class="quote-kpi-label">En cotización</span>
            </span>
        </a>
        <a class="glass-card quote-kpi" href="{{ route('comercial.cotizaciones.index', array_merge(request()->except(['page', 'estado']), ['vence_hasta' => $venceHasta])) }}">
            <span class="quote-kpi-icon"><i class="bi bi-calendar-event"></i></span>
            <span>
                <span class="quote-kpi-value">{{ $resumenEstados['por_vencer'] ?? 0 }}</span>
                <span class="quote-kpi-label">Vencen en 30 días</span>
            </span>
        </a>
    </div>

    <div class="glass-card" style="margin-bottom:1rem">
        <form method="GET" action="{{ route('comercial.cotizaciones.index') }}" class="quote-filter-grid">
            <div class="form-group" style="margin:0">
                <label><i class="bi bi-search"></i> Buscar</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Número, cliente, CC o cargo">
            </div>
            <div class="form-group" style="margin:0">
                <label>Cliente</label>
                <select name="cliente_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                        {{ $cliente->nombre_comercial ?? $cliente->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Centro de costo</label>
                <select name="centro_costo_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($centrosCosto as $centro)
                    <option value="{{ $centro->id }}" {{ request('centro_costo_id') == $centro->id ? 'selected' : '' }}>
                        {{ $centro->codigo }} - {{ $centro->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Modalidad</label>
                <select name="modalidad_id" class="form-control">
                    <option value="">Todas</option>
                    @foreach($modalidades as $modalidad)
                    <option value="{{ $modalidad->id }}" {{ request('modalidad_id') == $modalidad->id ? 'selected' : '' }}>
                        {{ $modalidad->codigo }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach($estadoLabels as $estado => $label)
                    <option value="{{ $estado }}" {{ request('estado') === $estado ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Cargo</label>
                <input type="text" name="cargo" value="{{ request('cargo') }}" class="form-control" placeholder="Cargo o puesto">
            </div>
            <div class="form-group" style="margin:0">
                <label>Creada desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0">
                <label>Creada hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0">
                <label>Vigente desde</label>
                <input type="date" name="vigencia_desde" value="{{ request('vigencia_desde') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0">
                <label>Vigente hasta</label>
                <input type="date" name="vigencia_hasta" value="{{ request('vigencia_hasta') }}" class="form-control">
            </div>
            <div style="display:flex;gap:.5rem;align-items:end">
                <button type="submit" class="btn-premium">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                @if($hayFiltros)
                <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">Limpiar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="glass-card">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem">
            <div>
                <h3 style="margin:0;font-size:1rem">Cotizaciones</h3>
                <p style="margin:.25rem 0 0 0;color:var(--text-muted);font-size:.85rem">Las vigentes quedan operativas; las no vigentes conservan su rango histórico para consultas.</p>
            </div>
        </div>

        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente / CC</th>
                        <th>Cargo</th>
                        <th>Modalidad</th>
                        <th>Precio venta</th>
                        <th>Estado / vigencia</th>
                        <th>Responsable</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cotizaciones as $cotizacion)
                    @php
                        $estadoNormalizado = $estadoOperativo($cotizacion->estado);
                        $periodoInicio = $cotizacion->fecha_vigencia
                            ? $cotizacion->fecha_vigencia->format('d/m/Y')
                            : optional($cotizacion->fecha_vigencia_desde)->format('d/m/Y');
                        $periodoFin = $estadoNormalizado === 'vigente'
                            ? (optional($cotizacion->fecha_vigencia_hasta)->format('d/m/Y') ?? 'Sin término')
                            : (optional($cotizacion->fecha_vigencia_hasta)->format('d/m/Y') ?? 'Sin término');
                    @endphp
                    <tr>
                        <td>
                            <code style="font-size:.85rem">{{ $cotizacion->numero }}</code>
                            <div style="font-size:.75rem;color:var(--text-muted)">v{{ $cotizacion->version }}</div>
                        </td>
                        <td>
                            <strong>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</strong>
                            <div style="font-size:.8rem;color:var(--text-muted)">{{ $cotizacion->centroCosto->codigo }} - {{ $cotizacion->centroCosto->nombre }}</div>
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
                            <span class="badge {{ $estadoBadge($cotizacion->estado) }}">
                                {{ $estadoLabels[$estadoNormalizado] ?? ucfirst(str_replace('_', ' ', $estadoNormalizado)) }}
                            </span>
                            <div style="margin-top:.35rem;font-size:.78rem;color:var(--text-muted)">
                                {{ $periodoInicio ?? 'Sin inicio' }} a {{ $periodoFin }}
                            </div>
                        </td>
                        <td style="font-size:.85rem">
                            <strong>{{ $cotizacion->usuario->name ?? 'Sistema' }}</strong>
                            <div style="font-size:.78rem;color:var(--text-muted)">{{ $cotizacion->updated_at->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>
                            <div class="quote-actions">
                                <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="icon-btn" title="Ver detalles">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                @if($cotizacion->estado === 'en_cotizacion')
                                <a href="{{ route('comercial.cotizaciones.edit', $cotizacion) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                @endif
                                @if($puedeCrearComercial)
                                <form method="POST" action="{{ route('comercial.cotizaciones.duplicar', $cotizacion) }}" onsubmit="return confirm('¿Crear una copia editable de esta cotización?')">
                                    @csrf
                                    <button type="submit" class="icon-btn" title="Duplicar como borrador">
                                        <i class="bi bi-files"></i>
                                    </button>
                                </form>
                                @endif
                                <a href="{{ route('comercial.cotizaciones.historico', $cotizacion) }}" class="icon-btn" title="Histórico">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                <a href="{{ route('comercial.cotizaciones.pdf', $cotizacion) }}" class="icon-btn" title="Descargar PDF" target="_blank">
                                    <i class="bi bi-file-pdf-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            No hay cotizaciones para los filtros aplicados.
                            @if($puedeCrearComercial)
                            <a href="{{ route('comercial.cotizaciones.create') }}">Crear la primera</a>
                            @endif
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
