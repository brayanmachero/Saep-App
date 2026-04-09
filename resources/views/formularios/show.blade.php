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
            <a href="{{ route('formularios.edit', $formulario) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
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
        <div>
            <!-- Información general -->
            <div class="glass-card" style="margin-bottom:1.25rem;">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-info-circle"></i> Información General
                </h3>
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

            <!-- Vista previa de campos -->
            <div class="glass-card">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1.25rem;">
                    <i class="bi bi-layout-wtf"></i> Campos del Formulario
                    <span style="font-size:0.8rem;font-weight:400;normal;text-transform:none;letter-spacing:0;">
                        — {{ count($schema) }} campo(s)
                    </span>
                </h3>

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
                                            'select_dynamic'=>'Lista dinámica'
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
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <!-- Historial de versiones -->
            @if($formulario->versiones->count() > 0)
            <div class="glass-card">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-clock-history"></i> Historial de versiones
                    <span style="font-size:0.8rem;font-weight:400;text-transform:none;letter-spacing:0;">
                        — {{ $formulario->versiones->count() }} versión(es) anterior(es)
                    </span>
                </h3>

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
            @endif
        </div>

        <!-- Columna lateral: estadísticas -->
        <div>
            <div class="glass-card" style="margin-bottom:1rem;">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-bar-chart-fill"></i> Estadísticas
                </h3>
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:0.6rem 0.75rem;background:rgba(79,70,229,0.07);border-radius:8px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Total solicitudes</span>
                        <strong style="font-size:1.25rem;color:var(--primary-color);">{{ $stats['total'] }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:0.6rem 0.75rem;background:rgba(234,179,8,0.08);border-radius:8px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Pendientes</span>
                        <strong style="font-size:1.1rem;color:#d97706;">{{ $stats['pendientes'] }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:0.6rem 0.75rem;background:rgba(34,197,94,0.08);border-radius:8px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Aprobadas</span>
                        <strong style="font-size:1.1rem;color:#16a34a;">{{ $stats['aprobadas'] }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:0.6rem 0.75rem;background:rgba(239,68,68,0.08);border-radius:8px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Rechazadas</span>
                        <strong style="font-size:1.1rem;color:#dc2626;">{{ $stats['rechazadas'] }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:0.6rem 0.75rem;background:rgba(107,114,128,0.08);border-radius:8px;">
                        <span style="font-size:0.85rem;color:var(--text-muted);">Borradores</span>
                        <strong style="font-size:1.1rem;color:var(--text-muted);">{{ $stats['borradores'] }}</strong>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="glass-card">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-gear-fill"></i> Acciones
                </h3>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <a href="{{ route('respuestas.create', ['formulario_id' => $formulario->id]) }}"
                       class="btn-premium" style="justify-content:center;">
                        <i class="bi bi-plus-circle-fill"></i> Nueva Solicitud
                    </a>
                    <a href="{{ route('formularios.edit', $formulario) }}"
                       class="btn-secondary" style="justify-content:center;">
                        <i class="bi bi-pencil-fill"></i> Editar Formulario
                    </a>
                    <a href="{{ route('respuestas.index', ['formulario_id' => $formulario->id]) }}"
                       class="btn-ghost" style="justify-content:center;">
                        <i class="bi bi-list-ul"></i> Ver Solicitudes
                    </a>

                    @if($stats['total'] === 0)
                    <form method="POST" action="{{ route('formularios.destroy', $formulario) }}"
                          onsubmit="return confirm('¿Eliminar este formulario?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost danger" style="width:100%;justify-content:center;">
                            <i class="bi bi-trash-fill"></i> Eliminar
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Panel de asignación --}}
            <div class="glass-card">
                <h3 style="font-size:0.875rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.05em;margin-bottom:1rem;">
                    <i class="bi bi-people-fill"></i> Asignaciones
                    <span class="badge" style="margin-left:.3rem">{{ $asignados->count() }}</span>
                </h3>

                <form method="POST" action="{{ route('formularios.asignar', $formulario) }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:.75rem">
                        <select name="modo" id="assign-modo" class="form-input" style="font-size:.82rem">
                            <option value="usuarios">Por usuario(s)</option>
                            <option value="departamento">Por departamento</option>
                        </select>
                    </div>

                    <div id="assign-usuarios">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <select name="user_ids[]" multiple class="form-input" style="font-size:.82rem;min-height:90px">
                                @foreach($usuariosDisp as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <small style="color:var(--text-muted)">Ctrl+click para seleccionar varios</small>
                        </div>
                    </div>

                    <div id="assign-depto" style="display:none">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <select name="departamento_id" class="form-input" style="font-size:.82rem">
                                <option value="">Seleccionar depto.</option>
                                @foreach($departamentos as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
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
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('assign-modo')?.addEventListener('change', function() {
    document.getElementById('assign-usuarios').style.display = this.value === 'usuarios' ? '' : 'none';
    document.getElementById('assign-depto').style.display = this.value === 'departamento' ? '' : 'none';
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
</script>
@endpush
