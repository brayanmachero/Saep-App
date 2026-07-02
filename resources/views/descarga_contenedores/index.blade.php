@extends('layouts.app')
@section('title','Contenedores')
@section('content')
@php
    $puedeGestionarCostos = auth()->user()->puedeGestionarCostosDescargaContenedores();
    $emptyColspan = $puedeGestionarCostos ? 11 : 10;
@endphp
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Contenedores</h2>
            <p class="page-subheading">
                {{ $puedeGestionarCostos ? 'Gestión de descargas, equipos, códigos FACT y pagos asociados.' : 'Registro operativo de descargas y equipos participantes.' }}
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            @if(auth()->user()->tieneAcceso('descarga_contenedores', 'puede_crear'))
            <a href="{{ route('descarga-contenedores.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nuevo registro
            </a>
            @endif
        </div>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')

    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3>{{ $stats['total'] }}</h3><p>Registros</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-pencil-square"></i></div>
            <div class="stat-info"><h3>{{ $stats['borradores'] }}</h3><p>Borradores</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-info"><h3>{{ $stats['validadas'] }}</h3><p>Validadas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-shield-check"></i></div>
            <div class="stat-info"><h3>{{ $stats['listos_validar'] }}</h3><p>Listos para validar</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-people"></i></div>
            <div class="stat-info"><h3>{{ $stats['participantes'] }}</h3><p>Participaciones</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-clipboard-x"></i></div>
            <div class="stat-info"><h3>{{ $stats['pendientes_validar'] }}</h3><p>Pendientes</p></div>
        </div>
        @if($puedeGestionarCostos)
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-info"><h3>{{ $stats['revision_tarifa'] }}</h3><p>Tarifa por revisar</p></div>
        </div>
        @endif
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-person-dash"></i></div>
            <div class="stat-info"><h3>{{ $stats['sin_equipo'] }}</h3><p>Sin equipo</p></div>
        </div>
        @if($puedeGestionarCostos)
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info"><h3>${{ number_format((float) $stats['pago_total'], 0, ',', '.') }}</h3><p>Pago total ref.</p></div>
        </div>
        @endif
    </div>

    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('descarga-contenedores.index') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Contenedor, bodega, trabajador, FACT...">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Centro</label>
                <select name="centro_costo_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($centros as $centro)
                        <option value="{{ $centro->id }}" {{ request('centro_costo_id') == $centro->id ? 'selected' : '' }}>{{ $centro->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach(['borrador' => 'Borrador', 'validado' => 'Validado', 'cerrado' => 'Cerrado', 'liquidado' => 'Liquidado'] as $value => $label)
                        <option value="{{ $value }}" {{ request('estado') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Validación</label>
                <select name="validacion_estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="listos" {{ request('validacion_estado') === 'listos' ? 'selected' : '' }}>Listos</option>
                    <option value="pendientes" {{ request('validacion_estado') === 'pendientes' ? 'selected' : '' }}>Pendientes</option>
                </select>
            </div>
            @if($puedeGestionarCostos)
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Tarifa</label>
                <select name="tarifa_estado" class="form-control">
                    <option value="">Todas</option>
                    <option value="revision" {{ request('tarifa_estado') === 'revision' ? 'selected' : '' }}>Por revisar</option>
                    <option value="sin_tarifa" {{ request('tarifa_estado') === 'sin_tarifa' ? 'selected' : '' }}>Sin tarifa</option>
                    <option value="con_tarifa" {{ request('tarifa_estado') === 'con_tarifa' ? 'selected' : '' }}>Con tarifa</option>
                </select>
            </div>
            @endif
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Equipo</label>
                <select name="equipo_estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="sin_equipo" {{ request('equipo_estado') === 'sin_equipo' ? 'selected' : '' }}>Sin equipo</option>
                    <option value="con_equipo" {{ request('equipo_estado') === 'con_equipo' ? 'selected' : '' }}>Con equipo</option>
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="form-control">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="form-control">
            </div>
            <button type="submit" class="btn-premium" style="height:fit-content"><i class="bi bi-search"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','centro_costo_id','estado','validacion_estado','tarifa_estado','equipo_estado','fecha_desde','fecha_hasta']))
                <a href="{{ route('descarga-contenedores.index') }}" class="btn-secondary" style="height:fit-content"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Contenedor</th>
                        <th>Bodega</th>
                        <th>Equipo</th>
                        <th>FACT.</th>
                        @if($puedeGestionarCostos)
                        <th>Pago</th>
                        @endif
                        <th>Cajas</th>
                        <th>Trab.</th>
                        <th>Pendientes</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($descargas as $descarga)
                    @php($blockers = $descarga->validationBlockers())
                    <tr>
                        <td>{{ $descarga->fecha?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <strong>{{ $descarga->contenedor ?: 'Sin contenedor' }}</strong>
                            <div style="font-size:.75rem;color:var(--text-muted)">{{ $descarga->operacion ?: 'Sin operación' }}</div>
                        </td>
                        <td>
                            {{ $descarga->bodega ?: ($descarga->centroCosto->nombre ?? '—') }}
                            @if($descarga->producto)
                                <div style="font-size:.75rem;color:var(--text-muted)">{{ $descarga->producto }}</div>
                            @endif
                        </td>
                        <td>{{ $descarga->equipo_descarga ?: '—' }}</td>
                        <td>
                            <code>{{ $descarga->fact_codigo ?: '—' }}</code>
                            @if($puedeGestionarCostos)
                            @if($descarga->tarifa_cliente_snapshot || $descarga->tarifa_proceso_snapshot)
                                <div style="font-size:.72rem;color:var(--text-muted)">
                                    {{ $descarga->tarifa_cliente_snapshot }}{{ $descarga->tarifa_proceso_snapshot ? ' · '.$descarga->tarifa_proceso_snapshot : '' }}
                                </div>
                            @endif
                            @endif
                        </td>
                        @if($puedeGestionarCostos)
                        <td>
                            @if($descarga->requiere_revision_tarifa)
                                <span class="badge warning">Revisar</span>
                            @elseif($descarga->pago_colaborador_snapshot !== null)
                                ${{ number_format((float) $descarga->pago_colaborador_snapshot, 0, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        @endif
                        <td>{{ $descarga->cajas !== null ? number_format($descarga->cajas, 0, ',', '.') : '—' }}</td>
                        <td>
                            @if($descarga->participantes_count === 0)
                                <span class="badge warning">Sin equipo</span>
                            @else
                                {{ $descarga->participantes_count }}
                            @endif
                        </td>
                        <td>
                            @if($descarga->estado !== 'borrador')
                                <span class="badge success">OK</span>
                            @elseif($blockers->isEmpty())
                                <span class="badge success">Listo</span>
                            @else
                                <div class="pending-list">
                                    @foreach($blockers->take(2) as $blocker)
                                        <span class="badge warning">{{ ucfirst($blocker) }}</span>
                                    @endforeach
                                    @if($blockers->count() > 2)
                                        <span class="badge warning">+{{ $blockers->count() - 2 }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td><span class="{{ $descarga->estadoBadge['class'] }}">{{ $descarga->estadoBadge['label'] }}</span></td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('descarga-contenedores.show', $descarga) }}" class="icon-btn" title="Ver"><i class="bi bi-eye-fill"></i></a>
                            @if(auth()->user()->tieneAcceso('descarga_contenedores', 'puede_editar'))
                                @if($descarga->estado === 'borrador')
                                    @if($blockers->isEmpty())
                                        <form method="POST" action="{{ route('descarga-contenedores.validar', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Validar este registro de contenedor?')">
                                            @csrf @method('PATCH')
                                            <button class="icon-btn validation-ready" title="Validar"><i class="bi bi-check2-circle"></i></button>
                                        </form>
                                    @else
                                        <button class="icon-btn validation-disabled" title="Pendiente: {{ $blockers->implode(', ') }}" disabled><i class="bi bi-check2-circle"></i></button>
                                    @endif
                                @elseif($descarga->estado === 'validado')
                                    <form method="POST" action="{{ route('descarga-contenedores.volver-borrador', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Devolver este registro a borrador?')">
                                        @csrf @method('PATCH')
                                        <button class="icon-btn" title="Volver a borrador"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </form>
                                @endif
                                <a href="{{ route('descarga-contenedores.edit', $descarga) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            @endif
                            @if(auth()->user()->tieneAcceso('descarga_contenedores', 'puede_eliminar'))
                                <form method="POST" action="{{ route('descarga-contenedores.destroy', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Eliminar esta descarga?')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            No hay descargas registradas.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($descargas->hasPages())
            <div style="padding:1rem 0">{{ $descargas->links() }}</div>
        @endif
    </div>
</div>
@endsection
