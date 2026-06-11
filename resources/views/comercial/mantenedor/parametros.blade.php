@extends('layouts.app')
@section('title', 'Mantenedor de Parámetros')
@push('styles')
<style>
    .param-value-wrap {
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .param-value-wrap .form-control {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .param-unit {
        min-width: 58px;
        padding: .5rem .65rem;
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        background: var(--hover-bg, #f9fafb);
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
    }

    .param-format-hint {
        margin-top: .35rem;
        color: var(--text-muted);
        font-size: .72rem;
        font-weight: 600;
    }

    .param-category-layout {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
        gap: 1rem;
        align-items: stretch;
    }

    .param-category-panel {
        min-width: 0;
        padding: .95rem;
        border: 1px solid var(--surface-border);
        border-radius: 10px;
        background: var(--bg-tertiary);
    }

    .param-category-title {
        margin: 0 0 .85rem;
        color: var(--text-muted);
        font-size: .76rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .param-field-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: .85rem;
        align-items: stretch;
    }

    .param-field-card {
        min-width: 0;
        height: 100%;
        padding: .85rem;
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        background: var(--surface-color);
    }

    @media (max-width: 720px) {
        .param-category-layout,
        .param-field-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Mantenedor de Parámetros</h2>
            <p class="page-subheading">Configuración 100% editable del sistema de cotizaciones</p>
        </div>
    </div>

    @include('partials._alerts')

    <form method="POST" action="{{ route('comercial.parametros.batch-update') }}" id="parametrosForm">
        @csrf
        @php
            $valorParametro = fn($parametro) => $parametro->formatearValorVisual(old('parametros.' . $parametro->id . '.valor', $parametro->valor));
            $hintParametro = fn($parametro) => strtoupper($parametro->clave) === 'UF'
                ? 'Valor UF con decimales'
                : (strtoupper($parametro->clave) === 'JORNADA_SEMANAL_SUB'
                    ? 'Horas semanales para HHEE'
                    : match($parametro->formato_visual) {
                    'moneda' => 'Monto con separador de miles',
                    'porcentaje' => 'Valor porcentual',
                    'entero' => 'Número entero',
                    default => 'Número decimal',
                });
        @endphp

        {{-- Parámetros de Gobierno (UF, Sueldo Mínimo, IPC) --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                    <i class="bi bi-building" style="color:var(--accent-primary)"></i> Parámetros Gubernamentales
                </h3>
                <button type="button" class="btn-secondary" style="font-size:.85rem" onclick="actualizarParametrosGobierno()">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar desde APIs
                </button>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem">
                @foreach($parametrosPorCategoria['GOBIERNO'] ?? [] as $parametro)
                <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--accent-secondary)">
                    <div style="font-size:.85rem;font-weight:600;margin-bottom:.5rem">
                        {{ $parametro->nombre }}
                    </div>
                    <div style="display:flex;gap:.5rem;align-items:flex-end">
                        <div style="flex:1">
                            @if($parametro->editable)
                            <div class="param-value-wrap">
                                <input type="text" name="parametros[{{ $parametro->id }}][valor]"
                                       value="{{ $valorParametro($parametro) }}"
                                       class="form-control" style="font-size:1.1rem;font-weight:600"
                                       inputmode="decimal"
                                       data-parametro-id="{{ $parametro->id }}"
                                       data-param-format="{{ $parametro->formato_visual }}">
                                <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                            </div>
                            <div class="param-format-hint">{{ $hintParametro($parametro) }}</div>
                            @else
                            <div class="param-value-wrap">
                                <input type="text" value="{{ $parametro->formatearValorVisual() }}" class="form-control" disabled
                                       style="font-size:1.1rem;font-weight:600">
                                <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                        <div>v{{ $parametro->version }} • {{ $parametro->updated_at->format('d/m/Y H:i') }}</div>
                        <div style="margin-top:.25rem">{{ $parametro->descripcion }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Márgenes Operacionales --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-percent" style="color:var(--warning-color)"></i> Márgenes Operacionales
            </h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
                @foreach($parametrosPorCategoria['MARGENES'] ?? [] as $parametro)
                <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem">
                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                        {{ $parametro->nombre }}
                    </label>
                    <div class="param-value-wrap">
                        <input type="text" name="parametros[{{ $parametro->id }}][valor]"
                               value="{{ $valorParametro($parametro) }}"
                               class="form-control" placeholder="0" inputmode="decimal"
                               data-param-format="{{ $parametro->formato_visual }}"
                               @if(!$parametro->editable) disabled @endif>
                        <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                    </div>
                    <div class="param-format-hint">{{ $hintParametro($parametro) }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                        {{ $parametro->descripcion }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Tasas de Cotizaciones (Seguros) --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-shield-check" style="color:var(--danger-color)"></i> Tasas de Cotizaciones (ISES)
            </h3>

            <div class="param-category-layout">
                @php($categoriasTasas = $parametrosPorCategoria->filter(fn($items, $categoria) => str_starts_with((string) $categoria, 'TASAS')))
                @foreach($categoriasTasas as $categoria => $items)
                    <div class="param-category-panel">
                        <div class="param-category-title">{{ str_replace('_', ' ', $categoria) }}</div>
                        <div class="param-field-grid">
                            @foreach($items as $parametro)
                            <div class="param-field-card">
                                <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                                    {{ $parametro->nombre }}
                                </label>
                                <div class="param-value-wrap">
                                    <input type="text" name="parametros[{{ $parametro->id }}][valor]"
                                           value="{{ $valorParametro($parametro) }}"
                                           class="form-control" placeholder="0" inputmode="decimal"
                                           data-param-format="{{ $parametro->formato_visual }}"
                                           @if(!$parametro->editable) disabled @endif>
                                    <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                                </div>
                                <div class="param-format-hint">{{ $hintParametro($parametro) }}</div>
                                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                                    {{ $parametro->descripcion }}
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Parámetros de Fórmulas --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-sliders" style="color:var(--accent-primary)"></i> Fórmulas y Horas
            </h3>

            <div class="param-category-layout">
                @php($categoriasFormula = $parametrosPorCategoria->filter(fn($items, $categoria) => in_array((string) $categoria, ['FORMULAS', 'FORMULAS_EST', 'FORMULAS_SUB', 'HORAS'], true)))
                @foreach($categoriasFormula as $categoria => $items)
                    <div class="param-category-panel">
                        <div class="param-category-title">{{ str_replace('_', ' ', $categoria) }}</div>
                        <div class="param-field-grid">
                            @foreach($items as $parametro)
                                @if($parametro->clave === 'JORNADA_SEMANAL_SUB')
                                <div class="param-field-card" style="border-left:4px solid var(--accent-primary)">
                                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                                        {{ $parametro->nombre }}
                                    </label>
                                    <div class="param-value-wrap">
                                        <select name="parametros[{{ $parametro->id }}][valor]"
                                                class="form-control"
                                                onchange="actualizarFactorVisual(this)"
                                                @if(!$parametro->editable) disabled @endif>
                                            @foreach([45, 44, 43, 42, 41, 40] as $h)
                                                <option value="{{ $h }}" {{ old('parametros.' . $parametro->id . '.valor', $parametro->valor) == $h ? 'selected' : '' }}>
                                                    {{ $h }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                                    </div>
                                    <div class="param-format-hint">{{ $hintParametro($parametro) }}</div>
                                    
                                    <div style="margin-top:.75rem;padding:.5rem;background:var(--surface-bg);border-radius:.375rem;font-size:.8rem;color:var(--accent-primary);font-weight:600;display:flex;justify-content:space-between;align-items:center;">
                                        <span>Factor Matemático:</span>
                                        <span id="factor_visual_sub" style="font-variant-numeric: tabular-nums;">
                                            {{ number_format(7 / (30 * max((float)old('parametros.' . $parametro->id . '.valor', $parametro->valor), 1)), 6) }}
                                        </span>
                                    </div>
                                    
                                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                                        {{ $parametro->descripcion }}
                                    </div>
                                </div>
                                @else
                                <div class="param-field-card">
                                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                                        {{ $parametro->nombre }}
                                    </label>
                                    <div class="param-value-wrap">
                                        <input type="text" name="parametros[{{ $parametro->id }}][valor]"
                                               value="{{ $valorParametro($parametro) }}"
                                               class="form-control" placeholder="0" inputmode="decimal"
                                               data-param-format="{{ $parametro->formato_visual }}"
                                               @if(!$parametro->editable) disabled @endif>
                                        <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                                    </div>
                                    <div class="param-format-hint">{{ $hintParametro($parametro) }}</div>
                                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                                        {{ $parametro->descripcion }}
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Uniformes y Equipos --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-bag" style="color:var(--success-color)"></i> Costos de Uniformes
            </h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
                @foreach($parametrosPorCategoria['UNIFORMES'] ?? [] as $parametro)
                <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem">
                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                        {{ $parametro->nombre }}
                    </label>
                    <div class="param-value-wrap">
                        <input type="text" name="parametros[{{ $parametro->id }}][valor]"
                               value="{{ $valorParametro($parametro) }}"
                               class="form-control" placeholder="0" inputmode="decimal"
                               data-param-format="{{ $parametro->formato_visual }}"
                               @if(!$parametro->editable) disabled @endif>
                        <span class="param-unit">{{ $parametro->unidad_visual }}</span>
                    </div>
                    <div class="param-format-hint">{{ $hintParametro($parametro) }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                        {{ $parametro->descripcion }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Botones de Acción --}}
        <div style="display:flex;gap:1rem;justify-content:flex-end">
            <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">Volver</a>
            <button type="submit" class="btn-premium">
                <i class="bi bi-check-lg"></i> Guardar Cambios
            </button>
        </div>
    </form>

    {{-- Información de Versionado --}}
    <div class="glass-card" style="margin-top:2rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
            <i class="bi bi-clock-history"></i> Información del Sistema
        </h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:.25rem">Última Actualización</div>
                <strong>{{ $ultimaActualizacion?->updated_at->format('d/m/Y H:i:s') ?? 'N/A' }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:.25rem">Actualizado Por</div>
                <strong>{{ $ultimaActualizacion?->actualizadoPor?->name ?? 'Sistema' }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:.25rem">Total Parámetros</div>
                <strong>{{ count($parametrosPorCategoria->flatten()) }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:.25rem">Parámetros Editables</div>
                <strong>{{ count($parametrosPorCategoria->flatten()->where('editable', true)) }}</strong>
            </div>
        </div>

        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--surface-border);background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;font-size:.85rem">
            <i class="bi bi-info-circle"></i>
            <strong>Nota:</strong> Todos los cambios quedan registrados en la bitácora inferior con usuario, fecha, origen y valores modificados.
        </div>
    </div>

    <div class="glass-card" style="margin-top:2rem">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-journal-text"></i> Bitácora de Cambios de Parámetros
            </h3>
            <span class="badge badge-info">{{ $auditorias->count() }} últimos registros</span>
        </div>

        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Parámetro</th>
                        <th>Antes</th>
                        <th>Después</th>
                        <th>Origen</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditorias as $auditoria)
                    <tr>
                        <td style="font-size:.85rem;white-space:nowrap">{{ $auditoria->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $auditoria->usuario?->name ?? 'Sistema' }}</td>
                        <td>
                            <strong>{{ $auditoria->nombre }}</strong>
                            <div style="font-size:.75rem;color:var(--text-muted)">{{ $auditoria->clave }} · {{ $auditoria->categoria }}</div>
                        </td>
                        <td><code style="font-size:.8rem">{{ $auditoria->valor_anterior ?? 'N/A' }}</code></td>
                        <td><code style="font-size:.8rem">{{ $auditoria->valor_nuevo ?? 'N/A' }}</code></td>
                        <td>
                            <span class="badge {{ $auditoria->origen === 'api_gobierno' ? 'badge-info' : ($auditoria->origen === 'cotizador_rapido' ? 'badge-warning' : 'badge-success') }}">
                                {{ ucfirst(str_replace('_', ' ', $auditoria->origen)) }}
                            </span>
                        </td>
                        <td style="font-size:.8rem;color:var(--text-muted)">{{ $auditoria->ip_address ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            Aún no hay cambios registrados en parámetros.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
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

document.querySelectorAll('[data-param-format]').forEach((input) => {
    input.addEventListener('blur', () => {
        input.value = formatParamNumber(input.value, input.dataset.paramFormat);
    });
});

document.getElementById('parametrosForm')?.addEventListener('submit', () => {
    document.querySelectorAll('[data-param-format][name]').forEach((input) => {
        input.value = parseParamNumber(input.value, input.dataset.paramFormat);
    });
});

<script>
function actualizarFactorVisual(select) {
    const horas = parseFloat(select.value);
    if(horas > 0) {
        const factor = 7 / (30 * horas);
        document.getElementById('factor_visual_sub').innerText = factor.toFixed(6);
    }
}

function actualizarParametrosGobierno() {
    if(confirm('¿Actualizar parámetros de gobierno desde las APIs? (UF, Sueldo Mínimo, IPC)')) {
        fetch('{{ route("comercial.parametros.actualizar-gobierno") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                alert('Parámetros actualizados exitosamente');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(e => alert('Error de conexión: ' + e.message));
    }
}
</script>
@endsection
