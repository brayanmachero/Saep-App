@extends('layouts.app')
@section('title', 'Editar Cotización ' . $cotizacion->numero)
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Cotización</h2>
            <p class="page-subheading">{{ $cotizacion->numero }}</p>
        </div>
        <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    @if($cotizacion->estado !== 'en_cotizacion')
    <div style="background:var(--warning-color);color:white;padding:1rem;border-radius:.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Cotización no editable</strong>
            <p style="margin:.25rem 0 0 0;font-size:.9rem">Solo se pueden editar cotizaciones en estado "En Cotización". Para hacer cambios, cree una nueva versión.</p>
        </div>
    </div>
    @else

    <form method="POST" action="{{ route('comercial.cotizaciones.update', $cotizacion) }}" id="editForm">
        @csrf @method('PATCH')

        <input type="hidden" name="cliente_id" value="{{ $cotizacion->cliente_id }}">
        <input type="hidden" name="centro_costo_id" value="{{ $cotizacion->centro_costo_id }}">
        <input type="hidden" name="modalidad_id" value="{{ $cotizacion->modalidad_id }}">

        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-info-circle"></i> Datos Básicos
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Título de Cotización</label>
                    <input type="text" name="titulo" value="{{ old('titulo', $cotizacion->titulo) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Cargo / Puesto <span class="required">*</span></label>
                    <input type="text" name="cargo" value="{{ old('cargo', $cotizacion->cargo) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Vigencia Desde</label>
                    <input type="date" name="fecha_vigencia_desde" value="{{ old('fecha_vigencia_desde', optional($cotizacion->fecha_vigencia_desde)->format('Y-m-d')) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Vigencia Hasta</label>
                    <input type="date" name="fecha_vigencia_hasta" value="{{ old('fecha_vigencia_hasta', optional($cotizacion->fecha_vigencia_hasta)->format('Y-m-d')) }}" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="3">{{ old('observaciones', $cotizacion->observaciones) }}</textarea>
            </div>
        </div>

        {{-- Sección de Remuneraciones --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-calculator"></i> Remuneraciones
            </h3>

            <div style="overflow-x:auto;margin-bottom:1rem">
                <table class="data-table" style="margin-bottom:1rem">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Valor Mensual</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="remuneracionesTable">
                        @foreach($cotizacion->detalles->where('tipo', 'remuneracion') as $idx => $detalle)
                        <tr>
                            <td>
                                <input type="text" name="remuneraciones[{{ $idx }}][concepto]" value="{{ $detalle->concepto }}" class="form-control" required>
                            </td>
                            <td>
                                <input type="number" name="remuneraciones[{{ $idx }}][valor]" value="{{ $detalle->valor_base }}" class="form-control" step="1" min="0" required onchange="recalcular()">
                            </td>
                            <td>
                                <button type="button" class="icon-btn danger" onclick="this.parentElement.parentElement.remove(); recalcular()">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn-secondary" onclick="agregarRemuneracion()">
                <i class="bi bi-plus-lg"></i> Agregar Remuneración
            </button>
        </div>

        {{-- Sección de Asignaciones y Costos --}}
        @php
            $detalleValor = fn($concepto) => optional($cotizacion->detalles->firstWhere('concepto', $concepto))->valor ?? 0;
        @endphp
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-wallet2"></i> Asignaciones y Otros Costos
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Asignación Movilización</label>
                    <input type="number" name="asignacion_movilizacion" value="{{ old('asignacion_movilizacion', $detalleValor('Asignación Movilización')) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Asignación Colación</label>
                    <input type="number" name="asignacion_colacion" value="{{ old('asignacion_colacion', $detalleValor('Asignación Colación')) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Servicios de Casino</label>
                    <input type="number" name="servicios_casino" value="{{ old('servicios_casino', $detalleValor('Servicios de Casino')) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Seguro Accidentes Personales</label>
                    <input type="number" name="seguro_accidentes" value="{{ old('seguro_accidentes', $detalleValor('Seguro Accidentes Personales')) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Otros Gastos</label>
                    <input type="number" name="otros_gastos" value="{{ old('otros_gastos', $detalleValor('Otros Gastos')) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Otros Beneficios / Aguinaldos</label>
                    <input type="number" name="otros_beneficios" value="{{ old('otros_beneficios', $detalleValor('Otros Beneficios')) }}" class="form-control" min="0" step="1">
                </div>
            </div>
        </div>

        {{-- Sección de Uniformes --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-bag"></i> Uniformes y Equipos
            </h3>

            <div style="overflow-x:auto;margin-bottom:1rem">
                <table class="data-table" style="margin-bottom:1rem">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="uniformesTable">
                        @foreach($cotizacion->uniformes as $idx => $uniforme)
                        <tr>
                            <td>
                                <select class="form-control" style="margin-bottom:.45rem" data-uniforme-catalogo onchange="seleccionarUniformeCatalogo(this)">
                                    <option value="">-- Item libre / catálogo --</option>
                                    @foreach($uniformesCatalogo ?? [] as $item)
                                    <option value="{{ $item['id'] }}">{{ $item['nombre'] }} - ${{ number_format($item['valor'], 0, ',', '.') }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="uniformes[{{ $idx }}][descripcion]" value="{{ $uniforme->descripcion }}" class="form-control">
                            </td>
                            <td>
                                <input type="number" name="uniformes[{{ $idx }}][cantidad]" value="{{ $uniforme->cantidad }}" class="form-control" min="0" onchange="recalcular()">
                            </td>
                            <td>
                                <input type="number" name="uniformes[{{ $idx }}][precio_unitario]" value="{{ $uniforme->precio_unitario }}" class="form-control" step=".01" min="0" onchange="recalcular()">
                            </td>
                            <td>
                                <input type="text" value="{{ number_format($uniforme->total, 0, ',', '.') }}" class="form-control" disabled>
                            </td>
                            <td>
                                <button type="button" class="icon-btn danger" onclick="this.parentElement.parentElement.remove(); recalcular()">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn-secondary" onclick="agregarUniforme()">
                <i class="bi bi-plus-lg"></i> Agregar Uniforme
            </button>
        </div>

        {{-- Resumen de Cálculos (Read-only) --}}
        <div class="glass-card" style="margin-bottom:1.5rem;background:linear-gradient(135deg, var(--surface-color) 0%, var(--bg-tertiary) 100%)">
            <h3 style="margin:0 0 1rem 0;font-size:1rem">Resumen de Cálculo</h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
                <div style="border-left:4px solid var(--accent-primary);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Total Remuneraciones</div>
                    <div id="previewTotalRemuneraciones" style="font-size:1.5rem;font-weight:700">${{ number_format($cotizacion->total_remuneraciones, 0, ',', '.') }}</div>
                </div>

                <div style="border-left:4px solid var(--accent-secondary);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Cotizaciones</div>
                    <div id="previewTotalCotizaciones" style="font-size:1.5rem;font-weight:700">${{ number_format($cotizacion->total_cotizaciones, 0, ',', '.') }}</div>
                </div>

                <div style="border-left:4px solid var(--warning-color);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Provisiones</div>
                    <div id="previewTotalProvisiones" style="font-size:1.5rem;font-weight:700">${{ number_format($cotizacion->total_provisiones, 0, ',', '.') }}</div>
                </div>

                <div style="border-left:4px solid var(--success-color);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Precio Venta</div>
                    <div id="previewPrecioVenta" style="font-size:1.5rem;font-weight:700;color:var(--accent-primary)">${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        {{-- Botones --}}
        <div style="display:flex;gap:1rem;justify-content:flex-end">
            <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-premium">
                <i class="bi bi-check-lg"></i> Actualizar Cotización
            </button>
        </div>
    </form>

    @endif
</div>

<script>
const previewUrl = @json(route('comercial.cotizaciones.preview'));
const uniformesCatalogo = @json($uniformesCatalogo ?? []);
const clpFormatter = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0
});
let previewTimer = null;

function formatCLP(value) {
    return clpFormatter.format(Number(value || 0));
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function uniformCatalogOptions() {
    return uniformesCatalogo.map((item) => {
        return `<option value="${escapeHtml(item.id)}">${escapeHtml(item.nombre)} - ${formatCLP(item.valor)}</option>`;
    }).join('');
}

function seleccionarUniformeCatalogo(select) {
    const fila = select.closest('tr');
    const item = uniformesCatalogo.find((uniforme) => String(uniforme.id) === String(select.value));
    if (!fila || !item) return;

    fila.querySelector('[name*="[descripcion]"]').value = item.nombre;
    fila.querySelector('[name*="[precio_unitario]"]').value = Number(item.valor || 0).toFixed(0);
    schedulePreview();
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function actualizarResumen(data = {}) {
    setText('previewTotalRemuneraciones', formatCLP(data.total_remuneraciones));
    setText('previewTotalCotizaciones', formatCLP(data.total_cotizaciones));
    setText('previewTotalProvisiones', formatCLP(data.total_provisiones));
    setText('previewPrecioVenta', formatCLP(data.precio_venta));
}

function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(recalcular, 250);
}

function agregarRemuneracion() {
    const tabla = document.getElementById('remuneracionesTable');
    const idx = tabla.children.length;
    const fila = document.createElement('tr');
    fila.innerHTML = `
        <td>
            <input type="text" name="remuneraciones[${idx}][concepto]" class="form-control" placeholder="Concepto" required>
        </td>
        <td>
            <input type="number" name="remuneraciones[${idx}][valor]" class="form-control" placeholder="0" step="1" min="0" required onchange="schedulePreview()">
        </td>
        <td>
            <button type="button" class="icon-btn danger" onclick="this.parentElement.parentElement.remove(); schedulePreview()">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    `;
    tabla.appendChild(fila);
}

function agregarUniforme() {
    const tabla = document.getElementById('uniformesTable');
    const idx = tabla.children.length;
    const fila = document.createElement('tr');
    fila.innerHTML = `
        <td>
            <select class="form-control" style="margin-bottom:.45rem" data-uniforme-catalogo onchange="seleccionarUniformeCatalogo(this)">
                <option value="">-- Item libre / catálogo --</option>
                ${uniformCatalogOptions()}
            </select>
            <input type="text" name="uniformes[${idx}][descripcion]" class="form-control" placeholder="Descripción">
        </td>
        <td>
            <input type="number" name="uniformes[${idx}][cantidad]" class="form-control" min="0" value="1" onchange="schedulePreview()">
        </td>
        <td>
            <input type="number" name="uniformes[${idx}][precio_unitario]" class="form-control" step=".01" min="0" onchange="schedulePreview()">
        </td>
        <td>
            <input type="text" class="form-control" disabled placeholder="Total">
        </td>
        <td>
            <button type="button" class="icon-btn danger" onclick="this.parentElement.parentElement.remove(); schedulePreview()">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    `;
    tabla.appendChild(fila);
}

async function recalcular() {
    const form = document.getElementById('editForm');

    try {
        const response = await fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        });

        if (!response.ok) {
            return;
        }

        actualizarResumen(await response.json());
    } catch (error) {
        console.error('No fue posible previsualizar la cotización.', error);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editForm');
    if (!form) {
        return;
    }

    form.addEventListener('input', schedulePreview);
    form.addEventListener('change', schedulePreview);
});
</script>
@endsection
