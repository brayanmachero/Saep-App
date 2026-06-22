@extends('layouts.app')
@section('title', 'Clientes y CC - Módulo Comercial')
@section('content')
@php
    $puedeCrear = auth()->user()->tieneAcceso('comercial', 'puede_crear');
    $filtroTexto = request('q', '');
    $filtroEstado = request('estado', '');
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
            <a href="#alta-cliente" class="btn-secondary">
                <i class="bi bi-building-add"></i> Nuevo cliente
            </a>
            <a href="#alta-centro" class="btn-premium">
                <i class="bi bi-diagram-3"></i> Nuevo centro
            </a>
        </div>
        @endif
    </div>

    @include('partials._alerts')

    <form method="GET" action="{{ route('comercial.clientes.index') }}" class="glass-card directory-filter">
        <div class="form-group" style="flex:2 1 300px;margin-bottom:0">
            <label>Buscar</label>
            <input type="search" name="q" value="{{ $filtroTexto }}" class="form-control" placeholder="Cliente, RUT, centro o código">
        </div>
        <div class="form-group" style="flex:1 1 180px;margin-bottom:0">
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
        @if($filtroTexto || $filtroEstado)
        <a href="{{ route('comercial.clientes.index') }}" class="btn-secondary">
            <i class="bi bi-x-lg"></i> Limpiar
        </a>
        @endif
    </form>

    <div class="directory-kpis">
        <div class="directory-kpi">
            <small>Clientes</small>
            <strong>{{ number_format($resumen['clientes']) }}</strong>
        </div>
        <div class="directory-kpi">
            <small>Clientes activos</small>
            <strong>{{ number_format($resumen['clientes_activos']) }}</strong>
        </div>
        <div class="directory-kpi">
            <small>Centros de costo</small>
            <strong>{{ number_format($resumen['centros']) }}</strong>
        </div>
        <div class="directory-kpi">
            <small>Centros activos</small>
            <strong>{{ number_format($resumen['centros_activos']) }}</strong>
        </div>
    </div>

    @if($puedeCrear)
    <div class="directory-grid">
        <section class="glass-card" id="alta-cliente">
            <div class="section-heading">
                <h3>Alta rápida de cliente</h3>
                <span>Datos mínimos</span>
            </div>
            <form method="POST" action="{{ route('comercial.clientes.store') }}" class="quick-form">
                @csrf
                <input type="hidden" name="estado" value="activo">
                <div class="form-group wide">
                    <label>Cliente</label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                </div>
                <div class="form-group">
                    <label>RUT</label>
                    <input type="text" name="rut" class="form-control" value="{{ old('rut') }}">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                </div>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-save"></i> Guardar
                </button>
            </form>
        </section>

        <section class="glass-card" id="alta-centro">
            <div class="section-heading">
                <h3>Alta rápida de centro</h3>
                <span>Asociado a cliente</span>
            </div>
            <form method="POST" action="{{ route('comercial.centros-costo.store') }}" class="quick-form">
                @csrf
                <input type="hidden" name="estado" value="activo">
                <div class="form-group wide">
                    <label>Cliente</label>
                    <select name="cliente_id" class="form-control" required {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                        <option value="">Seleccionar</option>
                        @foreach($clientesSelect as $clienteOption)
                            <option value="{{ $clienteOption->id }}" @selected((string) old('cliente_id') === (string) $clienteOption->id)>
                                {{ $clienteOption->nombre_comercial ?: $clienteOption->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de costo</label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                </div>
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" name="codigo" class="form-control" value="{{ old('codigo') }}" {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                </div>
                <button type="submit" class="btn-premium" {{ $clientesSelect->isEmpty() ? 'disabled' : '' }}>
                    <i class="bi bi-save"></i> Guardar
                </button>
            </form>
        </section>
    </div>

    <div class="directory-grid">
        <section class="glass-card">
            <div class="section-heading">
                <h3>Importar clientes + centros</h3>
                <span>CSV o TXT</span>
            </div>
            <form method="POST" action="{{ route('comercial.clientes.importar') }}" enctype="multipart/form-data" class="quick-form">
                @csrf
                <div class="form-group wide">
                    <label>Archivo</label>
                    <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt">
                    @error('archivo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn-secondary">
                    <i class="bi bi-upload"></i> Importar
                </button>
            </form>
        </section>

        <section class="glass-card">
            <div class="section-heading">
                <h3>Importar centros por cliente</h3>
                <span>CSV o TXT</span>
            </div>
            <form method="POST" action="{{ route('comercial.centros-costo.importar') }}" enctype="multipart/form-data" class="quick-form">
                @csrf
                <div class="form-group wide">
                    <label>Archivo</label>
                    <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt">
                    @error('archivo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn-secondary">
                    <i class="bi bi-upload"></i> Importar
                </button>
            </form>
        </section>
    </div>
    @endif

    <section class="glass-card directory-table-card">
        <div class="section-heading">
            <h3>Clientes con centros asociados</h3>
            <span>{{ $clientes->total() }} registros</span>
        </div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Centros de costo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                    <tr>
                        <td>
                            <strong>{{ $cliente->nombre_comercial ?: $cliente->nombre }}</strong>
                            <span class="muted-line">
                                {{ $cliente->rut ?: 'Sin RUT' }}
                                @if($cliente->email) · {{ $cliente->email }} @endif
                            </span>
                        </td>
                        <td>
                            <div class="tag-list">
                                @forelse($cliente->centrosCosto as $centro)
                                    <a href="{{ route('comercial.centros-costo.show', $centro) }}" class="cc-chip">
                                        <i class="bi bi-diagram-3"></i>
                                        {{ $centro->nombre }}
                                        @if($centro->codigo)
                                            <span class="muted-line" style="display:inline;margin:0">({{ $centro->codigo }})</span>
                                        @endif
                                    </a>
                                @empty
                                    <span class="muted-line">Sin centros asociados</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $cliente->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($cliente->estado) }}
                            </span>
                        </td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('comercial.clientes.show', $cliente) }}" class="icon-btn" title="Ver">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <a href="{{ route('comercial.clientes.edit', $cliente) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="table-empty">No hay clientes para los filtros seleccionados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($clientes->hasPages())
        <div style="padding:1.5rem;text-align:center">
            {{ $clientes->links() }}
        </div>
        @endif
    </section>

    <section class="glass-card directory-table-card" id="centros">
        <div class="section-heading">
            <h3>Directorio por centro de costo</h3>
            <span>{{ $centrosCosto->total() }} registros</span>
        </div>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Centro de costo</th>
                        <th>Cliente</th>
                        <th>Código</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centrosCosto as $centro)
                    <tr>
                        <td><strong>{{ $centro->nombre }}</strong></td>
                        <td>{{ $centro->cliente?->nombre_comercial ?: $centro->cliente?->nombre }}</td>
                        <td>{{ $centro->codigo ?: 'Sin código' }}</td>
                        <td>
                            <span class="badge {{ $centro->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($centro->estado) }}
                            </span>
                        </td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('comercial.centros-costo.show', $centro) }}" class="icon-btn" title="Ver">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <a href="{{ route('comercial.centros-costo.edit', $centro) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="table-empty">No hay centros de costo para los filtros seleccionados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($centrosCosto->hasPages())
        <div style="padding:1.5rem;text-align:center">
            {{ $centrosCosto->links() }}
        </div>
        @endif
    </section>
</div>
@endsection
