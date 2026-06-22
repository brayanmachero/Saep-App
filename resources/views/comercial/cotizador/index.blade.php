@extends('layouts.app')
@section('title', 'Cotizador Comercial')
@push('styles')
<style>
    .quote-workspace {
        display: grid;
        gap: 1rem;
    }

    .quote-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .9rem;
    }

    .quote-stat {
        display: block;
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        background: var(--surface-color);
        padding: 1rem;
        min-height: 92px;
        text-decoration: none;
        transition: border-color .15s ease, transform .15s ease;
    }

    .quote-stat:hover {
        border-color: var(--accent-primary);
        transform: translateY(-1px);
    }

    .quote-stat-label {
        color: var(--text-muted);
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .quote-stat-value {
        margin-top: .35rem;
        color: var(--text-primary);
        font-size: 1.55rem;
        font-weight: 850;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .quote-stat-note {
        margin-top: .4rem;
        color: var(--text-muted);
        font-size: .8rem;
    }

    .quote-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(180px, 1fr));
        gap: .8rem;
        align-items: end;
    }

    .quote-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .8rem;
    }

    .quote-section-title {
        margin: 0;
        color: var(--text-primary);
        font-size: 1rem;
        font-weight: 850;
    }

    .quote-section-copy {
        margin: .2rem 0 0;
        color: var(--text-muted);
        font-size: .85rem;
    }

    .quote-table-wrap {
        overflow-x: auto;
    }

    .quote-main {
        min-width: 220px;
    }

    .quote-main strong {
        display: block;
        color: var(--text-primary);
        font-size: .9rem;
    }

    .quote-main span,
    .quote-muted {
        color: var(--text-muted);
        font-size: .78rem;
    }

    .quote-price {
        font-weight: 850;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .quote-actions {
        display: flex;
        gap: .25rem;
        justify-content: flex-end;
        min-width: 112px;
    }

    .quote-empty {
        padding: 1.6rem;
        text-align: center;
        color: var(--text-muted);
    }

    .quote-pager {
        padding-top: .9rem;
    }

    .quote-filter-actions {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
    }

    @media (max-width: 1100px) {
        .quote-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .quote-filter-grid {
            grid-template-columns: 1fr;
        }

        .quote-filter-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .quote-stats {
            grid-template-columns: 1fr;
        }

        .quote-section-head {
            display: block;
        }
    }
</style>
@endpush
@section('content')
@php
    $cotizacionEstado = \App\Modules\Comercial\Models\Cotizacion::class;
    $money = fn($amount) => '$' . number_format((float) ($amount ?? 0), 0, ',', '.');
    $date = fn($value) => $value ? $value->format('d/m/Y') : '-';
    $estadoTexto = fn($estado) => $cotizacionEstado::etiquetaEstado($estado);
    $estadoBadge = fn($estado) => $cotizacionEstado::badgeEstado($estado);
    $modalidadBadge = fn($cotizacion) => $cotizacion->modalidad?->codigo === 'EST' ? 'badge-info' : 'badge-warning';
    $vigenciaInicio = fn($cotizacion) => $cotizacion->fecha_vigencia ?? $cotizacion->fecha_aprobacion ?? $cotizacion->fecha_vigencia_desde ?? $cotizacion->fecha_cotizacion;
    $vigenciaFinReal = fn($cotizacion) => $cotizacion->fecha_fin_vigencia_real ?? ($cotizacionEstado::normalizarEstado($cotizacion->estado) === 'no_vigente' ? $cotizacion->fecha_cancelacion : null);
    $periodoActivo = function ($cotizacion) use ($date, $vigenciaInicio, $vigenciaFinReal, $cotizacionEstado) {
        $inicio = $date($vigenciaInicio($cotizacion));
        $finReal = $vigenciaFinReal($cotizacion);
        $estadoOperativo = $cotizacionEstado::normalizarEstado($cotizacion->estado);

        if ($finReal) {
            return "{$inicio} - {$date($finReal)}";
        }

        if ($estadoOperativo === 'vigente') {
            return "{$inicio} - activa";
        }

        return "{$inicio} - sin activación";
    };
    $hayFiltros = collect(['cliente_id', 'centro_costo_id', 'cargo', 'estado', 'vigencia_desde', 'vigencia_hasta', 'vence_hasta', 'q'])
        ->contains(fn($campo) => request()->filled($campo));
@endphp

<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Cotizador Comercial</h2>
            <p class="page-subheading">Cotizaciones vigentes, en gestión e histórico por cliente, centro de costo y cargo</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('comercial.cotizaciones.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nueva cotización
            </a>
            <a href="{{ route('comercial.reportes.cotizaciones') }}" class="btn-secondary">
                <i class="bi bi-bar-chart-fill"></i> Reportes
            </a>
        </div>
    </div>

    @include('partials._alerts')

    <div class="quote-workspace">
        <div class="quote-stats">
            <div class="quote-stat">
                <div class="quote-stat-label">Tarifas vigentes</div>
                <div class="quote-stat-value">{{ number_format($resumenEstados['vigentes'], 0, ',', '.') }}</div>
                <div class="quote-stat-note">Actuales para operación comercial</div>
            </div>
            <div class="quote-stat">
                <div class="quote-stat-label">En gestión</div>
                <div class="quote-stat-value">{{ number_format($resumenEstados['gestion'], 0, ',', '.') }}</div>
                <div class="quote-stat-note">Cotizaciones en edición o revisión</div>
            </div>
            <div class="quote-stat">
                <div class="quote-stat-label">Histórico</div>
                <div class="quote-stat-value">{{ number_format($resumenEstados['historicas'], 0, ',', '.') }}</div>
                <div class="quote-stat-note">Cotizaciones no vigentes</div>
            </div>
            <div class="quote-stat">
                <div class="quote-stat-label">Valor vigente</div>
                <div class="quote-stat-value">{{ $money($resumenEstados['precio_vigente']) }}</div>
                <div class="quote-stat-note">Suma de precios activos filtrados</div>
            </div>
            <a class="quote-stat" href="{{ route('comercial.cotizaciones.index', array_merge(request()->except(['gestion_page', 'vigentes_page', 'historicas_page', 'agrupadas_page', 'vence_hasta']), ['estado' => 'en_cotizacion'])) }}">
                <div class="quote-stat-label">Por aprobar</div>
                <div class="quote-stat-value">{{ number_format($resumenEstados['pendientes_aprobar'], 0, ',', '.') }}</div>
                <div class="quote-stat-note">Borradores listos para revisión</div>
            </a>
            <a class="quote-stat" href="{{ route('comercial.cotizaciones.index', array_merge(request()->except(['gestion_page', 'vigentes_page', 'historicas_page', 'agrupadas_page', 'estado', 'vigencia_desde', 'vigencia_hasta']), ['vence_hasta' => now()->addDays(30)->format('Y-m-d')])) }}">
                <div class="quote-stat-label">Por vencer</div>
                <div class="quote-stat-value">{{ number_format($resumenEstados['vigentes_por_vencer'], 0, ',', '.') }}</div>
                <div class="quote-stat-note">Vigentes con término dentro de 30 días</div>
            </a>
        </div>

        <div class="glass-card">
            <form method="GET" action="{{ route('comercial.cotizaciones.index') }}" class="quote-filter-grid">
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-building"></i> Cliente
                    </label>
                    <select name="cliente_id" class="form-control">
                        <option value="">Todos los clientes</option>
                        @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre_comercial ?? $cliente->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-diagram-3"></i> Centro de costo
                    </label>
                    <select name="centro_costo_id" class="form-control">
                        <option value="">Todos los centros</option>
                        @foreach($centrosCosto as $centro)
                        <option value="{{ $centro->id }}" {{ request('centro_costo_id') == $centro->id ? 'selected' : '' }}>
                            {{ $centro->nombre }}{{ $centro->codigo ? ' · '.$centro->codigo : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-person-badge"></i> Cargo
                    </label>
                    <input type="search" name="cargo" value="{{ request('cargo') }}" class="form-control" placeholder="Cargo específico">
                </div>
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-activity"></i> Estado
                    </label>
                    <select name="estado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="vigente" {{ request('estado') === 'vigente' ? 'selected' : '' }}>Vigente</option>
                        <option value="no_vigente" {{ request('estado') === 'no_vigente' ? 'selected' : '' }}>No vigente</option>
                        <option value="gestion" {{ request('estado') === 'gestion' ? 'selected' : '' }}>En gestión</option>
                        <option value="historico" {{ request('estado') === 'historico' ? 'selected' : '' }}>Histórico completo</option>
                        <option value="en_cotizacion" {{ request('estado') === 'en_cotizacion' ? 'selected' : '' }}>En cotización</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-calendar-check"></i> Activa desde
                    </label>
                    <input type="date" name="vigencia_desde" value="{{ request('vigencia_desde') }}" class="form-control">
                </div>
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-calendar-x"></i> Activa hasta
                    </label>
                    <input type="date" name="vigencia_hasta" value="{{ request('vigencia_hasta') }}" class="form-control">
                </div>
                <div>
                    <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:.35rem">
                        <i class="bi bi-search"></i> Buscar
                    </label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Número, cargo, RUT, cliente o centro de costo">
                </div>
                <div class="quote-filter-actions">
                    <button type="submit" class="btn-premium">
                        <i class="bi bi-funnel-fill"></i> Filtrar
                    </button>
                    @if($hayFiltros)
                    <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="glass-card">
            <div class="quote-section-head">
                <div>
                    <h3 class="quote-section-title"><i class="bi bi-grid-3x3-gap-fill"></i> Vista agrupada por cliente, CC y cargo</h3>
                    <p class="quote-section-copy">Agrupa todas las cotizaciones equivalentes para comparar tarifa vigente, cantidad de históricos y última actividad.</p>
                </div>
                <span class="badge badge-info">{{ $agrupadas->total() }} grupo(s)</span>
            </div>

            <div class="quote-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Centro de costo</th>
                            <th>Cargo</th>
                            <th>Modalidad</th>
                            <th>Tarifa vigente</th>
                            <th>Vigentes</th>
                            <th>Históricas</th>
                            <th>Total</th>
                            <th>Última actividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agrupadas as $grupo)
                        <tr>
                            <td class="quote-main">
                                <strong>{{ $grupo->cliente?->nombre_comercial ?? $grupo->cliente?->nombre ?? '-' }}</strong>
                                <span>{{ $grupo->cliente?->rut ?? '' }}</span>
                            </td>
                            <td class="quote-main">
                                <strong>{{ $grupo->centroCosto?->nombre ?? '-' }}</strong>
                                <span>{{ $grupo->centroCosto?->codigo ?? '' }}</span>
                            </td>
                            <td class="quote-main"><strong>{{ $grupo->cargo ?: '-' }}</strong></td>
                            <td><span class="badge {{ $modalidadBadge($grupo) }}">{{ $grupo->modalidad?->codigo ?? '-' }}</span></td>
                            <td class="quote-price">{{ $grupo->precio_vigente ? $money($grupo->precio_vigente) : '-' }}</td>
                            <td>{{ number_format((int) $grupo->total_vigentes, 0, ',', '.') }}</td>
                            <td>{{ number_format((int) $grupo->total_historicas, 0, ',', '.') }}</td>
                            <td>{{ number_format((int) $grupo->total_cotizaciones, 0, ',', '.') }}</td>
                            <td class="quote-muted">{{ $grupo->ultima_actividad ? \Carbon\Carbon::parse($grupo->ultima_actividad)->format('d/m/Y') : '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="quote-empty">No hay grupos comerciales para los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($agrupadas->hasPages())
            <div class="quote-pager">{{ $agrupadas->links() }}</div>
            @endif
        </div>

        <div class="glass-card">
            <div class="quote-section-head">
                <div>
                    <h3 class="quote-section-title"><i class="bi bi-check-circle-fill"></i> Cotizaciones vigentes</h3>
                    <p class="quote-section-copy">Estas son las tarifas activas. Al hacer vigente una nueva cotización equivalente, la anterior pasa al histórico como no vigente.</p>
                </div>
                <span class="badge badge-success">{{ $vigentes->total() }} vigentes</span>
            </div>

            <div class="quote-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente / Centro</th>
                            <th>Cargo</th>
                            <th>Modalidad</th>
                            <th>Precio venta</th>
                            <th>Vigencia</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vigentes as $cotizacion)
                        <tr>
                            <td><code style="font-size:.82rem">{{ $cotizacion->numero }}</code></td>
                            <td class="quote-main">
                                <strong>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</strong>
                                <span>{{ $cotizacion->centroCosto->nombre }}{{ $cotizacion->centroCosto->codigo ? ' · '.$cotizacion->centroCosto->codigo : '' }}</span>
                            </td>
                            <td class="quote-main">
                                <strong>{{ $cotizacion->cargo }}</strong>
                                @if($cotizacion->titulo)<span>{{ $cotizacion->titulo }}</span>@endif
                            </td>
                            <td><span class="badge {{ $modalidadBadge($cotizacion) }}">{{ $cotizacion->modalidad->codigo }}</span></td>
                            <td class="quote-price">{{ $money($cotizacion->precio_venta) }}</td>
                            <td class="quote-muted">
                                {{ $periodoActivo($cotizacion) }}
                            </td>
                            <td>
                                <div class="quote-actions">
                                    <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="icon-btn" title="Ver detalles"><i class="bi bi-eye-fill"></i></a>
                                    <a href="{{ route('comercial.cotizaciones.pdf', $cotizacion) }}" class="icon-btn" title="Descargar PDF final" target="_blank"><i class="bi bi-file-pdf-fill"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="quote-empty">No hay cotizaciones vigentes para los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($vigentes->hasPages())
            <div class="quote-pager">{{ $vigentes->links() }}</div>
            @endif
        </div>

        <div class="glass-card">
            <div class="quote-section-head">
                <div>
                    <h3 class="quote-section-title"><i class="bi bi-pencil-square"></i> Cotizaciones en gestión</h3>
                    <p class="quote-section-copy">Trabajo comercial en curso: cotizaciones en edición o listas para aprobar y dejar vigentes.</p>
                </div>
                <span class="badge badge-warning">{{ $enGestion->total() }} en gestión</span>
            </div>

            <div class="quote-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente / Centro</th>
                            <th>Cargo</th>
                            <th>Estado</th>
                            <th>Precio venta</th>
                            <th>Periodo activo</th>
                            <th>Responsable / actualización</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enGestion as $cotizacion)
                        <tr>
                            <td><code style="font-size:.82rem">{{ $cotizacion->numero }}</code></td>
                            <td class="quote-main">
                                <strong>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</strong>
                                <span>{{ $cotizacion->centroCosto->nombre }}</span>
                            </td>
                            <td class="quote-main">
                                <strong>{{ $cotizacion->cargo }}</strong>
                                @if($cotizacion->titulo)<span>{{ $cotizacion->titulo }}</span>@endif
                            </td>
                            <td><span class="badge {{ $estadoBadge($cotizacion->estado) }}">{{ $estadoTexto($cotizacion->estado) }}</span></td>
                            <td class="quote-price">{{ $money($cotizacion->precio_venta) }}</td>
                            <td class="quote-muted">{{ $periodoActivo($cotizacion) }}</td>
                            <td class="quote-muted">
                                {{ $cotizacion->usuario?->name ?? 'Sistema' }}
                                <br>
                                {{ $cotizacion->updated_at?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                            <td>
                                <div class="quote-actions">
                                    <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="icon-btn" title="Ver detalles"><i class="bi bi-eye-fill"></i></a>
                                    @if(auth()->user()->tieneAcceso('comercial', 'puede_crear'))
                                    <form method="POST" action="{{ route('comercial.cotizaciones.duplicar', $cotizacion) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="icon-btn" title="Duplicar como borrador" onclick="return confirm('¿Duplicar esta cotización como nuevo borrador?')">
                                            <i class="bi bi-files"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @if($cotizacion->estado === 'en_cotizacion')
                                    <a href="{{ route('comercial.cotizaciones.edit', $cotizacion) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                    @endif
                                    <a href="{{ route('comercial.cotizaciones.pdf', $cotizacion) }}" class="icon-btn" title="Descargar PDF" target="_blank"><i class="bi bi-file-pdf-fill"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="quote-empty">No hay cotizaciones en gestión para los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($enGestion->hasPages())
            <div class="quote-pager">{{ $enGestion->links() }}</div>
            @endif
        </div>

        <div class="glass-card">
            <div class="quote-section-head">
                <div>
                    <h3 class="quote-section-title"><i class="bi bi-archive-fill"></i> Histórico comercial</h3>
                    <p class="quote-section-copy">Cotizaciones no vigentes. Permanecen disponibles para trazabilidad y auditoría.</p>
                </div>
                <span class="badge badge-secondary">{{ $historicas->total() }} históricas</span>
            </div>

            <div class="quote-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente / Centro</th>
                            <th>Cargo</th>
                            <th>Estado</th>
                            <th>Precio venta</th>
                            <th>Periodo activo</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historicas as $cotizacion)
                        <tr>
                            <td><code style="font-size:.82rem">{{ $cotizacion->numero }}</code></td>
                            <td class="quote-main">
                                <strong>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</strong>
                                <span>{{ $cotizacion->centroCosto->nombre }}</span>
                            </td>
                            <td class="quote-main">
                                <strong>{{ $cotizacion->cargo }}</strong>
                                @if($cotizacion->titulo)<span>{{ $cotizacion->titulo }}</span>@endif
                            </td>
                            <td><span class="badge {{ $estadoBadge($cotizacion->estado) }}">{{ $estadoTexto($cotizacion->estado) }}</span></td>
                            <td class="quote-price">{{ $money($cotizacion->precio_venta) }}</td>
                            <td class="quote-muted">{{ $periodoActivo($cotizacion) }}</td>
                            <td>
                                <div class="quote-actions">
                                    <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="icon-btn" title="Ver histórico"><i class="bi bi-eye-fill"></i></a>
                                    <a href="{{ route('comercial.cotizaciones.pdf', $cotizacion) }}" class="icon-btn" title="Descargar PDF guardado" target="_blank"><i class="bi bi-file-pdf-fill"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="quote-empty">No hay histórico para los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($historicas->hasPages())
            <div class="quote-pager">{{ $historicas->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
