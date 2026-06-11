@extends('layouts.app')
@section('title', 'Nueva Cotización')
@push('styles')
<style>
    .quote-form .glass-card {
        margin-bottom: 1rem !important;
        padding: 1.1rem 1.25rem;
    }

    .quote-form .form-grid {
        gap: .85rem 1rem;
    }

    .quote-form .form-group {
        margin-bottom: .75rem;
    }

    .quote-form .form-group label {
        margin-bottom: .35rem;
        font-size: .84rem;
    }

    .quote-form .form-control {
        min-height: 40px;
        padding-top: .55rem;
        padding-bottom: .55rem;
    }

    .quote-form textarea.form-control {
        min-height: 72px;
    }

    .quote-form .data-table th,
    .quote-form .data-table td {
        padding: .55rem .75rem;
        vertical-align: middle;
    }

    .quote-summary-bar {
        position: sticky;
        top: .75rem;
        z-index: 30;
        display: grid;
        grid-template-columns: minmax(190px, 1.2fr) repeat(5, minmax(115px, 1fr)) auto;
        gap: .55rem;
        align-items: stretch;
        margin-bottom: 1rem;
        padding: .7rem;
        border: 1px solid var(--surface-border);
        border-radius: 10px;
        background: color-mix(in srgb, var(--surface-color) 94%, transparent);
        box-shadow: 0 14px 35px rgba(15, 23, 42, .12);
        backdrop-filter: blur(16px);
    }

    .quote-summary-cell {
        min-width: 0;
        padding: .65rem .75rem;
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        background: var(--bg-tertiary, var(--hover-bg, #f9fafb));
    }

    .quote-summary-cell > span {
        display: block;
        color: var(--text-muted);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .quote-summary-cell strong {
        display: block;
        margin-top: .18rem;
        color: var(--text-primary);
        font-size: 1rem;
        line-height: 1.15;
        white-space: nowrap;
    }

    .quote-summary-cell.is-price {
        background: var(--primary-color, #0f1b4c);
        border-color: var(--primary-color, #0f1b4c);
        color: white;
    }

    .quote-summary-cell.is-price > span,
    .quote-summary-cell.is-price strong {
        color: white;
    }

    .quote-summary-cell.is-price strong {
        font-size: 1.45rem;
    }

    .quote-summary-actions {
        display: flex;
        gap: .45rem;
        align-items: center;
        justify-content: flex-end;
    }

    .quote-money-input {
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .quote-param-wrap {
        display: flex;
        gap: .45rem;
        align-items: center;
    }

    .quote-param-wrap .form-control {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .quote-param-unit {
        min-width: 54px;
        padding: .5rem .55rem;
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        background: var(--hover-bg, #f9fafb);
        color: var(--text-muted);
        font-size: .7rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
    }

    .quote-collapse summary {
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: .5rem;
        font-weight: 700;
    }

    .quote-collapse summary::-webkit-details-marker {
        display: none;
    }

    .quote-collapse summary::after {
        content: '+';
        margin-left: auto;
        color: var(--text-muted);
        font-weight: 700;
    }

    .quote-collapse[open] summary::after {
        content: '-';
    }

    .quote-breakdown-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: .85rem;
    }

    .quote-breakdown-section {
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        overflow: hidden;
        background: var(--surface-color);
    }

    .quote-breakdown-title {
        padding: .65rem .75rem;
        border-bottom: 1px solid var(--surface-border);
        background: var(--bg-tertiary, var(--hover-bg, #f9fafb));
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .quote-line {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        padding: .55rem .75rem;
        border-bottom: 1px solid var(--surface-border);
    }

    .quote-line:last-child {
        border-bottom: 0;
    }

    .quote-line-label {
        min-width: 0;
        color: var(--text-primary);
        font-size: .86rem;
        font-weight: 650;
    }

    .quote-line-meta {
        margin-top: .15rem;
        color: var(--text-muted);
        font-size: .72rem;
        line-height: 1.3;
    }

    .quote-line-value {
        align-self: center;
        color: var(--text-primary);
        font-size: .9rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .quote-line.is-total {
        background: color-mix(in srgb, var(--primary-color, #0f1b4c) 8%, var(--surface-color));
    }

    .quote-line.is-total .quote-line-label,
    .quote-line.is-total .quote-line-value {
        color: var(--primary-color, #0f1b4c);
    }

    @media (max-width: 1280px) {
        .quote-summary-bar {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .quote-summary-actions {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 900px) {
        .quote-summary-bar,
        .quote-breakdown-grid {
            grid-template-columns: 1fr;
        }

        .quote-summary-bar {
            position: static;
        }
    }
</style>
@endpush
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

    <form method="POST" action="{{ route('comercial.cotizaciones.store') }}" id="cotizacionForm" class="quote-form">
        @csrf

        <div class="quote-summary-bar" aria-label="Resumen de cotización">
            <div class="quote-summary-cell is-price">
                <span>Precio venta</span>
                <strong id="previewPrecioVenta">$0</strong>
                <input type="hidden" id="precioVenta" name="precio_venta" value="0">
            </div>
            <div class="quote-summary-cell">
                <span>Total haberes</span>
                <strong id="previewTotalRemuneraciones">$0</strong>
                <input type="hidden" id="totalRemuneraciones" value="0">
            </div>
            <div class="quote-summary-cell">
                <span>Cotizaciones (ISES)</span>
                <strong id="previewTotalCotizaciones">$0</strong>
                <input type="hidden" id="totalCotizaciones" value="0">
            </div>
            <div class="quote-summary-cell">
                <span>Provisiones</span>
                <strong id="previewTotalProvisiones">$0</strong>
                <input type="hidden" id="totalProvisiones" value="0">
            </div>
            <div class="quote-summary-cell">
                <span>Gastos op.</span>
                <strong id="previewTotalGastos">$0</strong>
                <input type="hidden" id="totalGastos" value="0">
            </div>
            <div class="quote-summary-cell">
                <span>Margen</span>
                <strong><span id="previewMargenPorcentaje">0%</span> / <span id="previewMargenValor">$0</span></strong>
                <input type="hidden" id="margenPorcentaje" value="0">
                <input type="hidden" id="margenValor" value="0">
                <input type="hidden" id="subtotal" value="0">
                <span id="previewSubtotal" style="display:none">$0</span>
            </div>
            <div class="quote-summary-actions">
                <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-check-lg"></i> Crear
                </button>
            </div>
        </div>

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
                    <div style="display:flex;gap:.5rem">
                        <select name="cliente_id" id="clienteSelect" class="form-control @error('cliente_id') is-invalid @enderror" required onchange="cargarCentrosCosto()">
                            <option value="">-- Seleccionar Cliente --</option>
                            @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>
                                {{ $cliente->nombre_comercial ?? $cliente->nombre }}
                            </option>
                            @endforeach
                        </select>
                        <button type="button" class="icon-btn" onclick="toggleQuickBox('quickClienteBox')" title="Crear cliente">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
                    <div id="quickClienteBox" style="display:none;margin-top:.65rem;padding:.75rem;border:1px solid var(--surface-border);border-radius:8px;background:var(--bg-tertiary)">
                        <div style="display:flex;gap:.5rem">
                            <input type="text" id="quickClienteNombre" class="form-control" placeholder="Nombre cliente">
                            <button type="button" class="btn-secondary" onclick="crearClienteRapido()">
                                <i class="bi bi-check-lg"></i> Guardar
                            </button>
                        </div>
                        <div id="quickClienteStatus" style="font-size:.78rem;color:var(--text-muted);margin-top:.4rem"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <div style="display:flex;gap:.5rem">
                        <select name="centro_costo_id" id="centroCostoSelect" class="form-control @error('centro_costo_id') is-invalid @enderror" required>
                            <option value="">-- Seleccionar Centro --</option>
                        </select>
                        <button type="button" class="icon-btn" onclick="toggleQuickBox('quickCentroBox')" title="Crear centro de costo">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    @error('centro_costo_id')<span class="form-error">{{ $message }}</span>@enderror
                    <div id="quickCentroBox" style="display:none;margin-top:.65rem;padding:.75rem;border:1px solid var(--surface-border);border-radius:8px;background:var(--bg-tertiary)">
                        <div style="display:flex;gap:.5rem">
                            <input type="text" id="quickCentroNombre" class="form-control" placeholder="Nombre centro de costo">
                            <button type="button" class="btn-secondary" onclick="crearCentroRapido()">
                                <i class="bi bi-check-lg"></i> Guardar
                            </button>
                        </div>
                        <div id="quickCentroStatus" style="font-size:.78rem;color:var(--text-muted);margin-top:.4rem"></div>
                    </div>
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
                <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas o comentarios sobre esta cotización">{{ old('observaciones') }}</textarea>
            </div>
        </div>

        @if(auth()->user()->tieneAcceso('comercial', 'puede_editar'))
        <div class="glass-card" style="margin-bottom:1.5rem">
            <details>
                <summary style="cursor:pointer;font-weight:700;display:flex;align-items:center;gap:.5rem">
                    <i class="bi bi-sliders" style="color:var(--accent-primary)"></i>
                    Parámetros rápidos de cálculo
                </summary>

                @php
                    $parametroScope = function ($parametro, $categoria) {
                        $clave = strtoupper($parametro->clave);
                        $categoria = strtoupper((string) $categoria);

                        if (str_ends_with($categoria, '_EST') || str_ends_with($clave, '_EST')) {
                            return 'EST';
                        }

                        if (str_ends_with($categoria, '_SUB') || str_ends_with($clave, '_SUB') || $clave === 'JORNADA_SEMANAL_SUB') {
                            return 'SUB';
                        }

                        return 'ALL';
                    };
                @endphp
                <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem">
                    @foreach($parametrosPorCategoria as $categoria => $items)
                        <div data-param-category-header data-param-category="{{ $categoria }}" style="grid-column:1/-1;font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-top:.25rem">
                            {{ str_replace('_', ' ', $categoria) }}
                        </div>
                        @foreach($items as $parametro)
                        <div data-param-card data-param-category="{{ $categoria }}" data-param-scope="{{ $parametroScope($parametro, $categoria) }}" style="background:var(--bg-tertiary);border:1px solid var(--surface-border);border-radius:8px;padding:.75rem">
                            <label style="font-size:.78rem;font-weight:600;display:block;margin-bottom:.4rem">
                                {{ $parametro->nombre }}
                            </label>
                            <div class="quote-param-wrap">
                                <input type="text"
                                       value="{{ $parametro->formatearValorVisual() }}"
                                       class="form-control"
                                       style="height:38px"
                                       inputmode="decimal"
                                       data-parametro-quick="{{ $parametro->id }}"
                                       data-param-format="{{ $parametro->formato_visual }}">
                                <span class="quote-param-unit">{{ $parametro->unidad_visual }}</span>
                            </div>
                        </div>
                        @endforeach
                    @endforeach
                    <div id="quickParametrosEmpty" style="display:none;grid-column:1/-1;padding:.8rem;border:1px dashed var(--surface-border);border-radius:8px;color:var(--text-muted);font-size:.82rem">
                        Selecciona una modalidad para ver sus parámetros específicos.
                    </div>
                </div>

                <div style="display:flex;gap:.75rem;justify-content:flex-end;align-items:center;margin-top:1rem">
                    <span id="quickParametrosStatus" style="font-size:.8rem;color:var(--text-muted)"></span>
                    <button type="button" class="btn-secondary" onclick="guardarParametrosRapidos()">
                        <i class="bi bi-save"></i> Guardar parámetros
                    </button>
                </div>
            </details>
        </div>
        @endif

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
                    <input type="text" name="asignacion_movilizacion" value="{{ old('asignacion_movilizacion', 0) }}" class="form-control quote-money-input" inputmode="numeric" data-money-input>
                </div>
                <div class="form-group">
                    <label>Asignación Colación</label>
                    <input type="text" name="asignacion_colacion" value="{{ old('asignacion_colacion', 0) }}" class="form-control quote-money-input" inputmode="numeric" data-money-input>
                </div>
                <div class="form-group">
                    <label>Servicios de Casino</label>
                    <input type="text" name="servicios_casino" value="{{ old('servicios_casino', 0) }}" class="form-control quote-money-input" inputmode="numeric" data-money-input>
                </div>
                <div class="form-group">
                    <label>Seguro Accidentes Personales</label>
                    <input type="text" name="seguro_accidentes" value="{{ old('seguro_accidentes', 0) }}" class="form-control quote-money-input" inputmode="numeric" data-money-input>
                </div>
                <div class="form-group">
                    <label>Otros Gastos</label>
                    <input type="text" name="otros_gastos" value="{{ old('otros_gastos', 0) }}" class="form-control quote-money-input" inputmode="numeric" data-money-input>
                </div>
                <div class="form-group">
                    <label>Otros Beneficios / Aguinaldos</label>
                    <input type="text" name="otros_beneficios" value="{{ old('otros_beneficios', 5000) }}" class="form-control quote-money-input" inputmode="numeric" data-money-input>
                </div>
            </div>
        </div>

        {{-- Sección de Uniformes --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <details class="quote-collapse">
                <summary>
                    <i class="bi bi-bag" style="color:var(--accent-primary)"></i>
                    Uniformes y Equipos (Opcional)
                </summary>

                <div style="overflow-x:auto;margin:1rem 0">
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
            </details>
        </div>

        {{-- Desglose de Cálculos --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-table" style="color:var(--accent-primary)"></i> Desglose de cálculo tipo Excel
            </h3>

            <div class="quote-breakdown-grid">
                <div class="quote-breakdown-section">
                    <div class="quote-breakdown-title">Haberes, descuentos e impuestos</div>
                    <div id="previewHaberesDetalle"></div>
                </div>

                <div class="quote-breakdown-section">
                    <div class="quote-breakdown-title">Seguros, provisiones y gastos</div>
                    <div id="previewCostosDetalle"></div>
                </div>

                <div class="quote-breakdown-section">
                    <div class="quote-breakdown-title">Precio y margen</div>
                    <div id="previewPrecioDetalle"></div>
                </div>

                <div class="quote-breakdown-section">
                    <div class="quote-breakdown-title">Valores hora</div>
                    <div id="previewHorasDetalle"></div>
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
const modalidadCodes = @json($modalidades->pluck('codigo', 'id'));
const selectedCentroCostoId = @json(old('centro_costo_id'));
const previewUrl = @json(route('comercial.cotizaciones.preview'));
const quickClienteUrl = @json(route('comercial.clientes.store'));
const quickCentroUrl = @json(route('comercial.centros-costo.store'));
const parametrosBatchUrl = @json(route('comercial.parametros.batch-update'));
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const sueldoMinimoLegal = Number(@json($sueldoMinimoLegal ?? 0));
const clpFormatter = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0
});
let previewTimer = null;

function toggleQuickBox(id) {
    const box = document.getElementById(id);
    if (box) {
        box.style.display = box.style.display === 'none' || !box.style.display ? 'block' : 'none';
    }
}

function setStatus(id, message, type = 'info') {
    const el = document.getElementById(id);
    if (!el) return;

    const colors = { info: 'var(--text-muted)', success: 'var(--success-color)', error: 'var(--danger-color)' };
    el.style.color = colors[type] || colors.info;
    el.textContent = message;
}

function getSelectedModalidadCode() {
    const modalidadId = document.getElementById('modalidadSelect')?.value;
    return modalidadId ? (modalidadCodes[modalidadId] || '') : '';
}

function filterQuickParamsByModalidad() {
    const modalidad = getSelectedModalidadCode();
    const cards = Array.from(document.querySelectorAll('[data-param-card]'));

    cards.forEach((card) => {
        const scope = card.dataset.paramScope || 'ALL';
        const visible = scope === 'ALL' || (modalidad && scope === modalidad);
        card.style.display = visible ? '' : 'none';
    });

    document.querySelectorAll('[data-param-category-header]').forEach((header) => {
        const category = header.dataset.paramCategory;
        const hasVisible = cards.some((card) => card.dataset.paramCategory === category && card.style.display !== 'none');
        header.style.display = hasVisible ? '' : 'none';
    });

    const empty = document.getElementById('quickParametrosEmpty');
    if (empty) {
        const hasSpecificCards = cards.some((card) => card.dataset.paramScope !== 'ALL' && card.style.display !== 'none');
        empty.style.display = modalidad && !hasSpecificCards ? 'block' : 'none';
    }
}

async function postForm(url, payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value));

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(firstError || data.message || 'No fue posible guardar.');
    }

    return data;
}

function appendOption(select, value, label) {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    option.selected = true;
    select.appendChild(option);
}

async function crearClienteRapido() {
    const input = document.getElementById('quickClienteNombre');
    const nombre = input.value.trim();

    if (!nombre) {
        setStatus('quickClienteStatus', 'Ingresa un nombre de cliente.', 'error');
        input.focus();
        return;
    }

    setStatus('quickClienteStatus', 'Guardando...');

    try {
        const data = await postForm(quickClienteUrl, { nombre });
        const select = document.getElementById('clienteSelect');
        appendOption(select, data.cliente.id, data.cliente.label);
        centrosCostoData[data.cliente.id] = [];
        cargarCentrosCosto();
        input.value = '';
        setStatus('quickClienteStatus', 'Cliente creado.', 'success');
        if (typeof showToast === 'function') showToast('Cliente creado.', 'success');
    } catch (error) {
        setStatus('quickClienteStatus', error.message, 'error');
    }
}

async function crearCentroRapido() {
    const clienteId = document.getElementById('clienteSelect').value;
    const input = document.getElementById('quickCentroNombre');
    const nombre = input.value.trim();

    if (!clienteId) {
        setStatus('quickCentroStatus', 'Selecciona o crea un cliente.', 'error');
        return;
    }

    if (!nombre) {
        setStatus('quickCentroStatus', 'Ingresa un nombre de centro.', 'error');
        input.focus();
        return;
    }

    setStatus('quickCentroStatus', 'Guardando...');

    try {
        const data = await postForm(quickCentroUrl, { cliente_id: clienteId, nombre });
        const centro = data.centro_costo;
        centrosCostoData[clienteId] = centrosCostoData[clienteId] || [];
        centrosCostoData[clienteId].push(centro);
        cargarCentrosCosto();
        document.getElementById('centroCostoSelect').value = String(centro.id);
        input.value = '';
        setStatus('quickCentroStatus', 'Centro creado.', 'success');
        if (typeof showToast === 'function') showToast('Centro de costo creado.', 'success');
    } catch (error) {
        setStatus('quickCentroStatus', error.message, 'error');
    }
}

async function guardarParametrosRapidos() {
    const inputs = document.querySelectorAll('[data-parametro-quick]');
    const formData = new FormData();
    formData.append('origen', 'cotizador_rapido');

    inputs.forEach((input) => {
        const card = input.closest('[data-param-card]');
        if (card && card.style.display === 'none') {
            return;
        }

        formData.append(`parametros[${input.dataset.parametroQuick}][valor]`, parseParamNumber(input.value, input.dataset.paramFormat));
    });

    setStatus('quickParametrosStatus', 'Guardando...');

    try {
        const response = await fetch(parametrosBatchUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'No fue posible guardar parámetros.');
        }

        setStatus('quickParametrosStatus', data.message || 'Parámetros guardados.', 'success');
        if (typeof showToast === 'function') showToast('Parámetros comerciales guardados.', 'success');
        actualizarCalculos();
    } catch (error) {
        setStatus('quickParametrosStatus', error.message, 'error');
    }
}

function cargarCentrosCosto() {
    const clienteId = document.getElementById('clienteSelect').value;
    const select = document.getElementById('centroCostoSelect');
    select.innerHTML = '<option value="">-- Seleccionar Centro --</option>';

    if(clienteId && centrosCostoData[clienteId]) {
        centrosCostoData[clienteId].forEach(cc => {
            const opt = document.createElement('option');
            opt.value = cc.id;
            opt.textContent = cc.codigo ? cc.nombre + ' (' + cc.codigo + ')' : cc.nombre;
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

function formatNumber(value) {
    return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(Number(value || 0));
}

function formatPercent(value) {
    return `${Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })}%`;
}

function formatFactor(value) {
    return Number(value || 0).toLocaleString('es-CL', {
        minimumFractionDigits: 6,
        maximumFractionDigits: 6
    });
}

function parseMoney(value) {
    return String(value ?? '').replace(/[^\d]/g, '');
}

function formatMoneyValue(value) {
    const raw = parseMoney(value);
    return raw ? new Intl.NumberFormat('es-CL').format(Number(raw)) : '';
}

function parseParamNumber(value, format) {
    let raw = String(value ?? '').replace(/[$%\s]/g, '').replace(/[^\d,.-]/g, '');
    if (!raw) return '';

    if (raw.includes(',')) {
        raw = raw.replace(/\./g, '').replace(',', '.');
    } else if (format === 'entero') {
        raw = raw.replace(/\./g, '');
    } else if (format === 'moneda') {
        const parts = raw.split('.');
        if (parts.length > 2 || (parts.length === 2 && parts[1].length === 3)) {
            raw = raw.replace(/\./g, '');
        }
    }

    return raw;
}

function formatParamNumber(value, format) {
    const raw = parseParamNumber(value, format);
    if (!raw || Number.isNaN(Number(raw))) return value;

    const number = Number(raw);
    const decimals = (() => {
        if (format === 'entero') return 0;
        if (format === 'decimal' && number > 0 && number < 1) return 6;
        if (Number.isInteger(number)) return 0;
        return 2;
    })();

    return new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(number);
}

function initMoneyInput(input) {
    if (!input || input.dataset.moneyReady === '1') return;

    input.dataset.moneyReady = '1';
    input.classList.add('quote-money-input');
    input.value = formatMoneyValue(input.value);

    input.addEventListener('input', () => {
        input.value = formatMoneyValue(input.value);
        actualizarUniformeFila(input.closest('tr'));
    });
}

function normalizeMoneyInputsForSubmit() {
    document.querySelectorAll('[data-money-input][name]').forEach((input) => {
        input.value = parseMoney(input.value);
    });
}

function buildPreviewFormData(form) {
    const formData = new FormData(form);
    form.querySelectorAll('[data-money-input][name]').forEach((input) => {
        formData.set(input.name, parseMoney(input.value));
    });

    return formData;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
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

function findDetail(detalles, concept) {
    const needle = concept.toLowerCase();
    return (detalles || []).find((detalle) => String(detalle.concepto || '').toLowerCase().includes(needle));
}

function detailMeta(detalles, concept, fallback = '') {
    const detalle = findDetail(detalles, concept);
    if (!detalle) return fallback;

    const meta = [];
    if (Number(detalle.valor_base || 0) > 0) {
        meta.push(`Base ${formatCLP(detalle.valor_base)}`);
    }
    if (detalle.porcentaje !== null && detalle.porcentaje !== undefined) {
        meta.push(formatPercent(detalle.porcentaje));
    }
    if (detalle.formula?.descripcion) {
        meta.push(detalle.formula.descripcion);
    }

    return meta.join(' · ') || fallback;
}

function lineRow(label, value, meta = '', total = false) {
    return `
        <div class="quote-line ${total ? 'is-total' : ''}">
            <div>
                <div class="quote-line-label">${escapeHtml(label)}</div>
                ${meta ? `<div class="quote-line-meta">${escapeHtml(meta)}</div>` : ''}
            </div>
            <div class="quote-line-value">${formatCLP(value)}</div>
        </div>
    `;
}

function hourRow(label, value, meta = '') {
    return `
        <div class="quote-line">
            <div>
                <div class="quote-line-label">${escapeHtml(label)}</div>
                ${meta ? `<div class="quote-line-meta">${escapeHtml(meta)}</div>` : ''}
            </div>
            <div class="quote-line-value">${formatCLP(value)}</div>
        </div>
    `;
}

function actualizarDesglose(data = {}) {
    const resumen = data.resumen_excel || {};
    const detalles = data.detalles || [];
    const horas = data.horas || {};
    const jornadaHhee = horas.jornada_semanal_hhee ?? resumen.jornadaSemanalHhee;
    const factorHhee = horas.factor_normal_hhee ?? resumen.horaNormalFactorHhee;
    const metaHoraHhee = jornadaHhee
        ? `Base HHEE ${formatCLP(resumen.precioVentaHhee)} x factor ${formatFactor(factorHhee)} (${formatNumber(jornadaHhee)} hrs/sem)`
        : `Base HHEE ${formatCLP(resumen.precioVentaHhee)} antes de recargos`;

    const haberesRows = [
        lineRow('Gratificación legal', resumen.gratificacion, detailMeta(detalles, 'Gratificación', '25% con tope legal'), false),
        lineRow('Total imponible', resumen.totalImponible, 'Sueldo base + bonos + gratificación', true),
        lineRow('Total no imponible', resumen.totalNoImponible, 'Movilización + colación', false),
        lineRow('Total haberes', resumen.totalHaberes, 'Total imponible + total no imponible', true),
        lineRow('Imposiciones', resumen.imposiciones, 'Descuento trabajador configurado en parámetros', false),
        lineRow('Alcance líquido', resumen.alcanceLiquido, 'Total haberes - imposiciones - IU', true),
        lineRow('Renta tributable', resumen.rentaTributable, 'Total imponible - imposiciones', false),
        lineRow('Impuesto Único (IU)', resumen.impuestoUnico, 'Factor y rebaja configurados en mantenedor', false),
    ].join('');

    const costosRows = [
        lineRow('REFPREV', resumen.refprev, detailMeta(detalles, 'REFPREV'), false),
        lineRow('Seg. Inv. y Sob. (SIS)', resumen.sis, detailMeta(detalles, 'SIS'), false),
        lineRow('Mutual Seguridad I.S.T.', resumen.mutual, detailMeta(detalles, 'Mutual'), false),
        lineRow('Seguro Cesantía', resumen.seguroCesantia, detailMeta(detalles, 'Cesantía'), false),
        lineRow('Total cotizaciones (ISES)', data.total_cotizaciones, 'REFPREV + SIS + Mutual + Cesantía', true),
        lineRow('Provisión Vacaciones', resumen.provisionVacaciones, detailMeta(detalles, 'Vacaciones'), false),
        lineRow('Provisión Indemnizaciones', resumen.provisionIndemnizaciones, detailMeta(detalles, 'Indemnizaciones', 'Aplica principalmente en modalidad SUB'), false),
        lineRow('Total provisiones', data.total_provisiones, 'Vacaciones + indemnizaciones cuando aplique', true),
        lineRow('Seguro Accidentes Personales', findDetail(detalles, 'Accidentes')?.valor || 0, detailMeta(detalles, 'Accidentes', 'Valor ingresado'), false),
        lineRow('Otros Seguros / Gastos', findDetail(detalles, 'Otros Gastos')?.valor || 0, detailMeta(detalles, 'Otros Gastos', 'Valor ingresado'), false),
        lineRow('Otros Beneficios / Aguinaldos', findDetail(detalles, 'Otros Beneficios')?.valor || 0, detailMeta(detalles, 'Otros Beneficios', 'Valor ingresado'), false),
        lineRow('Gastos Administración', resumen.gastosAdministracion, detailMeta(detalles, 'Administración'), false),
        lineRow('Total gastos operacionales', data.total_gastos, 'Uniformes + casino + seguros + beneficios + administración', true),
    ].join('');

    const precioRows = [
        lineRow('Costo bruto normal', resumen.costoBruto || data.subtotal, 'Haberes + ISES + provisiones + gastos', true),
        lineRow('Margen operacional normal', resumen.margen || data.margen, formatPercent(data.margen_porcentaje || 0), false),
        lineRow('Precio venta normal', resumen.precioVenta || data.precio_venta, 'Costo bruto normal + margen operacional', true),
        lineRow('Costo bruto HHEE', resumen.costoBrutoHhee, 'Total imponible + cotizaciones empresa', true),
        lineRow('Margen operacional HHEE', resumen.margenHhee, formatPercent(data.margen_porcentaje || 0), false),
        lineRow('Precio venta HHEE', resumen.precioVentaHhee, 'Base columna D para horas extra', true),
    ].join('');

    const horasRows = [
        hourRow('Hora normal', horas.normal ?? resumen.horaNormal, 'Precio venta / horas mensuales'),
        hourRow('Hora normal HHEE', horas.normal_hhee ?? resumen.horaNormalHhee, metaHoraHhee),
        hourRow('Hora extra 50%', horas.extra_50 ?? resumen.horaExtra50, 'Hora normal HHEE x 1,5'),
        hourRow('Hora extra 100%', horas.extra_100 ?? resumen.horaExtra100, 'Hora normal HHEE x 2'),
    ].join('');

    setText('previewHaberesDetalle', '');
    setText('previewCostosDetalle', '');
    setText('previewPrecioDetalle', '');
    setText('previewHorasDetalle', '');

    document.getElementById('previewHaberesDetalle').innerHTML = haberesRows;
    document.getElementById('previewCostosDetalle').innerHTML = costosRows;
    document.getElementById('previewPrecioDetalle').innerHTML = precioRows;
    document.getElementById('previewHorasDetalle').innerHTML = horasRows;
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

    actualizarDesglose(data);
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
            <input type="text" name="remuneraciones[${idx}][valor]" class="form-control quote-money-input" placeholder="0" inputmode="numeric" data-money-input required>
        </td>
        <td>
            <button type="button" class="icon-btn danger" onclick="this.parentElement.parentElement.remove(); schedulePreview()">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    `;
    tabla.appendChild(fila);
    initMoneyInput(fila.querySelector('[data-money-input]'));
}

function actualizarUniformeFila(fila) {
    if (!fila || !fila.querySelector('[name*="[precio_unitario]"]')) return;

    const cantidad = Number(fila.querySelector('[name*="[cantidad]"]')?.value || 0);
    const precio = Number(parseMoney(fila.querySelector('[name*="[precio_unitario]"]')?.value || 0));
    const total = fila.querySelector('[data-uniforme-total]');
    if (total) {
        total.value = formatCLP(cantidad * precio);
    }
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
            <input type="number" name="uniformes[${idx}][cantidad]" class="form-control" placeholder="0" min="0" value="1" oninput="actualizarUniformeFila(this.closest('tr'))" onchange="schedulePreview()">
        </td>
        <td>
            <input type="text" name="uniformes[${idx}][precio_unitario]" class="form-control quote-money-input" placeholder="0" inputmode="numeric" data-money-input>
        </td>
        <td>
            <input type="text" class="form-control quote-money-input" data-uniforme-total disabled placeholder="Total">
        </td>
        <td>
            <button type="button" class="icon-btn danger" onclick="this.parentElement.parentElement.remove(); schedulePreview()">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    `;
    tabla.appendChild(fila);
    initMoneyInput(fila.querySelector('[data-money-input]'));
    actualizarUniformeFila(fila);
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
            body: buildPreviewFormData(form)
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
    const form = document.getElementById('cotizacionForm');
    form.addEventListener('input', schedulePreview);
    form.addEventListener('change', schedulePreview);
    form.addEventListener('submit', normalizeMoneyInputsForSubmit);
    document.getElementById('modalidadSelect')?.addEventListener('change', filterQuickParamsByModalidad);

    const clienteId = document.getElementById('clienteSelect').value;
    if(clienteId) {
        cargarCentrosCosto();
    }
    if (document.getElementById('remuneracionesTable').children.length === 0) {
        ['Sueldo Base', 'Bono Asistencia', 'Bono Compromiso', 'Otros Haberes'].forEach(concepto => {
            agregarRemuneracion();
            const row = document.getElementById('remuneracionesTable').lastElementChild;
            row.querySelector('input[name*="[concepto]"]').value = concepto;
            const valorInput = row.querySelector('input[name*="[valor]"]');
            valorInput.value = concepto === 'Sueldo Base' ? (sueldoMinimoLegal || '') : 0;
            valorInput.value = formatMoneyValue(valorInput.value);
        });
    }
    document.querySelectorAll('[data-money-input]').forEach(initMoneyInput);
    document.querySelectorAll('[data-parametro-quick]').forEach((input) => {
        input.addEventListener('blur', () => {
            input.value = formatParamNumber(input.value, input.dataset.paramFormat);
        });
    });
    filterQuickParamsByModalidad();
    document.querySelectorAll('#uniformesTable tr').forEach(actualizarUniformeFila);
    actualizarCalculos();
});
</script>
@endsection
