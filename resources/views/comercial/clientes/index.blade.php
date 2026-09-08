@extends('layouts.app')
@section('title', 'Clientes y CC - Módulo Comercial')
@section('content')
@php
    $puedeCrear = auth()->user()->tieneAcceso('comercial', 'puede_crear');
    $filtroTexto = request('q', '');
    $filtroEstado = request('estado', '');
    $tabActivo = in_array(request('tab'), ['clientes', 'centros'], true) ? request('tab') : 'clientes';
    $filtroClienteId = request('cliente_id');
    $queryBase = request()->except(['tab', 'page', 'centros_page']);
    $queryClientes = request()->except(['tab', 'page', 'centros_page', 'cliente_id']);
@endphp

<style>
    .comercial-directory {
        --panel-border: rgba(148, 163, 184, .22);
        --soft-bg: rgba(248, 250, 252, .82);
    }

    .directory-actions,
    .directory-filter,
    .quick-form,
    .inline-actions,
    .tag-list {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .directory-actions,
    .directory-filter,
    .quick-form {
        align-items: flex-end;
    }

    .directory-filter {
        margin-bottom: 1rem;
    }

    .directory-kpis {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        margin-bottom: 1rem;
    }

    .directory-kpi {
        background: #fff;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
    }

    .directory-kpi small {
        color: var(--text-muted);
        display: block;
        font-weight: 600;
        margin-bottom: .4rem;
    }

    .directory-kpi strong {
        color: var(--text-primary);
        display: block;
        font-size: 1.65rem;
        line-height: 1;
    }

    .directory-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        margin-bottom: 1rem;
    }

    .section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .section-heading h3 {
        color: var(--text-primary);
        font-size: 1rem;
        font-weight: 800;
        margin: 0;
    }

    .section-heading span {
        color: var(--text-muted);
        font-size: .82rem;
    }

    .quick-form .form-group {
        flex: 1 1 180px;
        margin-bottom: 0;
        min-width: 170px;
    }

    .quick-form .form-group.wide {
        flex-basis: 260px;
    }

    .directory-table-card {
        margin-bottom: 1rem;
    }

    .muted-line {
        color: var(--text-muted);
        display: block;
        font-size: .8rem;
        margin-top: .2rem;
    }

    .cc-chip {
        align-items: center;
        background: var(--soft-bg);
        border: 1px solid var(--panel-border);
        border-radius: 999px;
        color: var(--text-primary);
        display: inline-flex;
        font-size: .82rem;
        gap: .35rem;
        padding: .3rem .55rem;
        text-decoration: none;
    }

    .cc-chip:hover {
        border-color: rgba(37, 99, 235, .35);
        color: #1d4ed8;
    }

    .table-empty {
        color: var(--text-muted);
        padding: 2rem;
        text-align: center;
    }

    .directory-overview {
        display: flex;
        align-items: stretch;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .directory-metric {
        min-width: 188px;
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .72rem .85rem;
        background: var(--surface-color, #fff);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
    }

    .directory-metric i {
        display: inline-grid;
        width: 2rem;
        height: 2rem;
        place-items: center;
        color: var(--accent-primary);
        background: color-mix(in srgb, var(--accent-primary) 10%, transparent);
        border-radius: 7px;
    }

    .directory-metric span,
    .directory-metric strong {
        display: block;
    }

    .directory-metric span {
        color: var(--text-muted);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .directory-metric strong {
        margin-top: .08rem;
        color: var(--text-primary);
        font-size: 1rem;
    }

    .directory-tabs {
        display: flex;
        gap: .25rem;
        width: fit-content;
        max-width: 100%;
        margin-bottom: 1rem;
        padding: .28rem;
        overflow-x: auto;
        background: var(--bg-tertiary, #f8fafc);
        border: 1px solid var(--panel-border);
        border-radius: 9px;
    }

    .directory-tab {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        min-height: 2.35rem;
        padding: .45rem .8rem;
        color: var(--text-muted);
        border-radius: 6px;
        font-size: .86rem;
        font-weight: 750;
        text-decoration: none;
        white-space: nowrap;
    }

    .directory-tab:hover {
        color: var(--text-primary);
        background: color-mix(in srgb, var(--accent-primary) 8%, transparent);
    }

    .directory-tab.is-active {
        color: #fff;
        background: var(--accent-primary);
        box-shadow: 0 4px 10px color-mix(in srgb, var(--accent-primary) 28%, transparent);
    }

    .directory-tab-count {
        padding: .08rem .42rem;
        color: inherit;
        background: color-mix(in srgb, currentColor 14%, transparent);
        border-radius: 999px;
        font-size: .72rem;
        font-variant-numeric: tabular-nums;
    }

    .directory-filter {
        display: grid;
        grid-template-columns: minmax(260px, 2fr) minmax(170px, .8fr) auto auto;
        gap: .75rem;
        align-items: end;
    }

    .directory-filter .form-group {
        margin-bottom: 0;
    }

    .directory-filter .form-group.client-filter {
        display: none;
    }

    .directory-filter.is-centros .form-group.client-filter {
        display: block;
    }

    .directory-table-card {
        overflow: hidden;
    }

    .directory-table-card .section-heading {
        margin-bottom: .25rem;
    }

    .directory-table-note {
        margin: 0 0 1rem;
        color: var(--text-muted);
        font-size: .82rem;
    }

    .directory-table {
        min-width: 720px;
    }

    .directory-table th {
        padding-top: .72rem;
        padding-bottom: .72rem;
        color: var(--text-muted);
        font-size: .69rem;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .directory-table td {
        padding-top: .82rem;
        padding-bottom: .82rem;
        vertical-align: middle;
    }

    .directory-table tbody tr:hover {
        background: color-mix(in srgb, var(--accent-primary) 4%, transparent);
    }

    .directory-record-name {
        color: var(--text-primary);
        font-weight: 750;
    }

    .directory-record-meta {
        display: block;
        margin-top: .18rem;
        color: var(--text-muted);
        font-size: .79rem;
    }

    .directory-count {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .28rem .5rem;
        color: var(--accent-primary);
        background: color-mix(in srgb, var(--accent-primary) 9%, transparent);
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 750;
        text-decoration: none;
    }

    .directory-row-actions {
        display: flex;
        align-items: center;
        gap: .25rem;
        white-space: nowrap;
    }

    .directory-text-action {
        padding: .3rem .45rem;
        color: var(--accent-primary);
        border-radius: 5px;
        font-size: .78rem;
        font-weight: 700;
        text-decoration: none;
    }

    .directory-text-action:hover {
        background: color-mix(in srgb, var(--accent-primary) 10%, transparent);
    }

    .directory-add-menu {
        position: relative;
    }

    .directory-add-menu summary {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        cursor: pointer;
        list-style: none;
    }

    .directory-add-menu summary::-webkit-details-marker {
        display: none;
    }

    .directory-add-menu-panel {
        position: absolute;
        z-index: 50;
        top: calc(100% + .4rem);
        right: 0;
        display: grid;
        min-width: 220px;
        padding: .35rem;
        background: var(--surface-color, #fff);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, .16);
    }

    .directory-add-menu-panel button {
        display: flex;
        align-items: center;
        gap: .55rem;
        width: 100%;
        padding: .6rem .65rem;
        color: var(--text-primary);
        background: transparent;
        border: 0;
        border-radius: 6px;
        font: inherit;
        font-size: .84rem;
        text-align: left;
    }

    .directory-add-menu-panel button:hover {
        background: color-mix(in srgb, var(--accent-primary) 10%, transparent);
    }

    .directory-dialog {
        width: min(100% - 2rem, 680px);
        padding: 0;
        color: var(--text-primary);
        background: var(--surface-color, #fff);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .28);
    }

    .directory-dialog::backdrop {
        background: rgba(15, 23, 42, .46);
        backdrop-filter: blur(2px);
    }

    .directory-dialog-header,
    .directory-dialog-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
    }

    .directory-dialog-header {
        border-bottom: 1px solid var(--panel-border);
    }

    .directory-dialog-header h3 {
        margin: 0;
        font-size: 1rem;
    }

    .directory-dialog-header p {
        margin: .16rem 0 0;
        color: var(--text-muted);
        font-size: .81rem;
    }

    .directory-dialog-body {
        padding: 1.15rem;
    }

    .directory-dialog-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }

    .directory-dialog-form .form-group {
        margin: 0;
    }

    .directory-dialog-form .is-wide {
        grid-column: 1 / -1;
    }

    .directory-dialog-footer {
        justify-content: flex-end;
        padding: 1rem 0 0;
    }

    .directory-import-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }

    .directory-import-card {
        padding: .9rem;
        background: var(--bg-tertiary, #f8fafc);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
    }

    .directory-import-card h4 {
        margin: 0;
        font-size: .9rem;
    }

    .directory-import-card p {
        min-height: 2.4em;
        margin: .3rem 0 .8rem;
        color: var(--text-muted);
        font-size: .78rem;
        line-height: 1.4;
    }

    .directory-import-card .form-group {
        margin-bottom: .75rem;
    }

    @media (max-width: 768px) {
        .directory-actions,
        .directory-filter,
        .quick-form {
            align-items: stretch;
            flex-direction: column;
        }

        .directory-actions .btn-secondary,
        .directory-actions .btn-premium,
        .directory-filter .btn-secondary,
        .directory-filter .btn-premium,
        .quick-form .btn-premium,
        .quick-form .btn-secondary {
            justify-content: center;
            width: 100%;
        }

        .directory-filter {
            grid-template-columns: 1fr;
        }

        .directory-tabs {
            width: 100%;
        }

        .directory-tab {
            flex: 1;
            justify-content: center;
        }

        .directory-import-grid,
        .directory-dialog-form {
            grid-template-columns: 1fr;
        }

        .directory-dialog-form .is-wide {
            grid-column: auto;
        }

        .directory-dialog-header,
        .directory-dialog-footer {
            padding-right: .9rem;
            padding-left: .9rem;
        }

        .directory-dialog-body {
            padding: .9rem;
        }
    }
</style>

<div class="page-container comercial-directory">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Clientes y Centros de Costo</h2>
            <p class="page-subheading">Directorio comercial para cotizaciones, vigencias y tarifas</p>
        </div>
        @if($puedeCrear)
        <div class="directory-actions">
            <details class="directory-add-menu">
                <summary class="btn-premium">
                    <i class="bi bi-plus-lg"></i> Agregar <i class="bi bi-chevron-down" style="font-size:.7rem"></i>
                </summary>
                <div class="directory-add-menu-panel">
                    <button type="button" onclick="abrirDialogoMaestro('dialogCliente')">
                        <i class="bi bi-building-add"></i> Nuevo cliente
                    </button>
                    <button type="button" onclick="abrirDialogoMaestro('dialogCentro')">
                        <i class="bi bi-diagram-3"></i> Nuevo centro de costo
                    </button>
                </div>
            </details>
            <button type="button" class="btn-secondary" onclick="abrirDialogoMaestro('dialogImportar')">
                <i class="bi bi-upload"></i> Importar
            </button>
        </div>
        @endif
    </div>

    @include('partials._alerts')

    <div class="directory-overview" aria-label="Resumen de maestros comerciales">
        <div class="directory-metric">
            <i class="bi bi-buildings"></i>
            <div>
                <span>Clientes activos</span>
                <strong>{{ number_format($resumen['clientes_activos']) }} de {{ number_format($resumen['clientes']) }}</strong>
            </div>
        </div>
        <div class="directory-metric">
            <i class="bi bi-diagram-3"></i>
            <div>
                <span>Centros activos</span>
                <strong>{{ number_format($resumen['centros_activos']) }} de {{ number_format($resumen['centros']) }}</strong>
            </div>
        </div>
        @if($resumen['clientes'] > $resumen['clientes_activos'] || $resumen['centros'] > $resumen['centros_activos'])
        <div class="directory-metric">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <span>Maestros inactivos</span>
                <strong>{{ number_format(($resumen['clientes'] - $resumen['clientes_activos']) + ($resumen['centros'] - $resumen['centros_activos'])) }}</strong>
            </div>
        </div>
        @endif
    </div>

    <nav class="directory-tabs" aria-label="Tipo de maestro comercial">
        <a href="{{ route('comercial.clientes.index', array_merge($queryClientes, ['tab' => 'clientes'])) }}" class="directory-tab {{ $tabActivo === 'clientes' ? 'is-active' : '' }}">
            <i class="bi bi-buildings"></i> Clientes <span class="directory-tab-count">{{ $clientes->total() }}</span>
        </a>
        <a href="{{ route('comercial.clientes.index', array_merge($queryBase, ['tab' => 'centros'])) }}" class="directory-tab {{ $tabActivo === 'centros' ? 'is-active' : '' }}">
            <i class="bi bi-diagram-3"></i> Centros de costo <span class="directory-tab-count">{{ $centrosCosto->total() }}</span>
        </a>
    </nav>

    <form method="GET" action="{{ route('comercial.clientes.index') }}" class="glass-card directory-filter {{ $tabActivo === 'centros' ? 'is-centros' : '' }}">
        <input type="hidden" name="tab" value="{{ $tabActivo }}">
        <div class="form-group">
            <label>Buscar</label>
            <input type="search" name="q" value="{{ $filtroTexto }}" class="form-control" placeholder="{{ $tabActivo === 'clientes' ? 'Nombre, RUT, correo o centro asociado' : 'Centro, código o cliente' }}">
        </div>
        <div class="form-group client-filter">
            <label>Cliente</label>
            <select name="cliente_id" class="form-control">
                <option value="">Todos los clientes</option>
                @foreach($clientesSelect as $clienteOption)
                    <option value="{{ $clienteOption->id }}" @selected((string) $filtroClienteId === (string) $clienteOption->id)>
                        {{ $clienteOption->nombre_comercial ?: $clienteOption->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Estado</label>
            <select name="estado" class="form-control">
                <option value="">Todos</option>
                <option value="activo" @selected($filtroEstado === 'activo')>Activos</option>
                <option value="inactivo" @selected($filtroEstado === 'inactivo')>Inactivos</option>
            </select>
        </div>
        <button type="submit" class="btn-premium">
            <i class="bi bi-search"></i> Filtrar
        </button>
        @if($filtroTexto || $filtroEstado || $filtroClienteId)
        <a href="{{ route('comercial.clientes.index', ['tab' => $tabActivo]) }}" class="btn-secondary">
            <i class="bi bi-x-lg"></i> Limpiar
        </a>
        @endif
    </form>

    @if($tabActivo === 'clientes')
    <section class="glass-card directory-table-card">
        <div class="section-heading">
            <h3>Directorio de clientes</h3>
            <span>{{ $clientes->total() }} registros</span>
        </div>
        <p class="directory-table-note">Consulta los datos principales y abre el detalle cuando necesites ver sus centros de costo o historial de cotizaciones.</p>
        <div style="overflow-x:auto">
            <table class="data-table directory-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Identificación y contacto</th>
                        <th>Centros asociados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                    <tr>
                        <td>
                            <span class="directory-record-name">{{ $cliente->nombre_comercial ?: $cliente->nombre }}</span>
                            @if($cliente->nombre_comercial && $cliente->nombre_comercial !== $cliente->nombre)
                                <span class="directory-record-meta">{{ $cliente->nombre }}</span>
                            @endif
                        </td>
                        <td>
                            <span>{{ $cliente->rut ?: 'RUT no informado' }}</span>
                            <span class="directory-record-meta">{{ $cliente->email ?: 'Correo no informado' }}</span>
                        </td>
                        <td>
                            <a class="directory-count" href="{{ route('comercial.clientes.index', ['tab' => 'centros', 'cliente_id' => $cliente->id]) }}">
                                <i class="bi bi-diagram-3"></i> {{ $cliente->centros_costo_count }} {{ $cliente->centros_costo_count === 1 ? 'centro' : 'centros' }}
                            </a>
                        </td>
                        <td><span class="badge {{ $cliente->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($cliente->estado) }}</span></td>
                        <td>
                            <div class="directory-row-actions">
                                <a href="{{ route('comercial.clientes.show', $cliente) }}" class="directory-text-action">Ver</a>
                                <a href="{{ route('comercial.clientes.edit', $cliente) }}" class="directory-text-action">Editar</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="table-empty">No hay clientes para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($clientes->hasPages())
        <div style="padding:1.25rem;text-align:center">{{ $clientes->links() }}</div>
        @endif
    </section>
    @else
    <section class="glass-card directory-table-card" id="centros">
        <div class="section-heading">
            <h3>Directorio de centros de costo</h3>
            <span>{{ $centrosCosto->total() }} registros</span>
        </div>
        <p class="directory-table-note">Cada centro se muestra una sola vez, con su cliente, código y estado para facilitar la búsqueda operativa.</p>
        <div style="overflow-x:auto">
            <table class="data-table directory-table">
                <thead>
                    <tr>
                        <th>Centro de costo</th>
                        <th>Cliente</th>
                        <th>Código</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centrosCosto as $centro)
                    <tr>
                        <td><span class="directory-record-name">{{ $centro->nombre }}</span></td>
                        <td>
                            <span>{{ $centro->cliente?->nombre_comercial ?: $centro->cliente?->nombre ?: 'Sin cliente' }}</span>
                            @if($centro->cliente?->rut)<span class="directory-record-meta">{{ $centro->cliente->rut }}</span>@endif
                        </td>
                        <td>{{ $centro->codigo ?: 'Sin código' }}</td>
                        <td>{{ $centro->ubicacion ?: 'No informada' }}</td>
                        <td><span class="badge {{ $centro->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($centro->estado) }}</span></td>
                        <td>
                            <div class="directory-row-actions">
                                <a href="{{ route('comercial.centros-costo.show', $centro) }}" class="directory-text-action">Ver</a>
                                <a href="{{ route('comercial.centros-costo.edit', $centro) }}" class="directory-text-action">Editar</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="table-empty">No hay centros de costo para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($centrosCosto->hasPages())
        <div style="padding:1.25rem;text-align:center">{{ $centrosCosto->links() }}</div>
        @endif
    </section>
    @endif

    @if($puedeCrear)
    <dialog id="dialogCliente" class="directory-dialog" aria-labelledby="dialogClienteTitulo">
        <div class="directory-dialog-header">
            <div>
                <h3 id="dialogClienteTitulo">Nuevo cliente</h3>
                <p>Registra los datos mínimos para usarlo en una cotización.</p>
            </div>
            <button type="button" class="icon-btn" onclick="cerrarDialogoMaestro('dialogCliente')" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="directory-dialog-body">
            <form method="POST" action="{{ route('comercial.clientes.store') }}" class="directory-dialog-form">
                @csrf
                <input type="hidden" name="estado" value="activo">
                <div class="form-group is-wide">
                    <label>Cliente <span class="required">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                </div>
                <div class="form-group">
                    <label>RUT</label>
                    <input type="text" name="rut" class="form-control" value="{{ old('rut') }}" placeholder="Opcional">
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="Opcional">
                </div>
                <div class="directory-dialog-footer is-wide">
                    <button type="button" class="btn-secondary" onclick="cerrarDialogoMaestro('dialogCliente')">Cancelar</button>
                    <button type="submit" class="btn-premium"><i class="bi bi-save"></i> Crear cliente</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="dialogCentro" class="directory-dialog" aria-labelledby="dialogCentroTitulo">
        <div class="directory-dialog-header">
            <div>
                <h3 id="dialogCentroTitulo">Nuevo centro de costo</h3>
                <p>El centro quedará asociado al cliente que indiques.</p>
            </div>
            <button type="button" class="icon-btn" onclick="cerrarDialogoMaestro('dialogCentro')" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="directory-dialog-body">
            <form method="POST" action="{{ route('comercial.centros-costo.store') }}" class="directory-dialog-form">
                @csrf
                <input type="hidden" name="estado" value="activo">
                <div class="form-group is-wide">
                    <label>Cliente <span class="required">*</span></label>
                    <select name="cliente_id" class="form-control" required {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                        <option value="">Seleccionar cliente</option>
                        @foreach($clientesSelect as $clienteOption)
                            <option value="{{ $clienteOption->id }}" @selected((string) old('cliente_id') === (string) $clienteOption->id)>
                                {{ $clienteOption->nombre_comercial ?: $clienteOption->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de costo <span class="required">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                </div>
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" name="codigo" class="form-control" value="{{ old('codigo') }}" placeholder="Opcional" {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                </div>
                <div class="directory-dialog-footer is-wide">
                    <button type="button" class="btn-secondary" onclick="cerrarDialogoMaestro('dialogCentro')">Cancelar</button>
                    <button type="submit" class="btn-premium" {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}><i class="bi bi-save"></i> Crear centro</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="dialogImportar" class="directory-dialog" aria-labelledby="dialogImportarTitulo">
        <div class="directory-dialog-header">
            <div>
                <h3 id="dialogImportarTitulo">Importar maestros</h3>
                <p>Usa la alternativa que corresponda a la estructura de tu archivo CSV.</p>
            </div>
            <button type="button" class="icon-btn" onclick="cerrarDialogoMaestro('dialogImportar')" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="directory-dialog-body">
            <div class="directory-import-grid">
                <section class="directory-import-card">
                    <h4>Clientes y centros</h4>
                    <p>Crea clientes que no existan e incorpora su centro cuando la fila lo incluya.</p>
                    <a href="{{ route('comercial.clientes.importar.plantilla') }}" class="directory-text-action"><i class="bi bi-download"></i> Descargar plantilla</a>
                    <form method="POST" action="{{ route('comercial.clientes.importar') }}" enctype="multipart/form-data" style="margin-top:.7rem">
                        @csrf
                        <div class="form-group">
                            <label>Archivo CSV</label>
                            <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt" required>
                        </div>
                        <button type="submit" class="btn-secondary"><i class="bi bi-upload"></i> Importar</button>
                    </form>
                </section>
                <section class="directory-import-card">
                    <h4>Centros por cliente</h4>
                    <p>Úsalo cuando cada fila ya trae el cliente y el centro de costo que se debe asociar.</p>
                    <a href="{{ route('comercial.centros-costo.importar.plantilla') }}" class="directory-text-action"><i class="bi bi-download"></i> Descargar plantilla</a>
                    <form method="POST" action="{{ route('comercial.centros-costo.importar') }}" enctype="multipart/form-data" style="margin-top:.7rem">
                        @csrf
                        <div class="form-group">
                            <label>Archivo CSV</label>
                            <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt" required>
                        </div>
                        <button type="submit" class="btn-secondary"><i class="bi bi-upload"></i> Importar</button>
                    </form>
                </section>
            </div>
            <div class="directory-dialog-footer">
                <button type="button" class="btn-secondary" onclick="cerrarDialogoMaestro('dialogImportar')">Cerrar</button>
            </div>
        </div>
    </dialog>
    @endif
</div>

<script>
function abrirDialogoMaestro(id) {
    document.querySelectorAll('.directory-add-menu[open]').forEach((menu) => { menu.open = false; });
    const dialogo = document.getElementById(id);
    if (dialogo && !dialogo.open) dialogo.showModal();
}

function cerrarDialogoMaestro(id) {
    const dialogo = document.getElementById(id);
    if (dialogo?.open) dialogo.close();
}

document.querySelectorAll('.directory-dialog').forEach((dialogo) => {
    dialogo.addEventListener('click', (event) => {
        if (event.target === dialogo) dialogo.close();
    });
});
</script>
@endsection
