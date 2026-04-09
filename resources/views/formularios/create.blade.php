@extends('layouts.app')

@section('title', 'Nuevo Formulario')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Formulario</h2>
            <p class="page-subheading">Diseña el formulario dinámicamente</p>
        </div>
        <a href="{{ route('formularios.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form method="POST" action="{{ route('formularios.store') }}" id="form-builder-form">
        @csrf

        <!-- Info básica -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:1rem;margin-bottom:1.25rem;color:var(--text-muted);">
                <i class="bi bi-info-circle"></i> Información General
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" class="form-input" value="{{ $nextCodigo }}" readonly
                        style="text-transform:uppercase;background:rgba(255,255,255,.03);color:var(--text-muted);cursor:not-allowed">
                    <small style="color:var(--text-muted);font-size:.72rem">Se genera automáticamente</small>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-input @error('nombre') is-invalid @enderror"
                        value="{{ old('nombre') }}" placeholder="Ej: Solicitud de EPP">
                    @error('nombre') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" class="form-input"
                        value="{{ old('descripcion') }}" placeholder="Descripción breve del formulario">
                </div>
                <div class="form-group">
                    <label>Departamento</label>
                    <select name="departamento_id" class="form-input">
                        <option value="">Todos los departamentos</option>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}" {{ old('departamento_id') == $dep->id ? 'selected' : '' }}>
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
                            <option value="{{ $cat->id }}" {{ old('categoria_id') == $cat->id ? 'selected' : '' }}>
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
                    <input type="date" name="fecha_inicio" class="form-input" value="{{ old('fecha_inicio') }}">
                </div>
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-input" value="{{ old('fecha_fin') }}">
                </div>
                <div class="form-group">
                    <label>Frecuencia</label>
                    <select name="frecuencia" class="form-input">
                        <option value="">Sin frecuencia</option>
                        <option value="unica" {{ old('frecuencia') === 'unica' ? 'selected' : '' }}>Única vez</option>
                        <option value="diaria" {{ old('frecuencia') === 'diaria' ? 'selected' : '' }}>Diaria</option>
                        <option value="semanal" {{ old('frecuencia') === 'semanal' ? 'selected' : '' }}>Semanal</option>
                        <option value="quincenal" {{ old('frecuencia') === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                        <option value="mensual" {{ old('frecuencia') === 'mensual' ? 'selected' : '' }}>Mensual</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-2" style="margin-top:0.5rem;">
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="requiere_aprobacion" value="1" id="req-aprobacion"
                            {{ old('requiere_aprobacion') ? 'checked' : '' }}
                            style="width:16px;height:16px;accent-color:var(--primary-color);">
                        Requiere aprobación
                    </label>
                </div>
                <div class="form-group" id="aprobador-group" style="{{ old('requiere_aprobacion') ? '' : 'display:none' }}">
                    <label>Rol aprobador</label>
                    <select name="aprobador_rol_id" class="form-input">
                        <option value="">Seleccionar rol</option>
                        @foreach($roles as $rol)
                            <option value="{{ $rol->id }}" {{ old('aprobador_rol_id') == $rol->id ? 'selected' : '' }}>
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="genera_pdf" value="1"
                            {{ old('genera_pdf') ? 'checked' : '' }}
                            style="width:16px;height:16px;accent-color:var(--primary-color);">
                        Genera PDF al completar
                    </label>
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
                <input type="hidden" name="schema_json" id="schema_json" value="{{ old('schema_json', '[]') }}">
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
            <a href="{{ route('formularios.index') }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium" id="btn-save-form">
                <i class="bi bi-save-fill"></i> Guardar Formulario
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
    font-size:0.75rem;
    color: var(--text-muted);
    background: rgba(107,114,128,0.1);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
}
.field-card .required-badge {
    font-size:0.7rem; color:#ef4444;
    background: rgba(239,68,68,0.1);
    padding: 0.15rem 0.4rem;
    border-radius:4px;
}
.field-card-actions { display:flex; gap:0.25rem; }

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal-box {
    width: 100%;
    max-width: 520px;
    max-height: 85vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}
.modal-header h3 { font-size: 1rem; font-weight: 600; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    let fields = [];
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
        select_dynamic: 'Lista dinámica', divider: 'Separador'
    };
    const typeIcons = {
        text: 'bi-input-cursor-text', textarea: 'bi-card-text', number: 'bi-123',
        date: 'bi-calendar3', select: 'bi-menu-button-wide', radio: 'bi bi-ui-radios',
        checkbox: 'bi-check2-square', file: 'bi-paperclip', signature: 'bi-pen',
        select_dynamic: 'bi-collection', divider: 'bi-hr'
    };

    // ── Drag from toolbox ──
    document.querySelectorAll('.field-type-btn').forEach(btn => {
        btn.addEventListener('dragstart', e => {
            draggingNewType = btn.dataset.type;
            draggingCardIndex = null;
        });
    });

    canvas.addEventListener('dragover', e => {
        e.preventDefault();
        canvas.classList.add('drag-over');
    });
    canvas.addEventListener('dragleave', () => {
        canvas.classList.remove('drag-over');
    });
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
                    <span class="field-type-tag">${typeLabels[f.type] || f.type}</span>
                    <div class="field-card-actions">
                        <button type="button" class="icon-btn" style="width:26px;height:26px;" onclick="editField(${i})" title="Editar">
                            <i class="bi bi-pencil-fill" style="font-size:0.75rem;"></i>
                        </button>
                        <button type="button" class="icon-btn danger" style="width:26px;height:26px;" onclick="removeField(${i})" title="Eliminar">
                            <i class="bi bi-trash-fill" style="font-size:0.75rem;"></i>
                        </button>
                    </div>
                `;

                // Reorder drag
                card.addEventListener('dragstart', e => {
                    draggingCardIndex = i;
                    draggingNewType = null;
                    card.classList.add('dragging');
                });
                card.addEventListener('dragend', () => {
                    card.classList.remove('dragging');
                    draggingCardIndex = null;
                });
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

        // Build condition field options from existing fields (excluding dividers and current field)
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
                <div class="form-group">
                    <label>Mínimo</label>
                    <input type="number" id="m-min" class="form-input" value="${f.min ?? ''}">
                </div>
                <div class="form-group">
                    <label>Máximo</label>
                    <input type="number" id="m-max" class="form-input" value="${f.max ?? ''}">
                </div>
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
            <input type="hidden" id="m-type" value="${type}">
        `;

        // Toggle value field based on operator
        const condOpSel = document.getElementById('m-cond-op');
        if (condOpSel) {
            condOpSel.addEventListener('change', () => {
                const wrap = document.getElementById('m-cond-val-wrap');
                wrap.style.display = ['filled','empty'].includes(condOpSel.value) ? 'none' : '';
            });
        }

        editingIndex = isEdit ? editingIndex : null;
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
            const raw = document.getElementById('m-options').value;
            field.options = raw.split('\n').map(o => o.trim()).filter(Boolean);
        }
        if (type === 'number') {
            const mn = document.getElementById('m-min').value;
            const mx = document.getElementById('m-max').value;
            if (mn !== '') field.min = parseFloat(mn);
            if (mx !== '') field.max = parseFloat(mx);
        }

        // Conditional visibility
        const condField = document.getElementById('m-cond-field');
        if (condField && condField.value) {
            const op = document.getElementById('m-cond-op').value;
            field.condition = {
                fieldId: condField.value,
                operator: op,
            };
            if (!['filled', 'empty'].includes(op)) {
                field.condition.value = document.getElementById('m-cond-val').value;
            }
        }

        if (editingIndex !== null) {
            fields[editingIndex] = field;
        } else {
            fields.push(field);
        }
        editingIndex = null;
        modal.style.display = 'none';
        renderCanvas();
    });

    document.getElementById('modal-close').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('modal-cancel').addEventListener('click', () => modal.style.display = 'none');

    // Preview
    document.getElementById('btn-preview').addEventListener('click', () => {
        const previewModal = document.getElementById('preview-modal');
        const previewBody = document.getElementById('preview-body');
        if (fields.length === 0) { alert('Agrega al menos un campo'); return; }
        let html = '<div style="display:flex;flex-direction:column;gap:1.25rem;">';
        fields.forEach(f => {
            if (f.type === 'divider') {
                html += `<div style="text-align:center;color:var(--text-muted);font-size:0.875rem;border-top:1px solid var(--surface-border);padding-top:0.75rem;">${f.label}</div>`;
            } else {
                html += `<div class="form-group"><label>${f.label}${f.required ? ' <span style="color:#ef4444">*</span>' : ''}</label>`;
                if (['text','number','date','file'].includes(f.type)) {
                    html += `<input type="${f.type}" class="form-input" placeholder="${f.placeholder || ''}" disabled>`;
                } else if (f.type === 'textarea') {
                    html += `<textarea class="form-input" rows="3" placeholder="${f.placeholder || ''}" disabled></textarea>`;
                } else if (f.type === 'select') {
                    html += `<select class="form-input" disabled><option>Seleccionar opción</option>${(f.options||[]).map(o=>`<option>${o}</option>`).join('')}</select>`;
                } else if (f.type === 'radio') {
                    html += (f.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.35rem;"><input type="radio" disabled> ${o}</label>`).join('');
                } else if (f.type === 'checkbox') {
                    html += (f.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.35rem;"><input type="checkbox" disabled> ${o}</label>`).join('');
                } else if (f.type === 'signature') {
                    html += `<div style="border:1px dashed var(--surface-border);border-radius:8px;height:80px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-pen" style="margin-right:0.5rem;"></i> Área de firma</div>`;
                } else if (f.type === 'select_dynamic') {
                    html += `<div style="border:1px dashed var(--surface-border);border-radius:8px;padding:.6rem .75rem;display:flex;align-items:center;gap:.5rem;color:var(--text-muted);font-size:0.85rem;"><i class="bi bi-collection"></i> Lista dinámica — los usuarios podrán buscar o crear opciones</div>`;
                }
                html += '</div>';
            }
        });
        html += '</div>';
        previewBody.innerHTML = html;
        previewModal.style.display = 'flex';
    });
    document.getElementById('preview-close').addEventListener('click', () => {
        document.getElementById('preview-modal').style.display = 'none';
    });

    // Toggle aprobador
    document.getElementById('req-aprobacion').addEventListener('change', function () {
        document.getElementById('aprobador-group').style.display = this.checked ? '' : 'none';
    });

    // Form submit
    document.getElementById('form-builder-form').addEventListener('submit', e => {
        if (fields.length === 0) {
            e.preventDefault();
            alert('Debes agregar al menos un campo al formulario');
            return;
        }
        schemaInput.value = JSON.stringify(fields);
    });

    renderCanvas();
})();
</script>
@endpush
