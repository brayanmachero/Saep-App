@extends('layouts.app')

@section('title', 'Editar Formulario')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Formulario</h2>
            <p class="page-subheading">{{ $formulario->nombre }}</p>
        </div>
        <a href="{{ route('formularios.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form method="POST" action="{{ route('formularios.update', $formulario) }}" id="form-builder-form">
        @csrf
        @method('PUT')

        <!-- Info básica -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:1rem;margin-bottom:1.25rem;color:var(--text-muted);">
                <i class="bi bi-info-circle"></i> Información General
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" class="form-input @error('codigo') is-invalid @enderror"
                        value="{{ old('codigo', $formulario->codigo) }}"
                        placeholder="Ej: FORM-EPP-001" style="text-transform:uppercase;">
                    @error('codigo') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-input @error('nombre') is-invalid @enderror"
                        value="{{ old('nombre', $formulario->nombre) }}"
                        placeholder="Ej: Solicitud de EPP">
                    @error('nombre') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" class="form-input"
                        value="{{ old('descripcion', $formulario->descripcion) }}"
                        placeholder="Descripción breve del formulario">
                </div>
                <div class="form-group">
                    <label>Departamento</label>
                    <select name="departamento_id" class="form-input">
                        <option value="">Todos los departamentos</option>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}" {{ old('departamento_id', $formulario->departamento_id) == $dep->id ? 'selected' : '' }}>
                                {{ $dep->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria_id" class="form-input">
                        <option value="">Sin categoría</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" {{ old('categoria_id', $formulario->categoria_id) == $cat->id ? 'selected' : '' }}>
                                {{ $cat->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Programación --}}
            <h3 style="font-size:1rem;margin:1.25rem 0 0.75rem;color:var(--text-muted);">
                <i class="bi bi-calendar-range"></i> Programación
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-input"
                        value="{{ old('fecha_inicio', $formulario->fecha_inicio?->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-input"
                        value="{{ old('fecha_fin', $formulario->fecha_fin?->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label>Frecuencia</label>
                    <select name="frecuencia" class="form-input">
                        <option value="">Sin frecuencia</option>
                        @php $freq = old('frecuencia', $formulario->frecuencia); @endphp
                        <option value="unica" {{ $freq === 'unica' ? 'selected' : '' }}>Única vez</option>
                        <option value="diaria" {{ $freq === 'diaria' ? 'selected' : '' }}>Diaria</option>
                        <option value="semanal" {{ $freq === 'semanal' ? 'selected' : '' }}>Semanal</option>
                        <option value="quincenal" {{ $freq === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                        <option value="mensual" {{ $freq === 'mensual' ? 'selected' : '' }}>Mensual</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-2" style="margin-top:0.5rem;">
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="requiere_aprobacion" value="1" id="req-aprobacion"
                            {{ old('requiere_aprobacion', $formulario->requiere_aprobacion) ? 'checked' : '' }}
                            style="width:16px;height:16px;accent-color:var(--primary-color);">
                        Requiere aprobación
                    </label>
                </div>
                <div class="form-group" id="aprobador-group"
                     style="{{ old('requiere_aprobacion', $formulario->requiere_aprobacion) ? '' : 'display:none' }}">
                    <label>Rol aprobador</label>
                    <select name="aprobador_rol_id" class="form-input">
                        <option value="">Seleccionar rol</option>
                        @foreach($roles as $rol)
                            <option value="{{ $rol->id }}"
                                {{ old('aprobador_rol_id', $formulario->aprobador_rol_id) == $rol->id ? 'selected' : '' }}>
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="genera_pdf" value="1"
                            {{ old('genera_pdf', $formulario->genera_pdf) ? 'checked' : '' }}
                            style="width:16px;height:16px;accent-color:var(--primary-color);">
                        Genera PDF al completar
                    </label>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="enviar_email_respuesta" value="1" id="chk-email-resp"
                            {{ old('enviar_email_respuesta', $formulario->enviar_email_respuesta) ? 'checked' : '' }}
                            style="width:16px;height:16px;accent-color:var(--primary-color);">
                        <i class="bi bi-envelope" style="font-size:.9rem"></i> Enviar email al recibir respuesta
                    </label>
                </div>
                <div class="form-group" id="email-notif-group" style="grid-column:1/-1;{{ old('enviar_email_respuesta', $formulario->enviar_email_respuesta) ? '' : 'display:none' }}">
                    <label>Emails de destino</label>
                    <input type="text" name="email_notificacion" class="form-input"
                        value="{{ old('email_notificacion', $formulario->email_notificacion) }}"
                        placeholder="correo1@empresa.cl, correo2@empresa.cl">
                    <small style="color:var(--text-muted);font-size:.72rem">Separar múltiples emails con coma. Recibirán las respuestas completas con adjuntos.</small>
                </div>
            </div>
        </div>

        <!-- Constructor de campos -->
        <div class="form-grid-builder">
            <!-- Panel izquierdo: tipos de campo -->
            <div class="glass-card builder-toolbox">
                <h3 style="font-size:0.9rem;margin-bottom:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-tools"></i> Componentes
                </h3>
                <div class="field-types">
                    <div class="field-type-btn" draggable="true" data-type="text">
                        <i class="bi bi-input-cursor-text"></i> Texto corto
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="textarea">
                        <i class="bi bi-card-text"></i> Texto largo
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="number">
                        <i class="bi bi-123"></i> Número
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="date">
                        <i class="bi bi-calendar3"></i> Fecha
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="select">
                        <i class="bi bi-menu-button-wide"></i> Lista desplegable
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="radio">
                        <i class="bi bi-ui-radios"></i> Opción múltiple
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="checkbox">
                        <i class="bi bi-check2-square"></i> Casilla(s)
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="file">
                        <i class="bi bi-paperclip"></i> Adjunto
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="signature">
                        <i class="bi bi-pen"></i> Firma digital
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="select_dynamic">
                        <i class="bi bi-collection"></i> Lista dinámica
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="select_tabla">
                        <i class="bi bi-database"></i> Datos del sistema
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="auto">
                        <i class="bi bi-lightning-charge"></i> Campo automático
                    </div>
                    <div class="field-type-btn" draggable="true" data-type="divider">
                        <i class="bi bi-hr"></i> Separador
                    </div>
                </div>
            </div>

            <!-- Panel derecho: área de drop -->
            <div class="glass-card builder-canvas-wrapper">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">
                        <i class="bi bi-layout-wtf"></i> Campos del Formulario
                    </h3>
                    <button type="button" id="btn-preview" class="btn-secondary" style="font-size:0.8rem;">
                        <i class="bi bi-eye"></i> Vista previa
                    </button>
                </div>
                <div id="builder-canvas" class="builder-canvas">
                    <div class="canvas-placeholder" id="canvas-placeholder">
                        <i class="bi bi-arrow-left-circle" style="font-size:2rem;margin-bottom:0.5rem;display:block;"></i>
                        Arrastra campos aquí para construir el formulario
                    </div>
                </div>
                <input type="hidden" name="schema_json" id="schema_json"
                       value="{{ old('schema_json', $formulario->schema_json ?? '[]') }}">
                @error('schema_json') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- Modal de configuración de campo -->
        <div id="field-modal" class="modal-overlay" style="display:none;">
            <div class="modal-box glass-card">
                <div class="modal-header">
                    <h3 id="modal-title">Configurar campo</h3>
                    <button type="button" id="modal-close" class="icon-btn" style="width:32px;height:32px;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div id="modal-body"></div>
                <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.25rem;">
                    <button type="button" id="modal-cancel" class="btn-ghost">Cancelar</button>
                    <button type="button" id="modal-save" class="btn-premium">
                        <i class="bi bi-check-lg"></i> Guardar Campo
                    </button>
                </div>
            </div>
        </div>

        <!-- Preview modal -->
        <div id="preview-modal" class="modal-overlay" style="display:none;">
            <div class="modal-box glass-card" style="max-width:700px;max-height:85vh;overflow-y:auto;">
                <div class="modal-header">
                    <h3>Vista Previa del Formulario</h3>
                    <button type="button" id="preview-close" class="icon-btn" style="width:32px;height:32px;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div id="preview-body" style="margin-top:1rem;"></div>
            </div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
            <a href="{{ route('formularios.show', $formulario) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium" id="btn-save-form">
                <i class="bi bi-save-fill"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
.form-grid-builder {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    align-items: flex-start;
}
@media (max-width: 768px) {
    .form-grid-builder { grid-template-columns: 1fr; }
}
.builder-toolbox { padding: 1.25rem; }
.field-types { display: flex; flex-direction: column; gap: 0.5rem; }
.field-type-btn {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.65rem 0.9rem;
    border-radius: 10px;
    border: 1px dashed var(--surface-border);
    cursor: grab;
    font-size: 0.875rem;
    color: var(--text-main);
    transition: all 0.15s;
    user-select: none;
}
.field-type-btn:hover {
    border-color: var(--primary-color);
    background: rgba(79,70,229,0.08);
    color: var(--primary-color);
    transform: translateX(2px);
}
.field-type-btn:active { cursor: grabbing; }
.field-type-btn i { font-size: 1rem; }
.builder-canvas-wrapper { padding: 1.25rem; }
.builder-canvas {
    min-height: 300px;
    border: 2px dashed var(--surface-border);
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.2s;
}
.builder-canvas.drag-over {
    border-color: var(--primary-color);
    background: rgba(79,70,229,0.05);
}
.canvas-placeholder {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    min-height: 250px;
    color: var(--text-muted);
    font-size: 0.9rem;
    text-align: center;
}
.field-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: move;
    transition: all 0.15s;
}
.field-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 2px 12px rgba(79,70,229,0.12);
}
.field-card.dragging { opacity: 0.4; }
.field-card .drag-handle { color: var(--text-muted); cursor: grab; }
.field-card .field-label { flex:1; font-size:0.875rem; font-weight:500; }
.field-card .field-type-tag {
    font-size:0.75rem; color: var(--text-muted);
    background: rgba(107,114,128,0.1);
    padding: 0.2rem 0.5rem; border-radius: 6px;
}
.field-card .required-badge {
    font-size:0.7rem; color:#ef4444;
    background: rgba(239,68,68,0.1);
    padding: 0.15rem 0.4rem; border-radius:4px;
}
.field-card-actions { display:flex; gap:0.25rem; }
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.modal-box { width: 100%; max-width: 520px; max-height: 85vh; overflow-y: auto; }
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem;
}
.modal-header h3 { font-size: 1rem; font-weight: 600; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // Pre-load existing schema
    let fields = JSON.parse(document.getElementById('schema_json').value || '[]');
    let editingIndex = null;
    let draggingNewType = null;
    let draggingCardIndex = null;

    const canvas = document.getElementById('builder-canvas');
    const placeholder = document.getElementById('canvas-placeholder');
    const schemaInput = document.getElementById('schema_json');
    const modal = document.getElementById('field-modal');
    const modalBody = document.getElementById('modal-body');
    const modalTitle = document.getElementById('modal-title');

    const typeLabels = {
        text: 'Texto corto', textarea: 'Texto largo', number: 'Número',
        date: 'Fecha', select: 'Lista desplegable', radio: 'Opción múltiple',
        checkbox: 'Casilla(s)', file: 'Adjunto', signature: 'Firma digital',
        select_dynamic: 'Lista dinámica', select_tabla: 'Datos del sistema',
        auto: 'Campo automático', divider: 'Separador'
    };

    const tablaOpciones = {
        usuarios: 'Usuarios del sistema',
        departamentos: 'Departamentos',
        cargos: 'Cargos / Puestos',
        centros_costo: 'Centros de Costo',
    };

    const autoFuentes = {
        usuario_nombre: 'Nombre del usuario',
        usuario_email: 'Email del usuario',
        usuario_cargo: 'Cargo del usuario',
        usuario_departamento: 'Departamento del usuario',
        usuario_centro_costo: 'Centro de costo del usuario',
        fecha_actual: 'Fecha actual',
        hora_actual: 'Hora actual',
        fecha_hora_actual: 'Fecha y hora actual',
    };

    document.querySelectorAll('.field-type-btn').forEach(btn => {
        btn.addEventListener('dragstart', () => {
            draggingNewType = btn.dataset.type;
            draggingCardIndex = null;
        });
    });

    canvas.addEventListener('dragover', e => { e.preventDefault(); canvas.classList.add('drag-over'); });
    canvas.addEventListener('dragleave', () => canvas.classList.remove('drag-over'));
    canvas.addEventListener('drop', e => {
        e.preventDefault();
        canvas.classList.remove('drag-over');
        if (draggingNewType) {
            if (draggingNewType === 'divider') {
                fields.push({ type: 'divider', label: '── Sección ──', id: uid() });
                renderCanvas();
            } else {
                openModal(null, draggingNewType);
            }
            draggingNewType = null;
        }
    });

    function uid() {
        return 'f_' + Math.random().toString(36).substr(2, 8);
    }

    function renderCanvas() {
        if (fields.length === 0) {
            canvas.innerHTML = '';
            canvas.appendChild(placeholder);
            placeholder.style.display = 'flex';
        } else {
            canvas.innerHTML = '';
            fields.forEach((f, i) => {
                const card = document.createElement('div');
                card.className = 'field-card';
                card.draggable = true;
                card.dataset.index = i;
                card.innerHTML = `
                    <i class="bi bi-grip-vertical drag-handle"></i>
                    <span class="field-label">${f.label || f.id}</span>
                    ${f.required ? '<span class="required-badge">* Obligatorio</span>' : ''}
                    ${f.condition ? '<span class="field-type-tag" style="color:#f59e0b;background:rgba(245,158,11,.1)"><i class="bi bi-eye"></i> Cond.</span>' : ''}
                    ${f.fuente ? `<span class="field-type-tag" style="color:#8b5cf6;background:rgba(139,92,246,.1)"><i class="bi bi-lightning-charge"></i> ${autoFuentes[f.fuente] || f.fuente}</span>` : ''}
                    ${f.tabla ? `<span class="field-type-tag" style="color:#0ea5e9;background:rgba(14,165,233,.1)"><i class="bi bi-database"></i> ${tablaOpciones[f.tabla] || f.tabla}</span>` : ''}
                    <span class="field-type-tag">${typeLabels[f.type] || f.type}</span>
                    <div class="field-card-actions">
                        <button type="button" class="icon-btn" style="width:26px;height:26px;" onclick="editField(${i})" title="Editar">
                            <i class="bi bi-pencil-fill" style="font-size:0.75rem;"></i>
                        </button>
                        <button type="button" class="icon-btn danger" style="width:26px;height:26px;" onclick="removeField(${i})" title="Eliminar">
                            <i class="bi bi-trash-fill" style="font-size:0.75rem;"></i>
                        </button>
                    </div>`;
                card.addEventListener('dragstart', () => { draggingCardIndex = i; draggingNewType = null; card.classList.add('dragging'); });
                card.addEventListener('dragend', () => { card.classList.remove('dragging'); draggingCardIndex = null; });
                card.addEventListener('dragover', e => {
                    e.preventDefault();
                    if (draggingCardIndex !== null && draggingCardIndex !== i) {
                        const moved = fields.splice(draggingCardIndex, 1)[0];
                        fields.splice(i, 0, moved);
                        draggingCardIndex = i;
                        renderCanvas();
                    }
                });
                canvas.appendChild(card);
            });
        }
        schemaInput.value = JSON.stringify(fields);
    }

    window.editField = function (i) {
        editingIndex = i;
        openModal(fields[i], fields[i].type);
    };

    window.removeField = function (i) {
        fields.splice(i, 1);
        renderCanvas();
    };

    function openModal(existingField, type) {
        const isEdit = existingField !== null;
        const f = existingField || { type, id: uid(), label: '', required: false, placeholder: '', options: '' };
        modalTitle.textContent = (isEdit ? 'Editar' : 'Agregar') + ' campo: ' + (typeLabels[type] || type);
        const needsOptions = ['select', 'radio', 'checkbox'].includes(type);
        const optionsVal = f.options ? (Array.isArray(f.options) ? f.options.join('\n') : f.options) : '';

        // Build condition field options
        const condFieldOpts = fields
            .filter(cf => cf.type !== 'divider' && cf.id !== f.id)
            .map(cf => `<option value="${cf.id}" ${f.condition?.fieldId === cf.id ? 'selected' : ''}>${cf.label}</option>`)
            .join('');
        const condOp = f.condition?.operator || 'equals';
        const condVal = f.condition?.value || '';

        modalBody.innerHTML = `
            <div class="form-group">
                <label>Etiqueta / Pregunta *</label>
                <input type="text" id="m-label" class="form-input" value="${f.label || ''}" placeholder="Ej: Nombre del trabajador">
            </div>
            ${type !== 'divider' && type !== 'signature' ? `
            <div class="form-group">
                <label>Placeholder</label>
                <input type="text" id="m-placeholder" class="form-input" value="${f.placeholder || ''}" placeholder="Texto de ayuda...">
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                    <input type="checkbox" id="m-required" ${f.required ? 'checked' : ''}
                        style="width:16px;height:16px;accent-color:var(--primary-color);">
                    Campo obligatorio
                </label>
            </div>` : ''}
            ${needsOptions ? `
            <div class="form-group">
                <label>Opciones <small style="color:var(--text-muted)">(una por línea)</small></label>
                <textarea id="m-options" class="form-input" rows="5" placeholder="Opción 1\nOpción 2\nOpción 3">${optionsVal}</textarea>
            </div>` : ''}
            ${type === 'number' ? `
            <div class="form-grid-2">
                <div class="form-group"><label>Mínimo</label><input type="number" id="m-min" class="form-input" value="${f.min ?? ''}"></div>
                <div class="form-group"><label>Máximo</label><input type="number" id="m-max" class="form-input" value="${f.max ?? ''}"></div>
            </div>` : ''}
            ${type === 'file' ? `
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                    <input type="checkbox" id="m-multiple" ${f.multiple ? 'checked' : ''}
                        style="width:16px;height:16px;accent-color:var(--primary-color);">
                    Permitir múltiples archivos
                </label>
                <small style="color:var(--text-muted);font-size:.72rem">El usuario podrá subir varias fotos o documentos en un solo campo</small>
            </div>` : ''}
            ${type === 'select_tabla' ? `
            <div class="form-group">
                <label>Fuente de datos *</label>
                <select id="m-tabla" class="form-input">
                    ${Object.entries(tablaOpciones).map(([k,v]) => `<option value="${k}" ${f.tabla===k?'selected':''}>${v}</option>`).join('')}
                </select>
                <small style="color:var(--text-muted);font-size:.72rem">Se poblará automáticamente con los registros activos de la tabla seleccionada</small>
            </div>` : ''}
            ${type === 'auto' ? `
            <div class="form-group">
                <label>Dato a capturar *</label>
                <select id="m-fuente" class="form-input">
                    ${Object.entries(autoFuentes).map(([k,v]) => `<option value="${k}" ${f.fuente===k?'selected':''}>${v}</option>`).join('')}
                </select>
                <small style="color:var(--text-muted);font-size:.72rem"><i class="bi bi-info-circle"></i> Se completará automáticamente al responder el formulario (campo de solo lectura)</small>
            </div>` : ''}
            ${condFieldOpts ? `
            <div style="border-top:1px solid var(--surface-border);margin-top:.75rem;padding-top:.75rem">
                <label style="font-size:.8rem;color:var(--text-muted);display:flex;align-items:center;gap:.4rem;margin-bottom:.5rem">
                    <i class="bi bi-eye"></i> Visibilidad condicional
                </label>
                <div class="form-grid-2" style="gap:.5rem">
                    <div class="form-group">
                        <label style="font-size:.8rem">Depende de</label>
                        <select id="m-cond-field" class="form-input" style="font-size:.82rem">
                            <option value="">Sin condición</option>
                            ${condFieldOpts}
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:.8rem">Operador</label>
                        <select id="m-cond-op" class="form-input" style="font-size:.82rem">
                            <option value="equals" ${condOp==='equals'?'selected':''}>Igual a</option>
                            <option value="not_equals" ${condOp==='not_equals'?'selected':''}>Diferente de</option>
                            <option value="filled" ${condOp==='filled'?'selected':''}>Tiene valor</option>
                            <option value="empty" ${condOp==='empty'?'selected':''}>Está vacío</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="m-cond-val-wrap" style="margin-top:.4rem;${['filled','empty'].includes(condOp)?'display:none':''}">
                    <label style="font-size:.8rem">Valor</label>
                    <input type="text" id="m-cond-val" class="form-input" style="font-size:.82rem" value="${condVal}" placeholder="Valor esperado...">
                </div>
            </div>` : ''}
            <input type="hidden" id="m-id" value="${f.id}">
            <input type="hidden" id="m-type" value="${type}">`;

        const condOpSel = document.getElementById('m-cond-op');
        if (condOpSel) {
            condOpSel.addEventListener('change', () => {
                const wrap = document.getElementById('m-cond-val-wrap');
                wrap.style.display = ['filled','empty'].includes(condOpSel.value) ? 'none' : '';
            });
        }

        if (!isEdit) editingIndex = null;
        modal.style.display = 'flex';
        document.getElementById('m-label').focus();
    }

    document.getElementById('modal-save').addEventListener('click', () => {
        const label = document.getElementById('m-label').value.trim();
        if (!label) { alert('La etiqueta es obligatoria'); return; }
        const type = document.getElementById('m-type').value;
        const needsOptions = ['select', 'radio', 'checkbox'].includes(type);
        const field = {
            id: document.getElementById('m-id').value,
            type,
            label,
            placeholder: document.getElementById('m-placeholder')?.value || '',
            required: document.getElementById('m-required')?.checked || false,
        };
        if (needsOptions) {
            field.options = document.getElementById('m-options').value.split('\n').map(o => o.trim()).filter(Boolean);
        }
        if (type === 'number') {
            const mn = document.getElementById('m-min').value;
            const mx = document.getElementById('m-max').value;
            if (mn !== '') field.min = parseFloat(mn);
            if (mx !== '') field.max = parseFloat(mx);
        }
        if (type === 'file') {
            field.multiple = document.getElementById('m-multiple')?.checked || false;
        }
        if (type === 'select_tabla') {
            field.tabla = document.getElementById('m-tabla').value;
        }
        if (type === 'auto') {
            field.fuente = document.getElementById('m-fuente').value;
            field.required = false;
        }
        // Conditional visibility
        const condField = document.getElementById('m-cond-field');
        if (condField && condField.value) {
            const op = document.getElementById('m-cond-op').value;
            field.condition = { fieldId: condField.value, operator: op };
            if (!['filled', 'empty'].includes(op)) {
                field.condition.value = document.getElementById('m-cond-val').value;
            }
        }
        if (editingIndex !== null) { fields[editingIndex] = field; } else { fields.push(field); }
        editingIndex = null;
        modal.style.display = 'none';
        renderCanvas();
    });

    document.getElementById('modal-close').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('modal-cancel').addEventListener('click', () => modal.style.display = 'none');

    document.getElementById('btn-preview').addEventListener('click', () => {
        if (fields.length === 0) { alert('Agrega al menos un campo'); return; }
        let html = '<div style="display:flex;flex-direction:column;gap:1.25rem;">';
        fields.forEach(f => {
            if (f.type === 'divider') {
                html += `<div style="text-align:center;color:var(--text-muted);font-size:0.875rem;border-top:1px solid var(--surface-border);padding-top:0.75rem;">${f.label}</div>`;
            } else {
                html += `<div class="form-group"><label>${f.label}${f.required ? ' <span style="color:#ef4444">*</span>' : ''}</label>`;
                if (['text','number','date','file'].includes(f.type)) html += `<input type="${f.type}" class="form-input" placeholder="${f.placeholder||''}" disabled>`;
                else if (f.type === 'textarea') html += `<textarea class="form-input" rows="3" placeholder="${f.placeholder||''}" disabled></textarea>`;
                else if (f.type === 'select') html += `<select class="form-input" disabled><option>Seleccionar opción</option>${(f.options||[]).map(o=>`<option>${o}</option>`).join('')}</select>`;
                else if (f.type === 'radio') html += (f.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.35rem;"><input type="radio" disabled> ${o}</label>`).join('');
                else if (f.type === 'checkbox') html += (f.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.35rem;"><input type="checkbox" disabled> ${o}</label>`).join('');
                else if (f.type === 'signature') html += `<div style="border:1px dashed var(--surface-border);border-radius:8px;height:80px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-pen" style="margin-right:0.5rem;"></i> Área de firma</div>`;
                else if (f.type === 'select_dynamic') html += `<div style="border:1px dashed var(--surface-border);border-radius:8px;padding:.6rem .75rem;display:flex;align-items:center;gap:.5rem;color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-collection"></i> Lista dinámica — los usuarios podrán buscar o crear opciones</div>`;
                else if (f.type === 'select_tabla') html += `<div style="border:1px dashed var(--surface-border);border-radius:8px;padding:.6rem .75rem;display:flex;align-items:center;gap:.5rem;color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-database"></i> Vinculado a: ${tablaOpciones[f.tabla] || f.tabla}</div>`;
                else if (f.type === 'auto') html += `<div style="border:1px dashed var(--surface-border);border-radius:8px;padding:.6rem .75rem;display:flex;align-items:center;gap:.5rem;color:var(--text-muted);font-size:0.85rem;background:rgba(139,92,246,.05);"><i class="bi bi-lightning-charge" style="color:#8b5cf6"></i> Automático: ${autoFuentes[f.fuente] || f.fuente}</div>`;
                html += '</div>';
            }
        });
        html += '</div>';
        document.getElementById('preview-body').innerHTML = html;
        document.getElementById('preview-modal').style.display = 'flex';
    });
    document.getElementById('preview-close').addEventListener('click', () => {
        document.getElementById('preview-modal').style.display = 'none';
    });

    document.getElementById('req-aprobacion').addEventListener('change', function () {
        document.getElementById('aprobador-group').style.display = this.checked ? '' : 'none';
    });

    // Toggle email notificación
    document.getElementById('chk-email-resp').addEventListener('change', function () {
        document.getElementById('email-notif-group').style.display = this.checked ? '' : 'none';
    });

    document.getElementById('form-builder-form').addEventListener('submit', e => {
        if (fields.length === 0) {
            e.preventDefault();
            alert('Debes agregar al menos un campo al formulario');
            return;
        }
        schemaInput.value = JSON.stringify(fields);
    });

    // Render pre-loaded schema on page load
    renderCanvas();
})();
</script>
@endpush
