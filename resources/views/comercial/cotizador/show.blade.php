@extends('layouts.app')
@section('title', 'Cotización ' . $cotizacion->numero)
@push('styles')
<style>
    .quote-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }

    .quote-detail-section {
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        overflow: hidden;
        background: var(--surface-color);
    }

    .quote-detail-title {
        padding: .65rem .8rem;
        border-bottom: 1px solid var(--surface-border);
        background: var(--hover-bg, #f9fafb);
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .quote-detail-line {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        padding: .62rem .8rem;
        border-bottom: 1px solid var(--surface-border);
    }

    .quote-detail-line:last-child {
        border-bottom: 0;
    }

    .quote-detail-line.is-total {
        background: color-mix(in srgb, var(--primary-color, #0f1b4c) 8%, var(--surface-color));
    }

    .quote-detail-label {
        color: var(--text-primary);
        font-size: .88rem;
        font-weight: 700;
    }

    .quote-detail-meta {
        margin-top: .15rem;
        color: var(--text-muted);
        font-size: .73rem;
        line-height: 1.35;
    }

    .quote-detail-value {
        align-self: center;
        color: var(--text-primary);
        font-size: .92rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .quote-context-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) minmax(260px, 1fr) minmax(260px, 1fr);
        gap: .85rem;
    }

    .quote-context-panel {
        border: 1px solid var(--surface-border);
        border-radius: 8px;
        background: var(--surface-color);
        padding: .9rem;
        min-height: 126px;
    }

    .quote-context-label {
        color: var(--text-muted);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .quote-context-main {
        margin-top: .35rem;
        color: var(--text-primary);
        font-size: .95rem;
        font-weight: 850;
    }

    .quote-context-copy,
    .quote-context-list {
        margin-top: .35rem;
        color: var(--text-muted);
        font-size: .82rem;
        line-height: 1.4;
    }

    .quote-context-list {
        display: grid;
        gap: .35rem;
    }

    .quote-context-list a {
        color: var(--accent-primary);
        text-decoration: none;
        font-weight: 700;
    }

    @media (max-width: 900px) {
        .quote-detail-grid {
            grid-template-columns: 1fr;
        }

        .quote-context-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush
@section('content')
@php
    $puedeCrearComercial = auth()->user()->tieneAcceso('comercial', 'puede_crear');
    $puedeEditarComercial = auth()->user()->tieneAcceso('comercial', 'puede_editar');
    $puedeEliminarComercial = auth()->user()->esAdminSistema()
        && auth()->user()->tieneAcceso('comercial', 'puede_eliminar');
@endphp
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
            @if($puedeCrearComercial)
            <form method="POST" action="{{ route('comercial.cotizaciones.duplicar', $cotizacion) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn-secondary" onclick="return confirm('Se creará una nueva cotización en borrador usando estos datos como base. ¿Continuar?')">
                    <i class="bi bi-files"></i> Duplicar
                </button>
            </form>
            @endif
            @if($cotizacion->estado === 'vigente' && $puedeEditarComercial)
            <button type="button" class="btn-secondary" onclick="enviarPorEmail()">
                <i class="bi bi-envelope-fill"></i> Enviar Email
            </button>
            @endif
            @if($cotizacion->estado === 'en_cotizacion' && $puedeEditarComercial)
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
    @php
        $resumen = $cotizacion->datos_calculo['resumen_excel'] ?? [];
        $horas = $cotizacion->datos_calculo['horas'] ?? [];
        $detallesCalculo = collect($cotizacion->datos_calculo['detalles'] ?? $cotizacion->detalles->toArray());
        $cotizacionEstado = \App\Modules\Comercial\Models\Cotizacion::class;
        $estadoOperativo = $cotizacion->estadoOperativo();
        $valor = fn($monto) => '$' . number_format((float) ($monto ?? 0), 0, ',', '.');
        $porcentaje = fn($pct) => number_format((float) ($pct ?? 0), 2, ',', '.') . '%';
        $numero = fn($valor, $decimales = 2) => number_format((float) ($valor ?? 0), $decimales, ',', '.');
        $estadoTexto = fn($estado) => $cotizacionEstado::etiquetaEstado($estado);
        $estadoBadge = fn($estado) => $cotizacionEstado::badgeEstado($estado);
        $vigenciaInicio = $cotizacion->fecha_vigencia ?? $cotizacion->fecha_aprobacion ?? $cotizacion->fecha_vigencia_desde ?? $cotizacion->fecha_cotizacion;
        $vigenciaFinReal = $cotizacion->fecha_fin_vigencia_real ?? ($estadoOperativo === 'no_vigente' ? $cotizacion->fecha_cancelacion : null);
        $periodoActivo = ($vigenciaInicio ? $vigenciaInicio->format('d/m/Y') : '-') . ' - ' . (
            $vigenciaFinReal
                ? $vigenciaFinReal->format('d/m/Y')
                : ($estadoOperativo === 'vigente' ? 'activa' : 'sin activación')
        );
        $comparacionVigente = null;
        if ($vigenteComercialActual && $estadoOperativo !== 'vigente') {
            $diferenciaPrecio = (float) $cotizacion->precio_venta - (float) $vigenteComercialActual->precio_venta;
            $diferenciaPct = (float) $vigenteComercialActual->precio_venta > 0
                ? ($diferenciaPrecio / (float) $vigenteComercialActual->precio_venta) * 100
                : null;
            $signoPrecio = $diferenciaPrecio > 0 ? '+' : ($diferenciaPrecio < 0 ? '-' : '');
            $comparacionVigente = [
                'diferencia' => $diferenciaPrecio,
                'porcentaje' => $diferenciaPct,
                'texto' => $signoPrecio . $valor(abs($diferenciaPrecio))
                    . ($diferenciaPct !== null ? ' (' . ($diferenciaPct >= 0 ? '+' : '') . number_format($diferenciaPct, 2, ',', '.') . '%)' : ''),
            ];
        }
        $confirmarHacerVigente = '¿Aprobar y dejar vigente esta cotización?';
        if ($vigenteComercialActual && $comparacionVigente) {
            $confirmarHacerVigente .= " Reemplazará {$vigenteComercialActual->numero}. Diferencia de precio: {$comparacionVigente['texto']}.";
        }
        $get = fn($item, $key) => is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
        $detallePor = fn($texto) => $detallesCalculo->first(fn($detalle) => str_contains(mb_strtolower((string) $get($detalle, 'concepto')), mb_strtolower($texto)));
        $detalleValor = fn($texto) => (float) ($get($detallePor($texto), 'valor') ?? 0);
        $margenPct = (float) ($cotizacion->datos_calculo['margen_porcentaje'] ?? 0);
        $costoBrutoHhee = (float) ($resumen['costoBrutoHhee'] ?? (($resumen['totalImponible'] ?? 0) + (float) $cotizacion->total_cotizaciones));
        $margenHhee = (float) ($resumen['margenHhee'] ?? ($costoBrutoHhee * ($margenPct / 100)));
        $precioVentaHhee = (float) ($resumen['precioVentaHhee'] ?? ($costoBrutoHhee + $margenHhee));
        $jornadaSemanalHhee = $horas['jornada_semanal_hhee'] ?? ($resumen['jornadaSemanalHhee'] ?? null);
        $factorHoraNormalHhee = $horas['factor_normal_hhee'] ?? ($resumen['horaNormalFactorHhee'] ?? null);
        $metaHoraHhee = $jornadaSemanalHhee
            ? 'Base HHEE ' . $valor($precioVentaHhee) . ' x factor ' . $numero($factorHoraNormalHhee, 6) . ' (' . $numero($jornadaSemanalHhee) . ' hrs/sem)'
            : 'Base HHEE antes de recargos';
        $detalleMeta = function ($texto, $fallback = '') use ($detallePor, $get, $valor, $porcentaje) {
            $detalle = $detallePor($texto);
            if (! $detalle) {
                return $fallback;
            }

            $meta = [];
            if ((float) ($get($detalle, 'valor_base') ?? 0) > 0) {
                $meta[] = 'Base ' . $valor($get($detalle, 'valor_base'));
            }
            if ($get($detalle, 'porcentaje') !== null) {
                $meta[] = $porcentaje($get($detalle, 'porcentaje'));
            }
            $formula = $get($detalle, 'formula');
            $formulaTexto = is_array($formula) ? ($formula['descripcion'] ?? null) : null;
            if ($formulaTexto) {
                $meta[] = $formulaTexto;
            }

            return implode(' · ', $meta) ?: $fallback;
        };

        $seccionesCalculo = [
            'Haberes, descuentos e impuestos' => [
                ['Gratificación legal', $resumen['gratificacion'] ?? $detalleValor('Gratificación'), $detalleMeta('Gratificación', '25% con tope legal')],
                ['Total imponible', $resumen['totalImponible'] ?? 0, 'Sueldo base + bonos + gratificación', true],
                ['Total no imponible', $resumen['totalNoImponible'] ?? 0, 'Movilización + colación'],
                ['Total haberes', $resumen['totalHaberes'] ?? $cotizacion->total_remuneraciones, 'Total imponible + total no imponible', true],
                ['Imposiciones', $resumen['imposiciones'] ?? 0, 'Descuento trabajador configurado en parámetros'],
                ['Alcance líquido', $resumen['alcanceLiquido'] ?? 0, 'Total haberes - imposiciones - IU', true],
                ['Renta tributable', $resumen['rentaTributable'] ?? 0, 'Total imponible - imposiciones'],
                ['Impuesto Único (IU)', $resumen['impuestoUnico'] ?? 0, 'Factor y rebaja configurados en mantenedor'],
            ],
            'Seguros, provisiones y gastos' => [
                ['REFPREV', $resumen['refprev'] ?? $detalleValor('REFPREV'), $detalleMeta('REFPREV')],
                ['Seg. Inv. y Sob. (SIS)', $resumen['sis'] ?? $detalleValor('SIS'), $detalleMeta('SIS')],
                ['Mutual Seguridad I.S.T.', $resumen['mutual'] ?? $detalleValor('Mutual'), $detalleMeta('Mutual')],
                ['Seguro Cesantía', $resumen['seguroCesantia'] ?? $detalleValor('Cesantía'), $detalleMeta('Cesantía')],
                ['Total cotizaciones (ISES)', $cotizacion->total_cotizaciones, 'REFPREV + SIS + Mutual + Cesantía', true],
                ['Provisión Vacaciones', $resumen['provisionVacaciones'] ?? $detalleValor('Vacaciones'), $detalleMeta('Vacaciones')],
                ['Provisión Indemnizaciones', $resumen['provisionIndemnizaciones'] ?? $detalleValor('Indemnizaciones'), $detalleMeta('Indemnizaciones', 'Aplica principalmente en SUB')],
                ['Total provisiones', $cotizacion->total_provisiones, 'Vacaciones + indemnizaciones cuando aplique', true],
                ['Seguro Accidentes Personales', $detalleValor('Accidentes'), $detalleMeta('Accidentes', 'Valor ingresado')],
                ['Otros Seguros / Gastos', $detalleValor('Otros Gastos'), $detalleMeta('Otros Gastos', 'Valor ingresado')],
                ['Otros Beneficios / Aguinaldos', $detalleValor('Otros Beneficios'), $detalleMeta('Otros Beneficios', 'Valor ingresado')],
                ['Gastos Administración', $resumen['gastosAdministracion'] ?? $detalleValor('Administración'), $detalleMeta('Administración')],
                ['Total gastos operacionales', $cotizacion->total_gastos, 'Uniformes + casino + seguros + beneficios + administración', true],
            ],
            'Precio y margen' => [
                ['Costo bruto normal', $resumen['costoBruto'] ?? $cotizacion->subtotal, 'Haberes + ISES + provisiones + gastos', true],
                ['Margen operacional normal', $resumen['margen'] ?? $cotizacion->margen, $porcentaje($cotizacion->datos_calculo['margen_porcentaje'] ?? 0)],
                ['Precio venta normal', $resumen['precioVenta'] ?? $cotizacion->precio_venta, 'Costo bruto normal + margen operacional', true],
                ['Costo bruto HHEE', $costoBrutoHhee, 'Total imponible + cotizaciones empresa', true],
                ['Margen operacional HHEE', $margenHhee, $porcentaje($margenPct)],
                ['Precio venta HHEE', $precioVentaHhee, 'Base para horas extra', true],
            ],
            'Valores hora' => [
                ['Hora normal', $horas['normal'] ?? ($resumen['horaNormal'] ?? 0), 'Precio venta / horas mensuales'],
                ['Hora normal HHEE', $horas['normal_hhee'] ?? ($resumen['horaNormalHhee'] ?? 0), $metaHoraHhee],
                ['Hora extra 50%', $horas['extra_50'] ?? ($resumen['horaExtra50'] ?? 0), 'Hora normal HHEE x 1,5'],
                ['Hora extra 100%', $horas['extra_100'] ?? ($resumen['horaExtra100'] ?? 0), 'Hora normal HHEE x 2'],
            ],
        ];
    @endphp

    <div class="glass-card" style="margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <div>
                <h3 style="margin:0;font-size:1rem">Contexto de vigencia comercial</h3>
                <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.86rem">
                    Cliente, centro de costo, modalidad y cargo forman la llave operativa para decidir qué tarifa queda activa.
                </p>
            </div>
            <a href="{{ route('comercial.cotizaciones.index', ['cliente_id' => $cotizacion->cliente_id, 'q' => $cotizacion->cargo]) }}" class="btn-secondary">
                <i class="bi bi-search"></i> Ver contexto
            </a>
        </div>

        <div class="quote-context-grid">
            <div class="quote-context-panel">
                <div class="quote-context-label">Estado de esta cotización</div>
                <div class="quote-context-main">
                    <span class="badge {{ $estadoBadge($cotizacion->estado) }}">{{ $estadoTexto($cotizacion->estado) }}</span>
                </div>
                <div class="quote-context-copy">
                    Periodo activo: <strong>{{ $periodoActivo }}</strong>
                </div>
                <div class="quote-context-copy">
                    @if($estadoOperativo === 'vigente')
                        Esta cotización es la tarifa vigente para esta combinación comercial.
                    @elseif($estadoOperativo === 'no_vigente')
                        Esta cotización quedó almacenada como histórico porque fue reemplazada o perdió vigencia.
                    @else
                        Está en cotización. Al aprobarla, quedará vigente y reemplazará una vigente equivalente si existe.
                    @endif
                </div>
            </div>

            <div class="quote-context-panel">
                <div class="quote-context-label">Tarifa vigente relacionada</div>
                @if($estadoOperativo === 'vigente')
                    <div class="quote-context-main">{{ $cotizacion->numero }}</div>
                    <div class="quote-context-copy">{{ $valor($cotizacion->precio_venta) }} · vigente desde {{ $cotizacion->fecha_vigencia?->format('d/m/Y') ?? '-' }}</div>
                @elseif($vigenteComercialActual)
                    <div class="quote-context-main">
                        <a href="{{ route('comercial.cotizaciones.show', $vigenteComercialActual) }}" style="color:var(--accent-primary);text-decoration:none">
                            {{ $vigenteComercialActual->numero }}
                        </a>
                    </div>
                    <div class="quote-context-copy">{{ $valor($vigenteComercialActual->precio_venta) }} · vigente desde {{ $vigenteComercialActual->fecha_vigencia?->format('d/m/Y') ?? '-' }}</div>
                    @if($comparacionVigente)
                    <div class="quote-context-copy">
                        Diferencia si se activa esta cotización:
                        <strong style="color:{{ $comparacionVigente['diferencia'] >= 0 ? 'var(--danger-color)' : 'var(--success-color)' }}">
                            {{ $comparacionVigente['texto'] }}
                        </strong>
                    </div>
                    @endif
                @else
                    <div class="quote-context-main">Sin tarifa vigente</div>
                    <div class="quote-context-copy">No hay otra cotización vigente para esta misma combinación comercial.</div>
                @endif
            </div>

            <div class="quote-context-panel">
                <div class="quote-context-label">Histórico relacionado</div>
                <div class="quote-context-main">{{ number_format($relacionadasCount, 0, ',', '.') }} cotización(es) relacionadas</div>
                @if($historicasComerciales->isNotEmpty())
                    <div class="quote-context-list">
                        @foreach($historicasComerciales->take(3) as $historica)
                        <div>
                            <a href="{{ route('comercial.cotizaciones.show', $historica) }}">{{ $historica->numero }}</a>
                            <span> · {{ $estadoTexto($historica->estado) }} · {{ $valor($historica->precio_venta) }}</span>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="quote-context-copy">Aún no hay cotizaciones históricas equivalentes.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Estado y Acciones --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
            <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
                <div>
                    <div style="font-size:.85rem;color:var(--text-muted)">Estado Actual</div>
                    <span class="badge {{
                        $estadoBadge($cotizacion->estado)
                    }}" style="font-size:1rem;padding:.5rem 1rem">
                        {{ $estadoTexto($cotizacion->estado) }}
                    </span>
                </div>
                <div>
                    <div style="font-size:.85rem;color:var(--text-muted)">Versión</div>
                    <strong style="font-size:1.1rem">{{ $cotizacion->version }}</strong>
                </div>
            </div>

            <div style="display:flex;gap:.5rem">
                @if($estadoOperativo === 'en_cotizacion' && $puedeEditarComercial)
                <form method="POST" action="{{ route('comercial.cotizaciones.aprobar', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-premium" onclick="return confirm(@js($confirmarHacerVigente))">
                        <i class="bi bi-check-circle-fill"></i> Aprobar y hacer vigente
                    </button>
                </form>
                @endif

                @if($cotizacion->estado === 'aprobada' && $puedeEditarComercial)
                <form method="POST" action="{{ route('comercial.cotizaciones.hacer-vigente', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-premium" onclick="return confirm(@js($confirmarHacerVigente))">
                        <i class="bi bi-play-circle-fill"></i> Hacer Vigente
                    </button>
                </form>
                @endif

                @if($estadoOperativo === 'en_cotizacion' && $puedeEditarComercial)
                <form method="POST" action="{{ route('comercial.cotizaciones.rechazar', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="motivo">
                    <button type="submit" class="btn-secondary" onclick="return solicitarMotivo(this, 'Indica el motivo para marcar esta cotización como no vigente:')">
                        <i class="bi bi-x-circle-fill"></i> Marcar no vigente
                    </button>
                </form>
                @endif

                @if($estadoOperativo === 'vigente' && $puedeEditarComercial)
                <form method="POST" action="{{ route('comercial.cotizaciones.cancelar', $cotizacion) }}" style="display:inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="motivo">
                    <button type="submit" class="btn-secondary" onclick="return solicitarMotivo(this, 'Indica el motivo para marcar esta cotización como no vigente:')">
                        <i class="bi bi-stop-circle-fill"></i> Marcar no vigente
                    </button>
                </form>
                @endif

                @if($puedeEliminarComercial)
                <form method="POST" action="{{ route('comercial.cotizaciones.destroy', $cotizacion) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <input type="hidden" name="motivo">
                    <button type="submit" class="btn-secondary" style="color:var(--danger-color)" onclick="return solicitarMotivo(this, 'Solo administradores pueden eliminar cotizaciones. Indica el motivo de eliminación:')">
                        <i class="bi bi-trash-fill"></i> Eliminar
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

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Periodo activo real</div>
                <strong>{{ $periodoActivo }}</strong>
                <div style="font-size:.85rem;color:var(--text-muted)">Base para búsquedas históricas de tarifa</div>
            </div>
        </div>

        @if($cotizacion->observaciones)
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--surface-border)">
            <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Observaciones</div>
            <p style="margin:0;font-style:italic">{{ $cotizacion->observaciones }}</p>
        </div>
        @endif
    </div>

    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem;display:flex;align-items:center;gap:.5rem">
            <i class="bi bi-table"></i> Desglose de cálculo tipo Excel
        </h3>

        <div class="quote-detail-grid">
            @foreach($seccionesCalculo as $titulo => $lineas)
            <div class="quote-detail-section">
                <div class="quote-detail-title">{{ $titulo }}</div>
                @foreach($lineas as $linea)
                <div class="quote-detail-line {{ ($linea[3] ?? false) ? 'is-total' : '' }}">
                    <div>
                        <div class="quote-detail-label">{{ $linea[0] }}</div>
                        @if(!empty($linea[2]))
                        <div class="quote-detail-meta">{{ $linea[2] }}</div>
                        @endif
                    </div>
                    <div class="quote-detail-value">{{ $valor($linea[1]) }}</div>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Detalle por concepto guardado</h3>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Base</th>
                        <th>%</th>
                        <th>Valor</th>
                        <th>Fórmula / referencia</th>
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
                        <td>{{ $valor($detalle->valor_base) }}</td>
                        <td>{{ $detalle->porcentaje !== null ? $porcentaje($detalle->porcentaje) : '—' }}</td>
                        <td style="font-weight:700">{{ $valor($detalle->valor) }}</td>
                        <td style="font-size:.8rem;color:var(--text-muted);min-width:220px">
                            {{ $detalle->formula['descripcion'] ?? '—' }}
                        </td>
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

function solicitarMotivo(button, mensaje) {
    const form = button.closest('form');
    const motivo = window.prompt(mensaje);

    if (motivo === null) {
        return false;
    }

    const limpio = motivo.trim();
    if (limpio.length < 5) {
        alert('El motivo debe tener al menos 5 caracteres.');
        return false;
    }

    form.querySelector('input[name="motivo"]').value = limpio;

    return confirm('¿Confirmas esta acción? El motivo quedará registrado en la bitácora.');
}
</script>
@endsection
