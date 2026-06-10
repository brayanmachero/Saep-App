@extends('layouts.app')
@section('title', 'Cotización ' . $cotizacion->numero)
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $cotizacion->numero }}</h2>
            <p class="page-subheading">
                {{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }} •
                {{ $cotizacion->cargo }} •
                {{ $cotizacion->modalidad->codigo }} •
                {{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('comercial.cotizaciones.pdf', $cotizacion) }}" class="btn-secondary" target="_blank">
                <i class="bi bi-file-pdf-fill"></i> Descargar PDF
            </a>
            @if($cotizacion->estado === 'vigente')
            <button type="button" class="btn-secondary" onclick="enviarPorEmail()">
                <i class="bi bi-envelope-fill"></i> Enviar Email
            </button>
            @endif
            @if($cotizacion->estado === 'en_cotizacion')
            <a href="{{ route('comercial.cotizaciones.edit', $cotizacion) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            @endif
            <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @include('partials._alerts')

    {{-- Estado y Acciones --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
            <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
                <div>
                    <div style="font-size:.85rem;color:var(--text-muted)">Estado Actual</div>
                    <span class="badge {{
                        $cotizacion->estado === 'vigente' ? 'badge-success' :
                        ($cotizacion->estado === 'aprobada' ? 'badge-info' :
                        ($cotizacion->estado === 'en_cotizacion' ? 'badge-warning' : 'badge-danger'))
                    }}" style="font-size:1rem;padding:.5rem 1rem">
                        {{ ucfirst(str_replace('_', ' ', $cotizacion->estado)) }}
                    </span>
                </div>
                <div>
                    <div style="font-size:.85rem;color:var(--text-muted)">Versión</div>
                    <strong style="font-size:1.1rem">{{ $cotizacion->version }}</strong>
                </div>
            </div>

            <div style="display:flex;gap:.5rem">
                @if($cotizacion->estado === 'en_cotizacion')
                <form method="POST" action="{{ route('comercial.cotizaciones.aprobar', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-premium" onclick="return confirm('¿Aprobar esta cotización?')">
                        <i class="bi bi-check-circle-fill"></i> Aprobar
                    </button>
                </form>
                @endif

                @if($cotizacion->estado === 'aprobada')
                <form method="POST" action="{{ route('comercial.cotizaciones.hacer-vigente', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-premium" onclick="return confirm('¿Hacer vigente esta cotización?')">
                        <i class="bi bi-play-circle-fill"></i> Hacer Vigente
                    </button>
                </form>
                @endif

                @if($cotizacion->estado === 'vigente')
                <form method="POST" action="{{ route('comercial.cotizaciones.cancelar', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-secondary" onclick="return confirm('¿Cancelar esta cotización?')">
                        <i class="bi bi-x-circle-fill"></i> Cancelar
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Datos de la Cotización --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Información General</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem">
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Cliente</div>
                <strong>{{ $cotizacion->cliente->nombre_comercial ?? $cotizacion->cliente->nombre }}</strong>
                <div style="font-size:.85rem;color:var(--text-muted)">RUT: {{ $cotizacion->cliente->rut }}</div>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Centro de Costo</div>
                <strong>{{ $cotizacion->centroCosto->nombre }}</strong>
                <div style="font-size:.85rem;color:var(--text-muted)">Código: {{ $cotizacion->centroCosto->codigo }}</div>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Cargo / Puesto</div>
                <strong>{{ $cotizacion->cargo }}</strong>
                @if($cotizacion->titulo)
                <div style="font-size:.85rem;color:var(--text-muted)">{{ $cotizacion->titulo }}</div>
                @endif
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Modalidad</div>
                <span class="badge {{ $cotizacion->modalidad->codigo === 'EST' ? 'badge-info' : 'badge-warning' }}">
                    {{ $cotizacion->modalidad->codigo }}
                </span>
                <div style="font-size:.85rem">{{ $cotizacion->modalidad->nombre }}</div>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Creada por</div>
                <strong>{{ $cotizacion->usuario->name ?? 'Sistema' }}</strong>
                <div style="font-size:.85rem;color:var(--text-muted)">{{ $cotizacion->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        @if($cotizacion->observaciones)
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--surface-border)">
            <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Observaciones</div>
            <p style="margin:0;font-style:italic">{{ $cotizacion->observaciones }}</p>
        </div>
        @endif
    </div>

    {{-- Detalles de Cálculo --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Desglose de Cálculo</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem">
            <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--accent-primary)">
                <div style="font-size:.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Total Remuneraciones</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:.5rem">${{ number_format($cotizacion->total_remuneraciones, 0, ',', '.') }}</div>
            </div>

            <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--accent-secondary)">
                <div style="font-size:.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Cotizaciones (ISES)</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:.5rem">${{ number_format($cotizacion->total_cotizaciones, 0, ',', '.') }}</div>
            </div>

            <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--warning-color)">
                <div style="font-size:.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Provisiones</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:.5rem">${{ number_format($cotizacion->total_provisiones, 0, ',', '.') }}</div>
            </div>

            <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--danger-color)">
                <div style="font-size:.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Gastos Operacionales</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:.5rem">${{ number_format($cotizacion->total_gastos, 0, ',', '.') }}</div>
            </div>

            <div style="background:var(--bg-tertiary);padding:1rem;border-radius:.5rem;border-left:4px solid var(--success-color)">
                <div style="font-size:.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Subtotal</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:.5rem">${{ number_format($cotizacion->subtotal, 0, ',', '.') }}</div>
            </div>

            <div style="background:var(--accent-primary);color:white;padding:1rem;border-radius:.5rem">
                <div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;opacity:.9">Margen</div>
                <div style="font-size:1.1rem;font-weight:700;margin-top:.25rem">{{ number_format($cotizacion->datos_calculo['margen_porcentaje'] ?? 0, 2) }}%</div>
                <div style="font-size:.9rem;margin-top:.5rem">${{ number_format($cotizacion->datos_calculo['margen'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Precio Venta Destacado --}}
        <div style="background:linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));color:white;padding:2rem;border-radius:.75rem;text-align:center">
            <div style="font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;opacity:.9;margin-bottom:.5rem">PRECIO VENTA FINAL</div>
            <div style="font-size:2.5rem;font-weight:700">${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- Tabla de Detalles --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Detalle por Concepto</h3>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Valor Base</th>
                        <th>Porcentaje</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cotizacion->detalles as $detalle)
                    <tr>
                        <td>
                            <span class="badge {{
                                $detalle->tipo === 'remuneracion' ? 'badge-info' :
                                ($detalle->tipo === 'cotizacion' ? 'badge-warning' :
                                ($detalle->tipo === 'provision' ? 'badge-danger' : 'badge-secondary'))
                            }}">
                                {{ ucfirst($detalle->tipo) }}
                            </span>
                        </td>
                        <td><strong>{{ $detalle->concepto }}</strong></td>
                        <td>${{ number_format($detalle->valor_base, 0, ',', '.') }}</td>
                        <td>{{ number_format($detalle->porcentaje, 2) }}%</td>
                        <td style="font-weight:600">${{ number_format($detalle->valor, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Historial de Versiones --}}
    @if($cotizacion->cotizacionesPosteriores->count() > 0 || $cotizacion->cotizacion_anterior_id)
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Historial de Versiones</h3>
        <a href="{{ route('comercial.cotizaciones.historico', $cotizacion) }}" class="btn-secondary">
            <i class="bi bi-clock-history"></i> Ver Todas las Versiones
        </a>
    </div>
    @endif

    {{-- Auditoría --}}
    @if($cotizacion->auditorias->count() > 0)
    <div class="glass-card">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Bitácora de la Cotización</h3>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Acción</th>
                        <th>Usuario</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cotizacion->auditorias->sortByDesc('created_at') as $auditoria)
                    <tr>
                        <td>
                            <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $auditoria->accion)) }}</span>
                        </td>
                        <td>{{ $auditoria->usuario->name ?? 'Sistema' }}</td>
                        <td style="font-size:.9rem">{{ $auditoria->created_at->format('d/m/Y H:i:s') }}</td>
                        <td style="font-size:.85rem;color:var(--text-muted)">{{ $auditoria->descripcion }}</td>
                        <td style="font-size:.8rem;color:var(--text-muted);min-width:220px">
                            @if($auditoria->cambios)
                                @foreach($auditoria->cambios as $clave => $valor)
                                    @if(is_scalar($valor) || $valor === null)
                                        <div><strong>{{ str_replace('_', ' ', ucfirst($clave)) }}:</strong> {{ $valor ?? 'N/A' }}</div>
                                    @elseif(is_array($valor))
                                        <div><strong>{{ str_replace('_', ' ', ucfirst($clave)) }}:</strong> {{ count($valor) }} dato(s)</div>
                                    @endif
                                @endforeach
                            @else
                                —
                            @endif
                        </td>
                        <td style="font-size:.8rem;color:var(--text-muted)">{{ $auditoria->ip_address ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- Modal para Enviar Email --}}
<div id="emailModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:1rem">
    <div style="background:var(--surface-color);border:1px solid var(--surface-border);border-radius:14px;padding:1.5rem;max-width:520px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.2)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h3 style="margin:0;font-size:1.1rem"><i class="bi bi-envelope-fill"></i> Enviar Cotización por Email</h3>
            <button type="button" onclick="document.getElementById('emailModal').style.display='none'"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <form method="POST" action="{{ route('comercial.cotizaciones.enviar-email', $cotizacion) }}">
            @csrf
            <div class="form-group">
                <label>Destinatario <span class="required">*</span></label>
                <input type="email" name="destinatario" value="{{ $cotizacion->cliente->email }}" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Asunto <span class="required">*</span></label>
                <input type="text" name="asunto" value="Cotización {{ $cotizacion->numero }}" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Mensaje (opcional)</label>
                <textarea name="mensaje" class="form-control" rows="4" placeholder="Mensaje adicional para el cliente..."></textarea>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
                <button type="button" class="btn-secondary" onclick="document.getElementById('emailModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-send-fill"></i> Enviar</button>
            </div>
        </form>
    </div>
</div>

<script>
function enviarPorEmail() {
    document.getElementById('emailModal').style.display = 'flex';
}
</script>
@endsection
