@extends('layouts.app')
@section('title', 'Nueva Cotización')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Nueva Cotización</h2>
            <p class="page-subheading">Crear nueva cotización de servicios</p>
        </div>
        <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <form method="POST" action="{{ route('comercial.cotizaciones.store') }}" id="cotizacionForm">
        @csrf

        {{-- Sección de Datos Básicos --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-info-circle" style="color:var(--accent-primary)"></i> Datos Básicos
            </h3>

            <div class="form-grid">
                <div class="form-group">
                    <label>Título de Cotización</label>
                    <input type="text" name="titulo" value="{{ old('titulo') }}"
                           class="form-control @error('titulo') is-invalid @enderror"
                           placeholder="Ej: Tarifa Operario Enero 2026">
                    @error('titulo')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Cargo / Puesto <span class="required">*</span></label>
                    <input type="text" name="cargo" value="{{ old('cargo') }}"
                           class="form-control @error('cargo') is-invalid @enderror"
                           placeholder="Ej: Analista de Inventarios" required>
                    @error('cargo')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Cliente <span class="required">*</span></label>
                    <select name="cliente_id" id="clienteSelect" class="form-control @error('cliente_id') is-invalid @enderror" required onchange="cargarCentrosCosto()">
                        <option value="">-- Seleccionar Cliente --</option>
                        @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre_comercial ?? $cliente->nombre }} ({{ $cliente->rut }})
                        </option>
                        @endforeach
                    </select>
                    @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" id="centroCostoSelect" class="form-control @error('centro_costo_id') is-invalid @enderror" required>
                        <option value="">-- Seleccionar Centro --</option>
                    </select>
                    @error('centro_costo_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Modalidad <span class="required">*</span></label>
                    <select name="modalidad_id" id="modalidadSelect" class="form-control @error('modalidad_id') is-invalid @enderror" required onchange="actualizarCalculos()">
                        <option value="">-- Seleccionar Modalidad --</option>
                        @foreach($modalidades as $modalidad)
                        <option value="{{ $modalidad->id }}" {{ old('modalidad_id') == $modalidad->id ? 'selected' : '' }}>
                            {{ $modalidad->codigo }} - {{ $modalidad->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('modalidad_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="3" placeholder="Notas o comentarios sobre esta cotización">{{ old('observaciones') }}</textarea>
            </div>
        </div>

        {{-- Sección de Remuneraciones --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-calculator" style="color:var(--accent-primary)"></i> Remuneraciones
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
                        {{-- Filas dinámicas --}}
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn-secondary" onclick="agregarRemuneracion()">
                <i class="bi bi-plus-lg"></i> Agregar Remuneración
            </button>
        </div>

        {{-- Sección de Asignaciones y Costos --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-wallet2" style="color:var(--accent-primary)"></i> Asignaciones y Otros Costos
            </h3>

            <div class="form-grid">
                <div class="form-group">
                    <label>Asignación Movilización</label>
                    <input type="number" name="asignacion_movilizacion" value="{{ old('asignacion_movilizacion', 0) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Asignación Colación</label>
                    <input type="number" name="asignacion_colacion" value="{{ old('asignacion_colacion', 0) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Servicios de Casino</label>
                    <input type="number" name="servicios_casino" value="{{ old('servicios_casino', 0) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Seguro Accidentes Personales</label>
                    <input type="number" name="seguro_accidentes" value="{{ old('seguro_accidentes', 0) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Otros Gastos</label>
                    <input type="number" name="otros_gastos" value="{{ old('otros_gastos', 0) }}" class="form-control" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Otros Beneficios / Aguinaldos</label>
                    <input type="number" name="otros_beneficios" value="{{ old('otros_beneficios', 5000) }}" class="form-control" min="0" step="1">
                </div>
            </div>
        </div>

        {{-- Sección de Uniformes --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-bag" style="color:var(--accent-primary)"></i> Uniformes y Equipos (Opcional)
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
                        {{-- Filas dinámicas --}}
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn-secondary" onclick="agregarUniforme()">
                <i class="bi bi-plus-lg"></i> Agregar Uniforme
            </button>
        </div>

        {{-- Resumen de Cálculos --}}
        <div class="glass-card" style="margin-bottom:1.5rem;background:linear-gradient(135deg, var(--surface-color) 0%, var(--bg-tertiary) 100%)">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-graph-up" style="color:var(--accent-primary)"></i> Resumen de Cálculo
            </h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
                <div style="border-left:4px solid var(--accent-primary);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Total Remuneraciones</div>
                    <div id="previewTotalRemuneraciones" style="font-size:1.5rem;font-weight:700;color:var(--text-primary)">$0</div>
                    <input type="hidden" id="totalRemuneraciones" value="0">
                </div>

                <div style="border-left:4px solid var(--accent-secondary);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Cotizaciones (ISES)</div>
                    <div id="previewTotalCotizaciones" style="font-size:1.5rem;font-weight:700;color:var(--text-primary)">$0</div>
                    <input type="hidden" id="totalCotizaciones" value="0">
                </div>

                <div style="border-left:4px solid var(--warning-color);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Provisiones</div>
                    <div id="previewTotalProvisiones" style="font-size:1.5rem;font-weight:700;color:var(--text-primary)">$0</div>
                    <input type="hidden" id="totalProvisiones" value="0">
                </div>

                <div style="border-left:4px solid var(--danger-color);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Gastos Operacionales</div>
                    <div id="previewTotalGastos" style="font-size:1.5rem;font-weight:700;color:var(--text-primary)">$0</div>
                    <input type="hidden" id="totalGastos" value="0">
                </div>

                <div style="border-left:4px solid var(--success-color);padding-left:1rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Subtotal</div>
                    <div id="previewSubtotal" style="font-size:1.5rem;font-weight:700;color:var(--text-primary)">$0</div>
                    <input type="hidden" id="subtotal" value="0">
                </div>

                <div style="border-left:4px solid var(--accent-primary);padding-left:1rem;background:var(--bg-tertiary);padding:1rem;border-radius:.5rem">
                    <div style="font-size:.85rem;color:var(--text-muted)">Margen (%) / Valor</div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-primary)">
                        <span id="previewMargenPorcentaje">0%</span> / <span id="previewMargenValor">$0</span>
                    </div>
                    <input type="hidden" id="margenPorcentaje" value="0">
                    <input type="hidden" id="margenValor" value="0">
                </div>

                <div style="grid-column:1/-1;border-top:2px solid var(--surface-border);padding-top:1rem;margin-top:1rem">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:1.1rem;font-weight:600">PRECIO VENTA</span>
                        <div id="previewPrecioVenta" style="font-size:2rem;font-weight:700;color:var(--accent-primary)">$0</div>
                    </div>
                    <input type="hidden" id="precioVenta" name="precio_venta" value="0">
                </div>
            </div>
        </div>

        {{-- Botones de Acción --}}
        <div style="display:flex;gap:1rem;justify-content:flex-end">
            <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-premium">
                <i class="bi bi-check-lg"></i> Crear Cotización
            </button>
        </div>
    </form>
</div>

{{-- Script para cálculos y dinámicas --}}
<script>
const centrosCostoData = {!! json_encode($centrosCostoAgrupados ?? []) !!};
const selectedCentroCostoId = @json(old('centro_costo_id'));
const previewUrl = @json(route('comercial.cotizaciones.preview'));
const clpFormatter = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0
});
let previewTimer = null;

function cargarCentrosCosto() {
    const clienteId = document.getElementById('clienteSelect').value;
    const select = document.getElementById('centroCostoSelect');
    select.innerHTML = '<option value="">-- Seleccionar Centro --</option>';

    if(clienteId && centrosCostoData[clienteId]) {
        centrosCostoData[clienteId].forEach(cc => {
            const opt = document.createElement('option');
            opt.value = cc.id;
            opt.textContent = cc.nombre + ' (' + cc.codigo + ')';
            if (String(cc.id) === String(selectedCentroCostoId)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }
}

function formatCLP(value) {
    return clpFormatter.format(Number(value || 0));
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.value = Number(value || 0).toFixed(2);
    }
}

function actualizarResumen(data = {}) {
    setText('previewTotalRemuneraciones', formatCLP(data.total_remuneraciones));
    setText('previewTotalCotizaciones', formatCLP(data.total_cotizaciones));
    setText('previewTotalProvisiones', formatCLP(data.total_provisiones));
    setText('previewTotalGastos', formatCLP(data.total_gastos));
    setText('previewSubtotal', formatCLP(data.subtotal));
    setText('previewMargenPorcentaje', `${Number(data.margen_porcentaje || 0).toFixed(2)}%`);
    setText('previewMargenValor', formatCLP(data.margen));
    setText('previewPrecioVenta', formatCLP(data.precio_venta));

    setValue('totalRemuneraciones', data.total_remuneraciones);
    setValue('totalCotizaciones', data.total_cotizaciones);
    setValue('totalProvisiones', data.total_provisiones);
    setValue('totalGastos', data.total_gastos);
    setValue('subtotal', data.subtotal);
    setValue('margenPorcentaje', data.margen_porcentaje);
    setValue('margenValor', data.margen);
    setValue('precioVenta', data.precio_venta);
}

function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(actualizarCalculos, 250);
}

function agregarRemuneracion() {
    const tabla = document.getElementById('remuneracionesTable');
    const fila = document.createElement('tr');
    const idx = tabla.children.length;
    fila.innerHTML = `
        <td>
            <input type="text" name="remuneraciones[${idx}][concepto]" class="form-control" placeholder="Sueldo Base, Bono, etc" required>
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
    const fila = document.createElement('tr');
    const idx = tabla.children.length;
    fila.innerHTML = `
        <td>
            <input type="text" name="uniformes[${idx}][descripcion]" class="form-control" placeholder="Ej: Casco de Seguridad">
        </td>
        <td>
            <input type="number" name="uniformes[${idx}][cantidad]" class="form-control" placeholder="0" min="0" value="1" onchange="schedulePreview()">
        </td>
        <td>
            <input type="number" name="uniformes[${idx}][precio_unitario]" class="form-control" placeholder="0" step=".01" min="0" onchange="schedulePreview()">
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

async function actualizarCalculos() {
    const form = document.getElementById('cotizacionForm');
    if (!document.getElementById('modalidadSelect').value) {
        actualizarResumen();
        return;
    }

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

// Cargar centros de costo si hay cliente preseleccionado
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('cotizacionForm').addEventListener('input', schedulePreview);
    document.getElementById('cotizacionForm').addEventListener('change', schedulePreview);

    const clienteId = document.getElementById('clienteSelect').value;
    if(clienteId) {
        cargarCentrosCosto();
    }
    if (document.getElementById('remuneracionesTable').children.length === 0) {
        ['Sueldo Base', 'Bono Asistencia', 'Bono Compromiso', 'Otros Haberes'].forEach(concepto => {
            agregarRemuneracion();
            const row = document.getElementById('remuneracionesTable').lastElementChild;
            row.querySelector('input[type="text"]').value = concepto;
            row.querySelector('input[type="number"]').value = concepto === 'Sueldo Base' ? '' : 0;
        });
    }
    actualizarCalculos();
});
</script>
@endsection
