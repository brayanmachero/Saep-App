@extends('layouts.app')
@section('title', 'Mantenedor de Parámetros')
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
                            <input type="text" name="parametros[{{ $parametro->id }}][valor]"
                                   value="{{ old('parametros.' . $parametro->id . '.valor', $parametro->valor) }}"
                                   class="form-control" style="font-size:1.1rem;font-weight:600"
                                   data-parametro-id="{{ $parametro->id }}">
                            @else
                            <input type="text" value="{{ $parametro->valor }}" class="form-control" disabled
                                   style="font-size:1.1rem;font-weight:600">
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
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="number" name="parametros[{{ $parametro->id }}][valor]"
                               value="{{ old('parametros.' . $parametro->id . '.valor', $parametro->valor) }}"
                               class="form-control" placeholder="0" step="0.01" min="0"
                               @if(!$parametro->editable) disabled @endif>
                        <span style="font-weight:600;color:var(--accent-primary)">%</span>
                    </div>
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

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
                @php($categoriasTasas = $parametrosPorCategoria->filter(fn($items, $categoria) => str_starts_with((string) $categoria, 'TASAS')))
                @foreach($categoriasTasas as $categoria => $items)
                    <div style="grid-column:1/-1;font-size:.8rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-top:.25rem">{{ str_replace('_', ' ', $categoria) }}</div>
                    @foreach($items as $parametro)
                <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem">
                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                        {{ $parametro->nombre }}
                    </label>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="number" name="parametros[{{ $parametro->id }}][valor]"
                               value="{{ old('parametros.' . $parametro->id . '.valor', $parametro->valor) }}"
                               class="form-control" placeholder="0" step="0.01" min="0"
                               @if(!$parametro->editable) disabled @endif>
                        <span style="font-weight:600;color:var(--danger-color)">%</span>
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                        {{ $parametro->descripcion }}
                    </div>
                </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Parámetros de Fórmulas --}}
        <div class="glass-card" style="margin-bottom:1.5rem">
            <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-sliders" style="color:var(--accent-primary)"></i> Fórmulas y Horas
            </h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
                @php($categoriasFormula = $parametrosPorCategoria->filter(fn($items, $categoria) => in_array((string) $categoria, ['FORMULAS', 'FORMULAS_EST', 'FORMULAS_SUB', 'HORAS'], true)))
                @foreach($categoriasFormula as $categoria => $items)
                    <div style="grid-column:1/-1;font-size:.8rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-top:.25rem">{{ str_replace('_', ' ', $categoria) }}</div>
                    @foreach($items as $parametro)
                    <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem">
                        <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.75rem">
                            {{ $parametro->nombre }}
                        </label>
                        <input type="number" name="parametros[{{ $parametro->id }}][valor]"
                               value="{{ old('parametros.' . $parametro->id . '.valor', $parametro->valor) }}"
                               class="form-control" placeholder="0" step="0.000001"
                               @if(!$parametro->editable) disabled @endif>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
                            {{ $parametro->descripcion }}
                        </div>
                    </div>
                    @endforeach
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
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="number" name="parametros[{{ $parametro->id }}][valor]"
                               value="{{ old('parametros.' . $parametro->id . '.valor', $parametro->valor) }}"
                               class="form-control" placeholder="0" step=".01" min="0"
                               @if(!$parametro->editable) disabled @endif>
                        <span style="font-weight:600">$</span>
                    </div>
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
            <strong>Nota:</strong> Todos los cambios son registrados automáticamente en la auditoría del sistema. Cada parámetro mantiene su historial de versiones para poder rastrear cambios anteriores.
        </div>
    </div>
</div>

<script>
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
