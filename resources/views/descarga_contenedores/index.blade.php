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
    @include('descarga_contenedores._context_help', [
        'title' => 'Flujo de trabajo',
        'items' => $puedeGestionarCostos
            ? [
                'Borrador: registro editable, creado manualmente o desde programación.',
                'Listo para validar: tiene datos mínimos, equipo y porcentajes al 100%.',
                'Validado: queda autorizado para revisión de liquidación.',
                'Liquidado: queda bloqueado para evitar cambios accidentales en pagos.',
            ]
            : [
                'Borrador: registro cargado para revisión de coordinación.',
                'Listo para validar: tiene datos mínimos, equipo y porcentajes al 100%.',
                'Validado: coordinación revisó el registro. Los valores económicos no son visibles para este perfil.',
            ],
    ])

    <div class="process-flow">
        <a href="{{ route('descarga-contenedores.index', ['validacion_estado' => 'pendientes']) }}" class="process-step warning">
            <i class="bi bi-clipboard-x"></i>
            <span>
                <strong>{{ $stats['pendientes_validar'] }}</strong>
                <small>Pendientes por completar</small>
            </span>
        </a>
        <a href="{{ route('descarga-contenedores.index', ['validacion_estado' => 'listos']) }}" class="process-step success">
            <i class="bi bi-shield-check"></i>
            <span>
                <strong>{{ $stats['listos_validar'] }}</strong>
                <small>Listos para validar</small>
            </span>
        </a>
        <a href="{{ route('descarga-contenedores.index', ['estado' => 'validado']) }}" class="process-step primary">
            <i class="bi bi-check2-circle"></i>
            <span>
                <strong>{{ $stats['validadas'] }}</strong>
                <small>{{ $puedeGestionarCostos ? 'Validadas para liquidar' : 'Validadas por coordinación' }}</small>
            </span>
        </a>
        @if($puedeGestionarCostos)
        <a href="{{ route('descarga-contenedores.index', ['tarifa_estado' => 'revision']) }}" class="process-step warning">
            <i class="bi bi-exclamation-triangle"></i>
            <span>
                <strong>{{ $stats['revision_tarifa'] }}</strong>
                <small>Tarifas por revisar</small>
            </span>
        </a>
        <a href="{{ route('descarga-contenedores.index', ['estado' => 'liquidado']) }}" class="process-step locked">
            <i class="bi bi-lock-fill"></i>
            <span>
                <strong>{{ $stats['liquidadas'] }}</strong>
                <small>Liquidadas y bloqueadas</small>
            </span>
        </a>
        @endif
    </div>

    <div class="stats-grid">
        <div class="glass-card stat-item" title="Total de registros de descarga guardados en el módulo.">
            <div class="stat-icon primary"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info"><h3>{{ $stats['total'] }}</h3><p>Registros</p></div>
        </div>
        <div class="glass-card stat-item" title="Registros pendientes de revisión o cierre por coordinación.">
            <div class="stat-icon warning"><i class="bi bi-pencil-square"></i></div>
            <div class="stat-info"><h3>{{ $stats['borradores'] }}</h3><p>Borradores</p></div>
        </div>
        <div class="glass-card stat-item" title="Registros revisados y autorizados para seguir el flujo.">
            <div class="stat-icon success"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-info"><h3>{{ $stats['validadas'] }}</h3><p>Validadas</p></div>
        </div>
        @if($puedeGestionarCostos)
        <div class="glass-card stat-item" title="Registros ya cerrados para pago referencial.">
            <div class="stat-icon success"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-info"><h3>{{ $stats['liquidadas'] }}</h3><p>Liquidadas</p></div>
        </div>
        @endif
        <div class="glass-card stat-item" title="Borradores sin pendientes de validación.">
            <div class="stat-icon success"><i class="bi bi-shield-check"></i></div>
            <div class="stat-info"><h3>{{ $stats['listos_validar'] }}</h3><p>Listos para validar</p></div>
        </div>
        <div class="glass-card stat-item" title="Cantidad total de asignaciones de trabajadores en descargas.">
            <div class="stat-icon primary"><i class="bi bi-people"></i></div>
            <div class="stat-info"><h3>{{ $stats['participantes'] }}</h3><p>Participaciones</p></div>
        </div>
        <div class="glass-card stat-item" title="Borradores con datos faltantes, equipo incompleto o porcentajes que no suman 100%.">
            <div class="stat-icon warning"><i class="bi bi-clipboard-x"></i></div>
            <div class="stat-info"><h3>{{ $stats['pendientes_validar'] }}</h3><p>Pendientes</p></div>
        </div>
        @if($puedeGestionarCostos)
        <div class="glass-card stat-item" title="Registros con código FACT manual, duplicado o marcado para revisión.">
            <div class="stat-icon warning"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-info"><h3>{{ $stats['revision_tarifa'] }}</h3><p>Tarifa por revisar</p></div>
        </div>
        @endif
        <div class="glass-card stat-item" title="Registros sin trabajadores asignados.">
            <div class="stat-icon warning"><i class="bi bi-person-dash"></i></div>
            <div class="stat-info"><h3>{{ $stats['sin_equipo'] }}</h3><p>Sin equipo</p></div>
        </div>
        @if($puedeGestionarCostos)
        <div class="glass-card stat-item" title="Suma referencial de pagos a colaboradores según tarifas asociadas.">
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
                <label style="font-size:.75rem;color:var(--text-muted)">Validación @include('descarga_contenedores._help_icon', ['text' => 'Listos: cumplen datos mínimos para validar. Pendientes: requieren completar información antes de validación.'])</label>
                <select name="validacion_estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="listos" {{ request('validacion_estado') === 'listos' ? 'selected' : '' }}>Listos</option>
                    <option value="pendientes" {{ request('validacion_estado') === 'pendientes' ? 'selected' : '' }}>Pendientes</option>
                </select>
            </div>
            @if($puedeGestionarCostos)
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Tarifa @include('descarga_contenedores._help_icon', ['text' => 'Permite separar registros con tarifa válida, sin tarifa o con código FACT que requiere revisión.'])</label>
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
                        <th title="Datos que faltan para validar el registro.">Pendientes</th>
                        <th title="Etapa actual del flujo operativo.">Estado</th>
                        <th title="Ver, validar, liquidar, editar o eliminar según permisos.">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($descargas as $descarga)
                    @php
                        $blockers = $descarga->validationBlockers();
                        $visibleBlockers = $blockers->map(function ($blocker) use ($puedeGestionarCostos) {
                            if ($puedeGestionarCostos) {
                                return $blocker;
                            }

                            return match ($blocker) {
                                'falta pago colaborador' => 'tarifa FACT pendiente',
                                'tarifa pendiente de revisión' => 'tarifa FACT por revisar',
                                default => $blocker,
                            };
                        });
                    @endphp
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
                                    @foreach($visibleBlockers->take(2) as $blocker)
                                        <span class="badge warning">{{ ucfirst($blocker) }}</span>
                                    @endforeach
                                    @if($visibleBlockers->count() > 2)
                                        <span class="badge warning">+{{ $visibleBlockers->count() - 2 }}</span>
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
                                            <button class="icon-btn validation-ready" title="Validar: bloquea el borrador como registro revisado"><i class="bi bi-check2-circle"></i></button>
                                        </form>
                                    @else
                                        <button class="icon-btn validation-disabled" title="No se puede validar. Pendiente: {{ $visibleBlockers->implode(', ') }}" disabled><i class="bi bi-check2-circle"></i></button>
                                    @endif
                                @elseif($descarga->estado === 'validado')
                                    @if($puedeGestionarCostos)
                                    <form method="POST" action="{{ route('descarga-contenedores.liquidar', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Marcar este registro como liquidado?')">
                                        @csrf @method('PATCH')
                                        <button class="icon-btn validation-ready" title="Liquidar: cerrar para pago referencial"><i class="bi bi-cash-stack"></i></button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('descarga-contenedores.volver-borrador', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Devolver este registro a borrador?')">
                                        @csrf @method('PATCH')
                                        <button class="icon-btn" title="Volver a borrador"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </form>
                                @elseif($descarga->estado === 'liquidado' && $puedeGestionarCostos)
                                    <form method="POST" action="{{ route('descarga-contenedores.volver-validado', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Reabrir este registro como validado?')">
                                        @csrf @method('PATCH')
                                        <button class="icon-btn" title="Reabrir como validado"><i class="bi bi-arrow-up-circle"></i></button>
                                    </form>
                                @endif
                                @if($descarga->estado !== 'liquidado')
                                <a href="{{ route('descarga-contenedores.edit', $descarga) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                @endif
                            @endif
                            @if(auth()->user()->tieneAcceso('descarga_contenedores', 'puede_eliminar'))
                                @if($descarga->estado !== 'liquidado')
                                <form method="POST" action="{{ route('descarga-contenedores.destroy', $descarga) }}" style="display:inline" onsubmit="return confirm('¿Eliminar esta descarga?')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                                </form>
                                @endif
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
<style>
.process-flow {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
}
.process-step {
    display: flex;
    align-items: center;
    gap: .7rem;
    min-width: 0;
    padding: .85rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-color);
    color: var(--text-main);
    text-decoration: none;
    box-shadow: 0 8px 18px rgba(15, 23, 42, .05);
}
.process-step:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, .08);
}
.process-step i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-size: 1rem;
}
.process-step strong {
    display: block;
    font-size: 1.25rem;
    line-height: 1;
}
.process-step small {
    display: block;
    margin-top: .2rem;
    color: var(--text-muted);
    font-size: .76rem;
}
.process-step.primary i { background: rgba(59, 130, 246, .12); color: #2563eb; }
.process-step.success i { background: rgba(16, 185, 129, .12); color: var(--success-color); }
.process-step.warning i { background: rgba(217, 119, 6, .14); color: #d97706; }
.process-step.locked i { background: rgba(100, 116, 139, .16); color: var(--text-muted); }
@media (max-width: 640px) {
    .process-flow { grid-template-columns: 1fr; }
}
</style>
@endsection
