@extends('layouts.app')
@section('title','Tarifas FACT')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Tarifas FACT</h2>
            <p class="page-subheading">Códigos, precios y pagos usados por Contenedores.</p>
        </div>
        <a href="{{ route('descarga-contenedores.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')
    @include('descarga_contenedores._context_help', [
        'title' => 'Uso de tarifas FACT',
        'items' => [
            'Costo unitario corresponde al valor de referencia de la operación para la empresa.',
            'Pago colaborador corresponde al monto base que se reparte entre trabajadores según porcentaje.',
            'Requiere revisión se usa cuando el código existe pero el valor debe confirmarse antes de liquidar.',
            'Los registros ya creados conservan snapshot histórico aunque luego se edite la tarifa.',
        ],
        'tone' => 'warning',
    ])

    <div class="glass-card" style="margin-bottom:1rem">
        <h4 class="section-title">Nuevo código</h4>
        <form method="POST" action="{{ route('descarga-contenedores.tarifas.store') }}" class="tarifa-form-grid">
            @csrf
            <div>
                <label>Cliente</label>
                <input type="text" name="cliente" class="form-control" value="{{ old('cliente', 'WM') }}" required>
            </div>
            <div>
                <label>Centro de costo</label>
                <select name="centro_costo_id" class="form-control">
                    <option value="">General del cliente</option>
                    @foreach($centros as $centro)
                        <option value="{{ $centro->id }}" {{ old('centro_costo_id') == $centro->id ? 'selected' : '' }}>
                            {{ $centro->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Código FACT</label>
                <input type="text" name="codigo" class="form-control" value="{{ old('codigo') }}" required>
            </div>
            <div>
                <label>Proceso</label>
                <input type="text" name="proceso" class="form-control" value="{{ old('proceso') }}" required>
            </div>
            <div>
                <label>Costo unitario @include('descarga_contenedores._help_icon', ['text' => 'Valor referencial asociado al trabajo del contenedor para la empresa.'])</label>
                <input type="number" name="costo_unitario" class="form-control" value="{{ old('costo_unitario') }}" min="0" step="0.01">
            </div>
            <div>
                <label>Pago colaborador @include('descarga_contenedores._help_icon', ['text' => 'Monto base a repartir entre los trabajadores participantes según porcentaje.'])</label>
                <input type="number" name="pago_colaborador" class="form-control" value="{{ old('pago_colaborador') }}" min="0" step="0.01">
            </div>
            <div class="check-row">
                <input type="hidden" name="requiere_revision" value="0">
                <label title="Marca códigos que no deben usarse como valor definitivo hasta confirmar tarifa"><input type="checkbox" name="requiere_revision" value="1" {{ old('requiere_revision') ? 'checked' : '' }}> Requiere revisión</label>
                <input type="hidden" name="activo" value="0">
                <label title="Sólo las tarifas activas aparecen en los selectores de registros"><input type="checkbox" name="activo" value="1" checked> Activo</label>
            </div>
            <div style="grid-column:1/-1">
                <label>Observaciones</label>
                <input type="text" name="observaciones" class="form-control" value="{{ old('observaciones') }}" maxlength="400">
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button class="btn-premium" type="submit"><i class="bi bi-plus-lg"></i> Crear código</button>
            </div>
        </form>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('descarga-contenedores.tarifas') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Cliente, código o proceso...">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="activos" {{ request('estado') === 'activos' ? 'selected' : '' }}>Activos</option>
                    <option value="inactivos" {{ request('estado') === 'inactivos' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Centro</label>
                <select name="centro_costo_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($centros as $centro)
                        <option value="{{ $centro->id }}" {{ request('centro_costo_id') == $centro->id ? 'selected' : '' }}>
                            {{ $centro->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-premium"><i class="bi bi-search"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','estado','centro_costo_id']))
                <a href="{{ route('descarga-contenedores.tarifas') }}" class="btn-secondary"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table tarifa-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Centro</th>
                        <th>Código</th>
                        <th>Proceso</th>
                        <th title="Valor referencial asociado al trabajo del contenedor para la empresa.">Costo unitario</th>
                        <th title="Monto base a repartir entre trabajadores según porcentaje.">Pago colaborador</th>
                        <th title="Estados operativos de la tarifa: revisión pendiente o disponibilidad en formularios.">Flags</th>
                        <th>Observaciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tarifas as $tarifa)
                    <tr>
                        <form method="POST" action="{{ route('descarga-contenedores.tarifas.update', $tarifa) }}">
                            @csrf
                            @method('PUT')
                            <td><input type="text" name="cliente" class="form-control mini-input" value="{{ $tarifa->cliente }}" required></td>
                            <td>
                                <select name="centro_costo_id" class="form-control center-input">
                                    <option value="">General</option>
                                    @foreach($centros as $centro)
                                        <option value="{{ $centro->id }}" {{ $tarifa->centro_costo_id == $centro->id ? 'selected' : '' }}>
                                            {{ $centro->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="codigo" class="form-control mini-input" value="{{ $tarifa->codigo }}" required></td>
                            <td><input type="text" name="proceso" class="form-control process-input" value="{{ $tarifa->proceso }}" required></td>
                            <td><input type="number" name="costo_unitario" class="form-control mini-input" value="{{ $tarifa->costo_unitario }}" min="0" step="0.01"></td>
                            <td><input type="number" name="pago_colaborador" class="form-control mini-input" value="{{ $tarifa->pago_colaborador }}" min="0" step="0.01"></td>
                            <td>
                                <input type="hidden" name="requiere_revision" value="0">
                                <label class="table-check" title="Marcar si el código debe confirmarse antes de liquidar"><input type="checkbox" name="requiere_revision" value="1" {{ $tarifa->requiere_revision ? 'checked' : '' }}> Revisión</label>
                                <input type="hidden" name="activo" value="0">
                                <label class="table-check" title="Sólo las tarifas activas aparecen en los selectores de registros"><input type="checkbox" name="activo" value="1" {{ $tarifa->activo ? 'checked' : '' }}> Activo</label>
                            </td>
                            <td><input type="text" name="observaciones" class="form-control obs-input" value="{{ $tarifa->observaciones }}" maxlength="400"></td>
                            <td><button class="icon-btn" title="Guardar"><i class="bi bi-save-fill"></i></button></td>
                        </form>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted)">No hay tarifas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tarifas->hasPages())
            <div style="padding:1rem 0">{{ $tarifas->links() }}</div>
        @endif
    </div>
</div>

<style>
.section-title {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--surface-border);
}
.tarifa-form-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .75rem; align-items: end; }
.tarifa-form-grid label { font-size: .75rem; color: var(--text-muted); }
.check-row { display: grid; gap: .35rem; align-self: center; }
.check-row label, .table-check { font-size: .78rem; color: var(--text-main); display: flex; align-items: center; gap: .35rem; white-space: nowrap; }
.tarifa-table { min-width: 1320px; }
.mini-input { min-width: 105px; }
.center-input { min-width: 190px; }
.process-input { min-width: 220px; }
.obs-input { min-width: 260px; }
@media (max-width: 980px) {
    .tarifa-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .tarifa-form-grid { grid-template-columns: 1fr; }
}
</style>
@endsection
