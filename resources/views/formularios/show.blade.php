@extends('layouts.app')

@section('title', $formulario->nombre)

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $formulario->nombre }}</h2>
            <p class="page-subheading">Código: {{ $formulario->codigo }} &bull; v{{ $formulario->version }}</p>
        </div>
        <div style="display:flex;gap:0.75rem;">
            @if(auth()->user()->tieneAcceso('formularios', 'puede_editar'))
            <a href="{{ route('formularios.dashboard', $formulario) }}" class="btn-secondary">
                <i class="bi bi-bar-chart-line-fill"></i> Dashboard
            </a>
            <a href="{{ route('formularios.edit', $formulario) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            @endif
            <a href="{{ route('respuestas.create', ['formulario_id' => $formulario->id]) }}" class="btn-premium">
                <i class="bi bi-plus-circle-fill"></i> Nueva Solicitud
            </a>
            <a href="{{ route('formularios.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:flex-start;">

        <!-- Columna principal -->
        <div style="display:flex;flex-direction:column;gap:1rem;">
            <!-- Información general -->
            <div class="glass-card">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-info')">
                    <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-info-circle"></i> Información General
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-info" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;"></i>
                </div>
                <div id="sec-info" style="margin-top:1rem;">
                <div class="form-grid-2">
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Nombre</p>
                        <p style="font-size:0.9rem;font-weight:500;margin:0;">{{ $formulario->nombre }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Departamento</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $formulario->departamento->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Categoría</p>
                        <p style="font-size:0.9rem;margin:0;">
                            @if($formulario->categoria)
                                <i class="bi {{ $formulario->categoria->icono }}" style="color:{{ $formulario->categoria->color }}"></i>
                                {{ $formulario->categoria->nombre }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    @if($formulario->descripcion)
                    <div style="grid-column:1/-1;">
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Descripción</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $formulario->descripcion }}</p>
                    </div>
                    @endif
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Creado por</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $formulario->creador->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.2rem;">Creado</p>
                        <p style="font-size:0.9rem;margin:0;">{{ $formulario->created_at->format('d/m/Y') }}</p>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.75rem;">
                    @if($formulario->activo)
                        <span class="badge success"><i class="bi bi-check-circle-fill"></i> Activo</span>
                    @else
                        <span class="badge danger"><i class="bi bi-x-circle-fill"></i> Inactivo</span>
                    @endif
                    @if($formulario->requiere_aprobacion)
                        <span class="badge warning"><i class="bi bi-shield-check"></i> Requiere aprobación
                            @if($formulario->aprobadorRol)
                                ({{ $formulario->aprobadorRol->nombre }})
                            @endif
                        </span>
                    @endif
                    @if($formulario->genera_pdf)
                        <span class="badge info"><i class="bi bi-file-earmark-pdf"></i> Genera PDF</span>
                    @endif
                    @if($formulario->frecuencia)
                        <span class="badge"><i class="bi bi-arrow-repeat"></i> {{ ucfirst($formulario->frecuencia) }}</span>
                    @endif
                    @if($formulario->fecha_inicio || $formulario->fecha_fin)
                        <span class="badge" style="font-size:.72rem">
                            <i class="bi bi-calendar-range"></i>
                            {{ $formulario->fecha_inicio?->format('d/m/Y') ?? '∞' }}
                            → {{ $formulario->fecha_fin?->format('d/m/Y') ?? '∞' }}
                        </span>
                    @endif
                </div>
                </div>
            </div>

            <!-- Vista previa de campos -->
            <div class="glass-card">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-campos')">
                    <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-layout-wtf"></i> Campos del Formulario
                        <span style="font-size:0.8rem;font-weight:400;text-transform:none;letter-spacing:0;">
                            — {{ count($schema) }} campo(s)
                        </span>
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-campos" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;transform:rotate(-90deg);"></i>
                </div>
                <div id="sec-campos" style="display:none;margin-top:1.25rem;">

                @if(count($schema) === 0)
                    <div style="text-align:center;color:var(--text-muted);padding:2rem;">
                        <i class="bi bi-ui-checks-grid" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                        No hay campos definidos
                    </div>
                @else
                    @foreach($schema as $i => $field)
                        @if($field['type'] === 'divider')
                            <div style="display:flex;align-items:center;gap:0.75rem;margin:1.25rem 0 0.75rem;">
                                <div style="flex:1;height:1px;background:var(--surface-border);"></div>
                                <span style="font-size:0.8rem;color:var(--text-muted);white-space:nowrap;">{{ $field['label'] }}</span>
                                <div style="flex:1;height:1px;background:var(--surface-border);"></div>
                            </div>
                        @else
                            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0;border-bottom:1px solid var(--surface-border);
                                {{ $loop->last ? 'border-bottom:none;' : '' }}">
                                <div style="width:30px;height:30px;border-radius:8px;background:rgba(79,70,229,0.1);
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <span style="font-size:0.75rem;color:var(--primary-color);font-weight:600;">{{ $i + 1 }}</span>
                                </div>
                                <div style="flex:1;">
                                    <span style="font-size:0.875rem;font-weight:500;">{{ $field['label'] }}</span>
                                    @if(!empty($field['required']))
                                        <span style="color:#ef4444;font-size:0.8rem;"> *</span>
                                    @endif
                                    @if(!empty($field['placeholder']))
                                        <span style="display:block;font-size:0.75rem;color:var(--text-muted);">
                                            Placeholder: {{ $field['placeholder'] }}
                                        </span>
                                    @endif
                                    @if(!empty($field['options']))
                                        <div style="margin-top:0.35rem;display:flex;flex-wrap:wrap;gap:0.3rem;">
                                            @foreach($field['options'] as $opt)
                                                <span style="font-size:0.72rem;background:rgba(107,114,128,0.1);
                                                    padding:0.15rem 0.45rem;border-radius:4px;color:var(--text-muted);">
                                                    {{ $opt }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div style="display:flex;gap:0.5rem;align-items:center;flex-shrink:0;">
                                    @php
                                        $typeLabels = [
                                            'text'=>'Texto','textarea'=>'Texto largo','number'=>'Número',
                                            'date'=>'Fecha','select'=>'Lista','radio'=>'Opción múltiple',
                                            'checkbox'=>'Casillas','file'=>'Adjunto','signature'=>'Firma',
                                            'select_dynamic'=>'Lista dinámica','select_tabla'=>'Datos del sistema',
                                            'auto'=>'Campo automático'
                                        ];
                                    @endphp
                                    <span style="font-size:0.72rem;background:rgba(79,70,229,0.1);
                                        color:var(--primary-color);padding:0.2rem 0.55rem;border-radius:6px;">
                                        {{ $typeLabels[$field['type']] ?? $field['type'] }}
                                    </span>
                                    @if(!empty($field['required']))
                                        <span style="font-size:0.72rem;background:rgba(239,68,68,0.1);
                                            color:#ef4444;padding:0.2rem 0.55rem;border-radius:6px;">
                                            Obligatorio
                                        </span>
                                    @endif
                                    @if(!empty($field['fuente']))
                                        @php
                                            $autoFuentes = [
                                                'usuario_nombre'=>'Nombre','usuario_email'=>'Email',
                                                'usuario_cargo'=>'Cargo','usuario_departamento'=>'Departamento',
                                                'usuario_centro_costo'=>'Centro costo','fecha_actual'=>'Fecha',
                                                'hora_actual'=>'Hora','fecha_hora_actual'=>'Fecha/hora',
                                            ];
                                        @endphp
                                        <span style="font-size:0.72rem;background:rgba(139,92,246,0.1);
                                            color:#8b5cf6;padding:0.2rem 0.55rem;border-radius:6px;">
                                            <i class="bi bi-lightning-charge"></i> {{ $autoFuentes[$field['fuente']] ?? $field['fuente'] }}
                                        </span>
                                    @endif
                                    @if(!empty($field['tabla']))
                                        <span style="font-size:0.72rem;background:rgba(14,165,233,0.1);
                                            color:#0ea5e9;padding:0.2rem 0.55rem;border-radius:6px;">
                                            <i class="bi bi-database"></i> {{ ucfirst(str_replace('_',' ',$field['tabla'])) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
                </div>
            </div>

            <!-- Gestión de opciones dinámicas -->
            @php $dynamicFields = collect($schema)->filter(fn($f) => ($f['type'] ?? '') === 'select_dynamic'); @endphp
            @if($dynamicFields->isNotEmpty() && auth()->user()->tieneAcceso('formularios', 'puede_editar'))
            <div class="glass-card">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-opciones-din')">
                    <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-collection"></i> Listas Dinámicas
                        <span style="font-size:0.8rem;font-weight:400;text-transform:none;letter-spacing:0;">
                            — {{ $dynamicFields->count() }} campo(s)
                        </span>
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-opciones-din" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;transform:rotate(-90deg);"></i>
                </div>
                <div id="sec-opciones-din" style="display:none;margin-top:1.25rem;">
                    <p style="font-size:.78rem;color:var(--text-muted);margin:0 0 1rem;padding:.5rem .75rem;background:rgba(79,70,229,.04);border-radius:8px;">
                        <i class="bi bi-info-circle"></i> Aquí puedes editar o eliminar las opciones acumuladas en cada lista dinámica para mantenerlas limpias.
                    </p>

                    @foreach($dynamicFields as $field)
                    @php $opciones = $campoOpciones[$field['id']] ?? collect(); @endphp
                    <div style="margin-bottom:.5rem;border:1px solid var(--surface-border);border-radius:10px;overflow:hidden;">
                        <div style="display:flex;align-items:center;gap:.5rem;padding:.55rem .75rem;cursor:pointer;background:rgba(255,255,255,.02);" onclick="toggleSection('dyn-{{ $field['id'] }}')">
                            <i class="bi bi-chevron-down section-chevron" id="chevron-dyn-{{ $field['id'] }}" style="font-size:.65rem;color:var(--text-muted);transition:transform .25s;transform:rotate(-90deg);flex-shrink:0;"></i>
                            <span style="font-size:.85rem;font-weight:600;flex:1;">{{ $field['label'] }}</span>
                            <span class="badge" style="font-size:.68rem">{{ $opciones->count() }}</span>
                        </div>

                        <div id="dyn-{{ $field['id'] }}" style="display:none;padding:.5rem .75rem .75rem;">
                        @if($opciones->isEmpty())
                            <p style="font-size:.8rem;color:var(--text-muted);padding:.25rem 0;margin:0;">Sin opciones registradas.</p>
                        @else
                            <div style="display:flex;flex-direction:column;gap:.35rem;" id="dyn-list-{{ $field['id'] }}">
                                @foreach($opciones as $opcion)
                                <div style="display:flex;align-items:center;gap:.5rem;padding:.4rem .65rem;background:rgba(255,255,255,.03);border:1px solid var(--surface-border);border-radius:8px;" id="opcion-row-{{ $opcion->id }}">
                                    <input type="text" value="{{ $opcion->valor }}" class="form-input" style="font-size:.82rem;padding:.3rem .55rem;flex:1;" id="opcion-input-{{ $opcion->id }}">
                                    <button type="button" class="icon-btn" style="width:26px;height:26px;flex-shrink:0;" title="Guardar cambio"
                                        onclick="saveOpcion({{ $opcion->id }}, this)">
                                        <i class="bi bi-check-lg" style="font-size:.8rem;color:#10b981"></i>
                                    </button>
                                    <button type="button" class="icon-btn danger" style="width:26px;height:26px;flex-shrink:0;" title="Eliminar"
                                        onclick="deleteOpcion({{ $opcion->id }}, this)">
                                        <i class="bi bi-trash3" style="font-size:.7rem"></i>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                        @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Historial de versiones -->
            @if($formulario->versiones->count() > 0)
            <div class="glass-card">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-versiones')">
                    <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-clock-history"></i> Historial de versiones
                        <span style="font-size:0.8rem;font-weight:400;text-transform:none;letter-spacing:0;">
                            — {{ $formulario->versiones->count() }} versión(es) anterior(es)
                        </span>
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-versiones" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;transform:rotate(-90deg);"></i>
                </div>
                <div id="sec-versiones" style="display:none;margin-top:1rem;">

                <div style="display:flex;flex-direction:column;gap:.5rem">
                    {{-- Current version --}}
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem .75rem;background:rgba(79,70,229,.06);border:1px solid rgba(79,70,229,.15);border-radius:10px;">
                        <span class="badge success" style="font-size:.75rem">v{{ $formulario->version }}</span>
                        <div style="flex:1">
                            <strong style="font-size:.85rem">Versión actual</strong>
                            <span style="display:block;font-size:.72rem;color:var(--text-muted)">{{ count($schema) }} campo(s)</span>
                        </div>
                        <span style="font-size:.72rem;color:var(--text-muted)">{{ $formulario->updated_at->format('d/m/Y H:i') }}</span>
                    </div>

                    @foreach($formulario->versiones as $v)
                    @php $vSchema = json_decode($v->schema_json ?? '[]', true); @endphp
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem .75rem;background:rgba(255,255,255,.03);border:1px solid var(--surface-border);border-radius:10px;">
                        <span class="badge" style="font-size:.75rem">v{{ $v->version }}</span>
                        <div style="flex:1">
                            <span style="font-size:.85rem">{{ count($vSchema) }} campo(s)</span>
                            @if($v->modificador)
                                <span style="display:block;font-size:.72rem;color:var(--text-muted)">
                                    por {{ $v->modificador->name }}
                                </span>
                            @endif
                        </div>
                        <span style="font-size:.72rem;color:var(--text-muted)">{{ $v->created_at->format('d/m/Y H:i') }}</span>
                        <button type="button" class="icon-btn" style="width:24px;height:24px;" title="Ver esquema"
                            onclick="toggleVersionDetail({{ $v->id }})">
                            <i class="bi bi-chevron-down" id="chevron-{{ $v->id }}" style="font-size:.7rem;transition:transform .2s"></i>
                        </button>
                    </div>
                    <div id="version-detail-{{ $v->id }}" style="display:none;padding:.5rem .75rem;background:rgba(255,255,255,.02);border:1px dashed var(--surface-border);border-radius:8px;margin-top:-.25rem">
                        @foreach($vSchema as $vi => $vf)
                            @if($vf['type'] === 'divider')
                                <div style="text-align:center;font-size:.75rem;color:var(--text-muted);margin:.5rem 0;border-top:1px dashed var(--surface-border);padding-top:.35rem">{{ $vf['label'] }}</div>
                            @else
                                <div style="font-size:.8rem;padding:.3rem 0;display:flex;gap:.5rem;align-items:center">
                                    <span style="color:var(--text-muted)">{{ $vi + 1 }}.</span>
                                    <span>{{ $vf['label'] }}</span>
                                    <span style="font-size:.68rem;color:var(--text-muted);background:rgba(107,114,128,.1);padding:.1rem .35rem;border-radius:4px">{{ $vf['type'] }}</span>
                                    @if(!empty($vf['required']))
                                        <span style="font-size:.68rem;color:#ef4444">*</span>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @endforeach
                </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Columna lateral -->
        <div style="display:flex;flex-direction:column;gap:1rem;">

            <!-- Acciones rápidas -->
            <div class="glass-card" style="padding:.85rem 1rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-acciones')">
                    <h3 style="font-size:0.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-lightning-charge-fill"></i> Acciones rápidas
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-acciones" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;transform:rotate(-90deg);"></i>
                </div>
                <div id="sec-acciones" style="display:none;margin-top:.75rem;">
                <div style="display:flex;flex-direction:column;gap:0.4rem;">
                    <a href="{{ route('respuestas.create', ['formulario_id' => $formulario->id]) }}"
                       class="btn-premium" style="justify-content:center;font-size:.82rem;padding:.5rem .75rem;">
                        <i class="bi bi-plus-circle-fill"></i> Nueva Solicitud
                    </a>
                    @if(auth()->user()->tieneAcceso('formularios', 'puede_editar'))
                    <a href="{{ route('formularios.edit', $formulario) }}"
                       class="btn-secondary" style="justify-content:center;font-size:.82rem;padding:.45rem .75rem;">
                        <i class="bi bi-pencil-fill"></i> Editar Formulario
                    </a>
                    @endif
                    <a href="#seccion-respuestas"
                       class="btn-ghost" style="justify-content:center;font-size:.82rem;padding:.45rem .75rem;">
                        <i class="bi bi-table"></i> Ver Respuestas
                    </a>
                    <a href="{{ route('formularios.dashboard', $formulario) }}"
                       class="btn-ghost" style="justify-content:center;font-size:.82rem;padding:.45rem .75rem;">
                        <i class="bi bi-bar-chart-line-fill"></i> Dashboard
                    </a>

                    @if(auth()->user()->tieneAcceso('formularios', 'puede_editar'))
                    <div style="border-top:1px solid var(--surface-border);margin:.25rem 0;"></div>
                    <form method="POST" action="{{ route('formularios.toggleActivo', $formulario) }}">
                        @csrf
                        @method('PATCH')
                        @if($formulario->activo)
                        <button type="submit" class="btn-ghost" style="width:100%;justify-content:center;font-size:.82rem;padding:.4rem .75rem;" onclick="return confirm('¿Desactivar este formulario?')">
                            <i class="bi bi-pause-circle"></i> Desactivar
                        </button>
                        @else
                        <button type="submit" class="btn-premium" style="width:100%;justify-content:center;font-size:.82rem;padding:.4rem .75rem;">
                            <i class="bi bi-play-circle-fill"></i> Reactivar
                        </button>
                        @endif
                    </form>
                    @endif

                    @if(auth()->user()->tieneAcceso('formularios', 'puede_eliminar') && $stats['total'] === 0)
                    <form method="POST" action="{{ route('formularios.destroy', $formulario) }}"
                          onsubmit="return confirm('¿Eliminar este formulario?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost danger" style="width:100%;justify-content:center;font-size:.82rem;padding:.4rem .75rem;">
                            <i class="bi bi-trash-fill"></i> Eliminar
                        </button>
                    </form>
                    @endif
                </div>
                </div>
            </div>

            <!-- Indicadores compactos -->
            <div class="glass-card" style="padding:.85rem 1rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-resumen')">
                    <h3 style="font-size:0.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-bar-chart-fill"></i> Resumen
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-resumen" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;"></i>
                </div>
                <div id="sec-resumen" style="margin-top:.6rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem .6rem;background:rgba(79,70,229,0.06);border-radius:6px;">
                        <span style="font-size:.72rem;color:var(--text-muted);">Total</span>
                        <strong style="font-size:.95rem;color:var(--primary-color);">{{ $stats['total'] }}</strong>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem .6rem;background:rgba(234,179,8,0.06);border-radius:6px;">
                        <span style="font-size:.72rem;color:var(--text-muted);">Pend.</span>
                        <strong style="font-size:.95rem;color:#d97706;">{{ $stats['pendientes'] }}</strong>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem .6rem;background:rgba(34,197,94,0.06);border-radius:6px;">
                        <span style="font-size:.72rem;color:var(--text-muted);">Aprob.</span>
                        <strong style="font-size:.95rem;color:#16a34a;">{{ $stats['aprobadas'] }}</strong>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem .6rem;background:rgba(239,68,68,0.06);border-radius:6px;">
                        <span style="font-size:.72rem;color:var(--text-muted);">Rech.</span>
                        <strong style="font-size:.95rem;color:#dc2626;">{{ $stats['rechazadas'] }}</strong>
                    </div>
                </div>
                @if($stats['borradores'] > 0)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.35rem .6rem;background:rgba(107,114,128,0.05);border-radius:6px;margin-top:.4rem;">
                    <span style="font-size:.72rem;color:var(--text-muted);">Borradores</span>
                    <strong style="font-size:.85rem;color:var(--text-muted);">{{ $stats['borradores'] }}</strong>
                </div>
                @endif
                </div>
            </div>

            {{-- Panel de asignación --}}
            @if(auth()->user()->tieneAcceso('formularios', 'puede_editar'))
            <div class="glass-card" style="padding:.85rem 1rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="toggleSection('sec-asignaciones')">
                    <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                        <i class="bi bi-people-fill"></i> Asignaciones
                        <span class="badge" style="margin-left:.3rem">{{ $asignados->count() }}</span>
                    </h3>
                    <i class="bi bi-chevron-down section-chevron" id="chevron-sec-asignaciones" style="font-size:.75rem;color:var(--text-muted);transition:transform .25s;transform:rotate(-90deg);"></i>
                </div>
                <div id="sec-asignaciones" style="display:none;margin-top:1rem;">

                <form method="POST" action="{{ route('formularios.asignar', $formulario) }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:.75rem">
                        <select name="modo" id="assign-modo" class="form-input" style="font-size:.82rem">
                            <option value="usuarios">Por usuario(s)</option>
                            <option value="departamento">Por departamento</option>
                            <option value="cargo">Por cargo</option>
                            <option value="rol">Por rol</option>
                            <option value="todos">Todos los usuarios</option>
                        </select>
                    </div>

                    <div id="assign-usuarios" class="assign-panel">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <select name="user_ids[]" multiple class="form-input" style="font-size:.82rem;min-height:90px">
                                @foreach($usuariosDisp as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <small style="color:var(--text-muted)">Ctrl+click para seleccionar varios</small>
                        </div>
                    </div>

                    <div id="assign-depto" class="assign-panel" style="display:none">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <select name="departamento_id" class="form-input" style="font-size:.82rem">
                                <option value="">Seleccionar departamento</option>
                                @foreach($departamentos as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="assign-cargo" class="assign-panel" style="display:none">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <select name="cargo_id" class="form-input" style="font-size:.82rem">
                                <option value="">Seleccionar cargo</option>
                                @foreach($cargos as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="assign-rol" class="assign-panel" style="display:none">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <select name="rol_id" class="form-input" style="font-size:.82rem">
                                <option value="">Seleccionar rol</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="assign-todos" class="assign-panel" style="display:none">
                        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.75rem;padding:.5rem;background:rgba(249,115,22,.06);border-radius:6px;">
                            <i class="bi bi-info-circle"></i> Se asignará a todos los usuarios activos del sistema
                        </p>
                    </div>

                    <div class="form-group" style="margin-bottom:.75rem">
                        <label style="font-size:.78rem;color:var(--text-muted)">Fecha límite</label>
                        <input type="date" name="fecha_limite" class="form-input" style="font-size:.82rem">
                    </div>

                    <button type="submit" class="btn-secondary" style="width:100%;justify-content:center;font-size:.82rem">
                        <i class="bi bi-person-plus-fill"></i> Asignar
                    </button>
                </form>

                @if($asignados->count() > 0)
                    <div style="margin-top:1rem;border-top:1px solid var(--surface-border);padding-top:.75rem;">
                        @foreach($asignados as $a)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.03);">
                                <div>
                                    <span style="font-size:.82rem">{{ $a->name }}</span>
                                    <span class="badge {{ $a->pivot->estado === 'Completado' ? 'success' : ($a->pivot->estado === 'Vencido' ? 'danger' : 'warning') }}"
                                          style="font-size:.65rem;margin-left:.3rem">
                                        {{ $a->pivot->estado }}
                                    </span>
                                    <span style="font-size:.68rem;color:var(--text-muted);display:block">
                                        {{ optional($a->departamento)->nombre ?? '' }}
                                        {{ optional($a->cargo)->nombre ? '· ' . $a->cargo->nombre : '' }}
                                    </span>
                                    @if($a->pivot->fecha_limite)
                                        <span style="font-size:.7rem;color:var(--text-muted);display:block">
                                            Límite: {{ \Carbon\Carbon::parse($a->pivot->fecha_limite)->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                                @if($a->pivot->estado === 'Pendiente')
                                    <form method="POST" action="{{ route('formularios.desasignar', [$formulario, $a]) }}"
                                          onsubmit="return confirm('¿Quitar asignación?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="icon-btn danger" style="width:22px;height:22px;" title="Quitar">
                                            <i class="bi bi-x" style="font-size:.7rem"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ===== TABLA DE RESPUESTAS ===== --}}
    <div id="seccion-respuestas" class="glass-card" style="margin-top:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem;">
            <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin:0;">
                <i class="bi bi-table"></i> Respuestas
                <span class="badge" style="margin-left:.3rem">{{ $respuestas->count() }}</span>
            </h3>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                <input type="text" id="resp-search" placeholder="Buscar..." class="form-input" style="font-size:.8rem;padding:.35rem .65rem;width:180px;">
                <select id="resp-filter-estado" class="form-input" style="font-size:.8rem;padding:.35rem .65rem;width:140px;">
                    <option value="">Todos los estados</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="Aprobado">Aprobado</option>
                    <option value="Rechazado">Rechazado</option>
                    <option value="Borrador">Borrador</option>
                    <option value="Revisión">Revisión</option>
                </select>
                <a href="{{ route('respuestas.exportar', ['formulario_id' => $formulario->id]) }}" class="btn-secondary" style="font-size:.78rem;padding:.35rem .65rem;">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
                <a href="{{ route('respuestas.plantillaImport', $formulario) }}" class="btn-secondary" style="font-size:.78rem;padding:.35rem .65rem;">
                    <i class="bi bi-download"></i> Plantilla
                </a>
                <button type="button" onclick="document.getElementById('modal-importar').style.display='flex'" class="btn-secondary" style="font-size:.78rem;padding:.35rem .65rem;">
                    <i class="bi bi-upload"></i> Importar
                </button>
                <button type="button" id="btn-bulk-delete" onclick="confirmBulkDelete()" class="btn-secondary" style="font-size:.78rem;padding:.35rem .65rem;display:none;background:rgba(220,38,38,.08);color:#dc2626;border-color:rgba(220,38,38,.25);">
                    <i class="bi bi-trash3"></i> Eliminar (<span id="bulk-delete-count">0</span>)
                </button>
            </div>
        </div>

        @if($respuestas->isEmpty())
            <div style="text-align:center;padding:2.5rem 1rem;color:var(--text-muted);">
                <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
                <p style="margin:0;font-size:.9rem;">No hay respuestas aún</p>
                <a href="{{ route('respuestas.create', ['formulario_id' => $formulario->id]) }}" class="btn-premium" style="margin-top:1rem;display:inline-flex;">
                    <i class="bi bi-plus-circle-fill"></i> Crear primera solicitud
                </a>
            </div>
        @else
            @php
                $badgeMap = ['Pendiente'=>'warning','Aprobado'=>'success','Rechazado'=>'danger','Borrador'=>'','Revisión'=>'warning'];
                // Get first 4 non-divider fields for compact table columns
                $tableCols = collect($schema)->filter(fn($f) => $f['type'] !== 'divider' && $f['type'] !== 'signature')->take(4)->values();
            @endphp
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
                <table class="saep-table" style="width:100%;font-size:.82rem;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="padding:.55rem .65rem;text-align:center;border-bottom:2px solid var(--surface-border);width:36px;">
                                <input type="checkbox" id="resp-select-all" onchange="toggleSelectAll(this)" style="cursor:pointer;">
                            </th>
                            <th style="padding:.55rem .65rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;">ID</th>
                            <th style="padding:.55rem .65rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;">Solicitante</th>
                            @foreach($tableCols as $col)
                            <th style="padding:.55rem .65rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;max-width:160px;overflow:hidden;text-overflow:ellipsis;">{{ $col['label'] }}</th>
                            @endforeach
                            <th style="padding:.55rem .65rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;">Estado</th>
                            <th style="padding:.55rem .65rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;">Fecha</th>
                            <th style="padding:.55rem .65rem;text-align:center;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="resp-tbody">
                        @foreach($respuestas as $resp)
                        @php $datos = json_decode($resp->datos_json ?? '{}', true); @endphp
                        <tr class="resp-row" data-estado="{{ $resp->estado }}" data-search="{{ strtolower(($resp->usuario->name ?? '') . ' ' . ($resp->usuario->apellido_paterno ?? '') . ' REQ-' . str_pad($resp->id, 4, '0', STR_PAD_LEFT)) }}" style="cursor:pointer;transition:background .15s;" onmouseenter="this.style.background='var(--surface-bg,#f8fafc)'" onmouseleave="this.style.background='transparent'">
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);text-align:center;" onclick="event.stopPropagation()">
                                <input type="checkbox" class="resp-checkbox" value="{{ $resp->id }}" onchange="updateBulkDelete()" style="cursor:pointer;">
                            </td>
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);white-space:nowrap;font-weight:600;color:var(--primary-color);">
                                #REQ-{{ str_pad($resp->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);white-space:nowrap;">
                                <div style="display:flex;align-items:center;gap:.4rem;">
                                    <div style="width:26px;height:26px;border-radius:50%;background:rgba(79,70,229,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-person-fill" style="font-size:.7rem;color:var(--primary-color)"></i>
                                    </div>
                                    <div>
                                        <span style="font-size:.82rem;">{{ $resp->usuario->name ?? 'Sin usuario' }} {{ $resp->usuario->apellido_paterno ?? '' }}</span>
                                        <span style="display:block;font-size:.68rem;color:var(--text-muted);">{{ $resp->usuario->departamento->nombre ?? '' }}</span>
                                    </div>
                                </div>
                            </td>
                            @foreach($tableCols as $col)
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                @php $v = $datos[$col['id']] ?? null; @endphp
                                @if($col['type'] === 'file')
                                    @if($v && is_array($v) && isset($v['name']))
                                        <i class="bi bi-paperclip" style="color:var(--accent-color)"></i> {{ Str::limit($v['name'], 20) }}
                                    @else — @endif
                                @elseif(is_array($v))
                                    {{ Str::limit(implode(', ', $v), 30) }}
                                @else
                                    {{ Str::limit((string)$v, 30) ?: '—' }}
                                @endif
                            </td>
                            @endforeach
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);white-space:nowrap;">
                                <span class="badge {{ $badgeMap[$resp->estado] ?? '' }}" style="font-size:.7rem;">{{ $resp->estado }}</span>
                            </td>
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);white-space:nowrap;font-size:.78rem;color:var(--text-muted);">
                                {{ $resp->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td style="padding:.55rem .65rem;border-bottom:1px solid var(--surface-border);text-align:center;white-space:nowrap;">
                                <button type="button" class="icon-btn" style="width:28px;height:28px;" title="Ver detalle" onclick="openDrawer({{ $resp->id }})">
                                    <i class="bi bi-eye-fill" style="font-size:.75rem;"></i>
                                </button>
                                <a href="{{ route('respuestas.show', $resp) }}" class="icon-btn" style="width:28px;height:28px;text-decoration:none;" title="Abrir completo">
                                    <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;"></i>
                                </a>
                                <a href="{{ route('pdf.respuesta', $resp) }}" class="icon-btn" style="width:28px;height:28px;text-decoration:none;" target="_blank" title="Descargar PDF">
                                    <i class="bi bi-file-earmark-pdf" style="font-size:.7rem;"></i>
                                </a>
                                <form method="POST" action="{{ route('respuestas.reenviarMail', $resp) }}" style="display:inline;" onsubmit="return confirm('¿Reenviar el correo con PDF a los destinatarios de esta respuesta?')">
                                    @csrf
                                    <button type="submit" class="icon-btn" style="width:28px;height:28px;" title="Reenviar correo con PDF">
                                        <i class="bi bi-envelope-arrow-up" style="font-size:.7rem;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== DRAWER DE DETALLE ===== --}}
    <div id="resp-drawer-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:999;transition:opacity .3s;" onclick="closeDrawer()"></div>
    <div id="resp-drawer" style="display:none;position:fixed;top:0;right:-480px;width:480px;max-width:100vw;height:100vh;background:var(--surface-card-solid,#fff);border-left:1px solid var(--surface-border);box-shadow:-8px 0 30px rgba(0,0,0,.12);z-index:1000;overflow-y:auto;transition:right .3s ease;color:var(--text-main);">
        <div style="position:sticky;top:0;background:var(--surface-card-solid,#fff);border-bottom:1px solid var(--surface-border);padding:.85rem 1.25rem;display:flex;align-items:center;justify-content:space-between;z-index:1;">
            <h3 id="drawer-title" style="font-size:.95rem;font-weight:600;margin:0;color:var(--text-main);">Detalle</h3>
            <button type="button" class="icon-btn" onclick="closeDrawer()" style="width:30px;height:30px;">
                <i class="bi bi-x-lg" style="font-size:.8rem;"></i>
            </button>
        </div>
        <div id="drawer-content" style="padding:1.25rem;">
            {{-- Se llena dinámicamente --}}
        </div>
    </div>
</div>

{{-- JSON de datos para el drawer --}}
@php
    $respuestasJson = $respuestas->map(function($r) {
        $datos = json_decode($r->datos_json ?? '{}', true);
        $aprobacionesArr = $r->aprobaciones->map(function($a) {
            return [
                'aprobador' => $a->aprobador->name ?? '—',
                'accion' => $a->accion,
                'comentario' => $a->comentario,
                'fecha' => $a->created_at->format('d/m/Y H:i'),
            ];
        })->values()->all();

        return [
            'id' => $r->id,
            'req' => '#REQ-' . str_pad($r->id, 4, '0', STR_PAD_LEFT),
            'estado' => $r->estado,
            'usuario' => ($r->usuario->name ?? 'Sin usuario') . ' ' . ($r->usuario->apellido_paterno ?? ''),
            'departamento' => $r->usuario->departamento->nombre ?? '',
            'version' => $r->version_form,
            'fecha' => $r->created_at->format('d/m/Y H:i'),
            'fecha_resolucion' => $r->fecha_resolucion ? \Carbon\Carbon::parse($r->fecha_resolucion)->format('d/m/Y H:i') : null,
            'datos' => $datos,
            'aprobaciones' => $aprobacionesArr,
            'show_url' => route('respuestas.show', $r),
            'pdf_url' => route('pdf.respuesta', $r),
        ];
    })->values()->all();

    $schemaJson = collect($schema)->filter(function($f) {
        return $f['type'] !== 'divider';
    })->values()->all();
@endphp
<script>
    window.__respuestas = @json($respuestasJson);
    window.__schema = @json($schemaJson);
</script>

{{-- ===== MODAL IMPORTAR ===== --}}
<div id="modal-importar" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--card-bg,#fff);border-radius:16px;padding:1.5rem 1.75rem;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);border:1px solid var(--surface-border);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--text-primary);">
                <i class="bi bi-upload" style="color:var(--primary-color);margin-right:.4rem;"></i> Importar Respuestas
            </h3>
            <button onclick="document.getElementById('modal-importar').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--text-muted);padding:.25rem;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div style="background:var(--surface-color);border:1px solid var(--surface-border);border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;font-size:.82rem;color:var(--text-secondary);line-height:1.5;">
            <strong style="color:var(--text-primary);">Instrucciones:</strong>
            <ol style="margin:.5rem 0 0;padding-left:1.2rem;">
                <li>Descargue la <a href="{{ route('respuestas.plantillaImport', $formulario) }}" style="color:var(--primary-color);font-weight:600;">plantilla Excel</a> del formulario.</li>
                <li>Complete los datos a partir de la fila 5 (no modifique las filas 1-4).</li>
                <li>Use el <strong>email</strong> del trabajador registrado en SAEP — los campos automáticos (nombre, cargo, depto, etc.) se llenan solos.</li>
                <li>Los campos de <strong>desplegable dinámico</strong> aceptan valores nuevos que se agregan automáticamente al listado.</li>
                <li>Suba el archivo completado aquí.</li>
            </ol>
        </div>
        <form action="{{ route('respuestas.importar', $formulario) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom:1rem;">
                <label for="archivo-importar" style="font-size:.82rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:.4rem;">Archivo Excel (.xlsx)</label>
                <input type="file" name="archivo" id="archivo-importar" accept=".xlsx,.xls" required class="form-input" style="font-size:.82rem;padding:.4rem .65rem;width:100%;">
            </div>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-importar').style.display='none'" class="btn-secondary" style="font-size:.82rem;padding:.4rem 1rem;">
                    Cancelar
                </button>
                <button type="submit" class="btn-premium" style="font-size:.82rem;padding:.4rem 1rem;">
                    <i class="bi bi-cloud-upload"></i> Importar
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ===== Collapsible sections =====
function toggleSection(id) {
    const el = document.getElementById(id);
    const chevron = document.getElementById('chevron-' + id);
    if (!el) return;
    if (el.style.display === 'none') {
        el.style.display = '';
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    } else {
        el.style.display = 'none';
        if (chevron) chevron.style.transform = 'rotate(-90deg)';
    }
}

document.getElementById('assign-modo')?.addEventListener('change', function() {
    document.querySelectorAll('.assign-panel').forEach(p => {
        p.style.display = 'none';
        p.querySelectorAll('select, input').forEach(el => el.disabled = true);
    });
    const map = {
        usuarios: 'assign-usuarios',
        departamento: 'assign-depto',
        cargo: 'assign-cargo',
        rol: 'assign-rol',
        todos: 'assign-todos'
    };
    const target = document.getElementById(map[this.value]);
    if (target) {
        target.style.display = '';
        target.querySelectorAll('select, input').forEach(el => el.disabled = false);
    }
});
// Initialize: disable hidden panels on load
document.querySelectorAll('.assign-panel').forEach(p => {
    if (p.style.display === 'none') {
        p.querySelectorAll('select, input').forEach(el => el.disabled = true);
    }
});

window.toggleVersionDetail = function(id) {
    const detail = document.getElementById('version-detail-' + id);
    const chevron = document.getElementById('chevron-' + id);
    if (detail.style.display === 'none') {
        detail.style.display = '';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        detail.style.display = 'none';
        chevron.style.transform = '';
    }
};

// ===== Drawer =====
const overlay = document.getElementById('resp-drawer-overlay');
const drawer = document.getElementById('resp-drawer');
const drawerTitle = document.getElementById('drawer-title');
const drawerContent = document.getElementById('drawer-content');

window.openDrawer = function(id) {
    const r = (window.__respuestas || []).find(x => x.id === id);
    if (!r) return;

    const badgeColor = {Pendiente:'#d97706',Aprobado:'#16a34a',Rechazado:'#dc2626',Borrador:'#6b7280','Revisión':'#d97706'};
    const badgeBg = {Pendiente:'rgba(234,179,8,.1)',Aprobado:'rgba(34,197,94,.1)',Rechazado:'rgba(239,68,68,.1)',Borrador:'rgba(107,114,128,.1)','Revisión':'rgba(234,179,8,.1)'};

    let html = '';

    // Header info
    html += `<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--surface-border);">
        <div style="flex:1;">
            <span style="font-size:1.1rem;font-weight:700;color:var(--primary-color);">${r.req}</span>
            <span style="display:inline-block;margin-left:.5rem;font-size:.72rem;padding:.2rem .5rem;border-radius:6px;background:${badgeBg[r.estado]||'#f1f5f9'};color:${badgeColor[r.estado]||'#6b7280'};font-weight:600;">${r.estado}</span>
        </div>
        <a href="${r.pdf_url}" target="_blank" style="text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;font-size:.78rem;color:#ef4444;padding:.3rem .6rem;border-radius:8px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);">
            <i class="bi bi-file-earmark-pdf-fill"></i> PDF
        </a>
    </div>`;

    // Meta info
    html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.25rem;">
        <div style="padding:.5rem .65rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:8px;">
            <p style="font-size:.68rem;color:var(--text-muted);margin:0;">Solicitante</p>
            <p style="font-size:.82rem;font-weight:500;margin:.1rem 0 0;color:var(--text-main);">${r.usuario}</p>
            <p style="font-size:.7rem;color:var(--text-muted);margin:0;">${r.departamento}</p>
        </div>
        <div style="padding:.5rem .65rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:8px;">
            <p style="font-size:.68rem;color:var(--text-muted);margin:0;">Fecha envío</p>
            <p style="font-size:.82rem;font-weight:500;margin:.1rem 0 0;color:var(--text-main);">${r.fecha}</p>
            ${r.fecha_resolucion ? `<p style="font-size:.7rem;color:var(--text-muted);margin:0;">Resuelto: ${r.fecha_resolucion}</p>` : ''}
        </div>
    </div>`;

    // All fields
    html += `<h4 style="font-size:.78rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin:0 0 .75rem;"><i class="bi bi-clipboard-data"></i> Datos ingresados</h4>`;
    (window.__schema || []).forEach(f => {
        const val = r.datos[f.id] ?? null;
        let rendered = '';
        if (f.type === 'signature') {
            rendered = val ? `<img src="${val}" alt="Firma" style="max-width:180px;border:1px solid var(--surface-border);border-radius:6px;">` : '<span style="color:var(--text-muted);font-style:italic;">Sin firma</span>';
        } else if (f.type === 'file') {
            const renderFileItem = (item) => {
                const isImg = item.mime && item.mime.startsWith('image/');
                const size = item.size ? ` (${Math.round(item.size/1024)} KB)` : '';
                if (isImg) {
                    return `<div style="margin-bottom:.4rem;"><img src="/storage/${item.path}" alt="${item.name||''}" style="max-width:100%;max-height:160px;border-radius:6px;border:1px solid var(--surface-border);display:block;margin-bottom:.25rem;"><a href="/storage/${item.path}" target="_blank" style="font-size:.75rem;color:var(--accent-color);text-decoration:none;">${item.name||'Ver imagen'}${size}</a></div>`;
                } else {
                    return `<a href="/storage/${item.path}" target="_blank" style="display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;color:var(--accent-color);text-decoration:none;padding:.3rem .6rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:6px;margin-bottom:.3rem;"><i class="bi bi-download"></i> ${item.name||'Descargar'}${size}</a>`;
                }
            };
            if (val && Array.isArray(val) && val.length > 0 && val[0] && val[0].path) {
                rendered = val.map(renderFileItem).join('');
            } else if (val && typeof val === 'object' && val.path) {
                rendered = renderFileItem(val);
            } else {
                rendered = '<span style="color:var(--text-muted);font-style:italic;">Sin archivo</span>';
            }
        } else if (Array.isArray(val)) {
            rendered = `<span style="font-size:.85rem;color:var(--text-main);">${val.join(', ') || '—'}</span>`;
        } else {
            rendered = `<span style="font-size:.85rem;color:var(--text-main);">${val || '—'}</span>`;
        }
        html += `<div style="margin-bottom:.85rem;padding-bottom:.65rem;border-bottom:1px solid var(--surface-border);">
            <label style="display:block;font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;margin-bottom:.2rem;">${f.label}</label>
            ${rendered}
        </div>`;
    });

    // Approvals timeline
    if (r.aprobaciones && r.aprobaciones.length > 0) {
        html += `<h4 style="font-size:.78rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.04em;margin:1.25rem 0 .75rem;padding-top:.75rem;border-top:1px solid var(--surface-border);"><i class="bi bi-clock-history"></i> Historial de aprobación</h4>`;
        r.aprobaciones.forEach(a => {
            const aColor = {Aprobado:'#16a34a',Rechazado:'#dc2626','Revisión':'#d97706',Comentario:'#6b7280'}[a.accion] || '#6b7280';
            const aIcon = {Aprobado:'bi-check-circle-fill',Rechazado:'bi-x-circle-fill','Revisión':'bi-arrow-repeat',Comentario:'bi-chat-dots-fill'}[a.accion] || 'bi-chat-dots';
            html += `<div style="display:flex;gap:.6rem;margin-bottom:.65rem;padding:.5rem .65rem;background:rgba(${aColor === '#16a34a' ? '34,197,94' : aColor === '#dc2626' ? '239,68,68' : '234,179,8'},.04);border-radius:8px;border-left:3px solid ${aColor};">
                <i class="bi ${aIcon}" style="color:${aColor};margin-top:.1rem;"></i>
                <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.82rem;font-weight:500;">${a.aprobador}</span>
                        <span style="font-size:.68rem;color:var(--text-muted);">${a.fecha}</span>
                    </div>
                    <span style="font-size:.72rem;font-weight:600;color:${aColor};">${a.accion}</span>
                    ${a.comentario ? `<p style="font-size:.78rem;color:var(--text-muted);margin:.2rem 0 0;">${a.comentario}</p>` : ''}
                </div>
            </div>`;
        });
    }

    // Footer action
    html += `<div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--surface-border);display:flex;gap:.5rem;">
        <a href="${r.show_url}" class="btn-premium" style="flex:1;justify-content:center;font-size:.82rem;"><i class="bi bi-eye-fill"></i> Ver completo</a>
        <a href="${r.pdf_url}" target="_blank" class="btn-secondary" style="font-size:.82rem;"><i class="bi bi-download"></i> PDF</a>
    </div>`;

    drawerTitle.textContent = r.req + ' — ' + r.usuario;
    drawerContent.innerHTML = html;

    overlay.style.display = 'block';
    drawer.style.display = 'block';
    requestAnimationFrame(() => { drawer.style.right = '0'; });
};

window.closeDrawer = function() {
    drawer.style.right = '-480px';
    setTimeout(() => {
        overlay.style.display = 'none';
        drawer.style.display = 'none';
    }, 300);
};

// Close drawer on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

// ===== Table search & filter =====
const searchInput = document.getElementById('resp-search');
const filterEstado = document.getElementById('resp-filter-estado');
function filterTable() {
    const q = (searchInput?.value || '').toLowerCase();
    const estado = filterEstado?.value || '';
    document.querySelectorAll('.resp-row').forEach(row => {
        const matchSearch = !q || (row.dataset.search || '').includes(q);
        const matchEstado = !estado || row.dataset.estado === estado;
        row.style.display = matchSearch && matchEstado ? '' : 'none';
    });
}
searchInput?.addEventListener('input', filterTable);
filterEstado?.addEventListener('change', filterTable);

// ===== Bulk delete =====
function toggleSelectAll(master) {
    document.querySelectorAll('.resp-checkbox').forEach(cb => {
        const row = cb.closest('.resp-row');
        if (row && row.style.display !== 'none') cb.checked = master.checked;
    });
    updateBulkDelete();
}

function updateBulkDelete() {
    const checked = document.querySelectorAll('.resp-checkbox:checked');
    const btn = document.getElementById('btn-bulk-delete');
    const count = document.getElementById('bulk-delete-count');
    if (btn) {
        btn.style.display = checked.length > 0 ? 'inline-flex' : 'none';
        count.textContent = checked.length;
    }
}

function confirmBulkDelete() {
    const ids = [...document.querySelectorAll('.resp-checkbox:checked')].map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`¿Eliminar ${ids.length} registro(s)? Esta acción no se puede deshacer fácilmente.`)) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("respuestas.bulkDestroy") }}';
    form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE">`;
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

// ===== Dynamic options AJAX =====
const _csrf = '{{ csrf_token() }}';

function saveOpcion(id, btn) {
    const input = document.getElementById('opcion-input-' + id);
    const valor = input.value.trim();
    if (!valor) return;

    btn.style.opacity = '.4';
    btn.disabled = true;

    fetch('/campo-opciones/' + id, {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': _csrf,'Accept':'application/json'},
        body: JSON.stringify({valor})
    })
    .then(r => { if (!r.ok) throw r; return r.json(); })
    .then(data => {
        if (data.merged) {
            // Option was merged into existing — remove this row
            const row = document.getElementById('opcion-row-' + id);
            row.style.transition = 'opacity .3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            // Flash green border briefly
            input.style.borderColor = '#10b981';
            setTimeout(() => input.style.borderColor = '', 1200);
        }
        if (typeof showToast === 'function') showToast(data.message, 'success');
    })
    .catch(async err => {
        let msg = 'Error al guardar';
        try { const j = await err.json(); msg = j.message || msg; } catch(e) {}
        if (typeof showToast === 'function') showToast(msg, 'error');
        else alert(msg);
    })
    .finally(() => { btn.style.opacity = '1'; btn.disabled = false; });
}

function deleteOpcion(id, btn) {
    if (!confirm('¿Eliminar esta opción?')) return;

    btn.style.opacity = '.4';
    btn.disabled = true;

    fetch('/campo-opciones/' + id, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': _csrf,'Accept':'application/json'}
    })
    .then(r => { if (!r.ok) throw r; return r.json(); })
    .then(() => {
        const row = document.getElementById('opcion-row-' + id);
        row.style.transition = 'opacity .3s';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 300);
        if (typeof showToast === 'function') showToast('Opción eliminada', 'success');
    })
    .catch(() => {
        btn.style.opacity = '1'; btn.disabled = false;
        if (typeof showToast === 'function') showToast('Error al eliminar', 'error');
    });
}
</script>
@endpush
