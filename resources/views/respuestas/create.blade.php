@extends('layouts.app')

@section('title', 'Nuevo Formulario')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Formulario</h2>
            <p class="page-subheading">Completa el formulario dinámico</p>
        </div>
        <a href="{{ url()->previous() }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @if(!$formulario)
    <!-- Selección de formulario -->
    <div class="glass-card">
        <h3 style="font-size:1rem;margin-bottom:1.25rem;">Selecciona el tipo de formulario</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;">
            @forelse($formularios as $f)
            <a href="{{ route('respuestas.create', ['formulario_id' => $f->id]) }}" 
               style="text-decoration:none;">
                <div class="glass-card" style="cursor:pointer;transition:all 0.2s;border:1px solid var(--surface-border);"
                    onmouseover="this.style.borderColor='var(--primary-color)'"
                    onmouseout="this.style.borderColor='var(--surface-border)'">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
                        <div class="stat-icon primary" style="width:40px;height:40px;border-radius:10px;font-size:1rem;">
                            <i class="bi bi-ui-checks"></i>
                        </div>
                        <div>
                            <strong style="font-size:0.9rem;">{{ $f->nombre }}</strong><br>
                            <span style="font-size:0.75rem;color:var(--text-muted);">{{ $f->departamento->nombre ?? 'General' }}</span>
                        </div>
                    </div>
                    @if($f->descripcion)
                        <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">{{ $f->descripcion }}</p>
                    @endif
                    @if($f->requiere_aprobacion)
                        <div style="margin-top:0.75rem;">
                            <span class="badge warning" style="font-size:0.7rem;"><i class="bi bi-shield-check"></i> Requiere aprobación</span>
                        </div>
                    @endif
                </div>
            </a>
            @empty
            <div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:2rem;">
                <i class="bi bi-ui-checks-grid" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                No hay formularios disponibles
            </div>
            @endforelse
        </div>
    </div>
    @else
    <!-- Formulario dinámico -->
    <div class="glass-card">
        <div style="margin-bottom:1.5rem;">
            <h3 style="font-size:1.1rem;font-weight:600;">{{ $formulario->nombre }}</h3>
            @if($formulario->descripcion)
                <p style="color:var(--text-muted);font-size:0.9rem;margin:0.25rem 0 0;">{{ $formulario->descripcion }}</p>
            @endif
            @if($formulario->requiere_aprobacion)
                <span class="badge warning" style="margin-top:0.5rem;display:inline-block;">
                    <i class="bi bi-shield-check"></i> Este formulario requiere aprobación
                </span>
            @endif
        </div>

        <form method="POST" action="{{ route('respuestas.store') }}" id="dynamic-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="formulario_id" value="{{ $formulario->id }}">
            <input type="hidden" name="datos_json" id="datos_json" value="{}">

            @php $schema = json_decode($formulario->schema_json ?? '[]', true); @endphp

            @foreach($schema as $field)
                @php
                    $hasCond = !empty($field['condition']['fieldId']);
                    $condAttr = $hasCond ? 'data-cond-field="'.$field['condition']['fieldId'].'" data-cond-op="'.$field['condition']['operator'].'" data-cond-val="'.($field['condition']['value'] ?? '').'"' : '';
                @endphp
                @if($field['type'] === 'divider')
                    <div class="cond-wrap" {!! $condAttr !!}>
                        <hr style="border-color:var(--surface-border);margin:1.5rem 0;">
                        <p style="text-align:center;color:var(--text-muted);font-size:0.85rem;">{{ $field['label'] }}</p>
                    </div>
                @else
                    <div class="form-group cond-wrap" {!! $condAttr !!}>
                        <label>
                            {{ $field['label'] }}
                            @if(!empty($field['required'])) <span style="color:#ef4444">*</span> @endif
                        </label>

                        @if($field['type'] === 'text')
                            <input type="text" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>

                        @elseif($field['type'] === 'textarea')
                            <textarea id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                rows="3" placeholder="{{ $field['placeholder'] ?? '' }}"
                                {{ !empty($field['required']) ? 'required' : '' }}></textarea>

                        @elseif($field['type'] === 'number')
                            <input type="number" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                @isset($field['min']) min="{{ $field['min'] }}" @endisset
                                @isset($field['max']) max="{{ $field['max'] }}" @endisset
                                {{ !empty($field['required']) ? 'required' : '' }}>

                        @elseif($field['type'] === 'date')
                            <input type="date" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>

                        @elseif($field['type'] === 'select')
                            <select id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>
                                <option value="">Seleccionar...</option>
                                @foreach($field['options'] ?? [] as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>

                        @elseif($field['type'] === 'radio')
                            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem;">
                                @foreach($field['options'] ?? [] as $opt)
                                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                        <input type="radio" name="field_{{ $field['id'] }}" value="{{ $opt }}"
                                            onchange="updateRadio('{{ $field['id'] }}', this.value)"
                                            {{ !empty($field['required']) ? 'required' : '' }}>
                                        {{ $opt }}
                                    </label>
                                @endforeach
                            </div>

                        @elseif($field['type'] === 'checkbox')
                            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem;">
                                @foreach($field['options'] ?? [] as $opt)
                                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                        <input type="checkbox" value="{{ $opt }}"
                                            data-check-group="{{ $field['id'] }}"
                                            onchange="updateCheckbox('{{ $field['id'] }}')">
                                        {{ $opt }}
                                    </label>
                                @endforeach
                            </div>

                        @elseif($field['type'] === 'signature')
                            <div style="border:1px solid var(--surface-border);border-radius:10px;overflow:hidden;background:white;">
                                <canvas id="sig_{{ $field['id'] }}" width="600" height="150"
                                    style="width:100%;height:150px;display:block;cursor:crosshair;touch-action:none;"></canvas>
                            </div>
                            <input type="hidden" id="field_{{ $field['id'] }}" data-id="{{ $field['id'] }}" class="field-input">
                            <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                                <button type="button" class="btn-ghost" style="font-size:0.8rem;"
                                    onclick="clearSignature('{{ $field['id'] }}')">
                                    <i class="bi bi-eraser"></i> Limpiar firma
                                </button>
                            </div>

                        @elseif($field['type'] === 'file')
                            <input type="file" name="file_{{ $field['id'] }}" id="field_{{ $field['id'] }}"
                                class="form-input" data-id="{{ $field['id'] }}" data-is-file="1"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                {{ !empty($field['required']) ? 'required' : '' }}>
                            <small style="color:var(--text-muted);font-size:.75rem">
                                PDF, Word, Excel o imágenes (máx. 10MB)
                            </small>

                        @elseif($field['type'] === 'select_dynamic')
                            <div class="dynamic-select-wrap" data-field-id="{{ $field['id'] }}"
                                 data-url="{{ route('campo-opciones.index', [$formulario->id, $field['id']]) }}"
                                 data-store-url="{{ route('campo-opciones.store', [$formulario->id, $field['id']]) }}"
                                 style="position:relative;">
                                <div>
                                    <input type="text" class="form-input ds-search" autocomplete="off"
                                        placeholder="{{ $field['placeholder'] ?? 'Buscar o agregar...' }}"
                                        style="padding-right:2.5rem">
                                    <i class="bi bi-chevron-down" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                                </div>
                                <div class="ds-dropdown" style="display:none;position:absolute;left:0;right:0;z-index:999;
                                    max-height:200px;overflow-y:auto;background:var(--surface-card-solid, #fff);border:1px solid var(--surface-border, #d1d5db);
                                    border-radius:8px;margin-top:2px;box-shadow:0 8px 24px rgba(0,0,0,.18)">
                                </div>
                                <input type="hidden" id="field_{{ $field['id'] }}" class="field-input"
                                    data-id="{{ $field['id'] }}" {{ !empty($field['required']) ? 'required' : '' }}>
                            </div>

                        @elseif($field['type'] === 'select_tabla')
                            @php
                                $tablaData = [];
                                $t = $field['tabla'] ?? '';
                                if ($t === 'usuarios') {
                                    $tablaData = \App\Models\User::where('activo', true)->orderBy('name')->get()
                                        ->mapWithKeys(fn($u) => [$u->id => $u->name . ' ' . ($u->apellido_paterno ?? '')]);
                                } elseif ($t === 'departamentos') {
                                    $tablaData = \App\Models\Departamento::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');
                                } elseif ($t === 'cargos') {
                                    $tablaData = \App\Models\Cargo::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');
                                } elseif ($t === 'centros_costo') {
                                    $tablaData = \App\Models\CentroCosto::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');
                                }
                            @endphp
                            <select id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>
                                <option value="">Seleccionar...</option>
                                @foreach($tablaData as $tId => $tNombre)
                                    <option value="{{ $tNombre }}">{{ $tNombre }}</option>
                                @endforeach
                            </select>
                            <small style="color:var(--text-muted);font-size:.72rem">
                                <i class="bi bi-database"></i> Datos del sistema
                            </small>

                        @elseif($field['type'] === 'auto')
                            @php
                                $autoVal = '';
                                $fuente = $field['fuente'] ?? '';
                                $u = auth()->user();
                                if ($fuente === 'usuario_nombre') {
                                    $autoVal = $u->name . ' ' . ($u->apellido_paterno ?? '');
                                } elseif ($fuente === 'usuario_email') {
                                    $autoVal = $u->email;
                                } elseif ($fuente === 'usuario_cargo') {
                                    $autoVal = optional($u->cargo)->nombre ?? 'Sin cargo';
                                } elseif ($fuente === 'usuario_departamento') {
                                    $autoVal = optional($u->departamento)->nombre ?? 'Sin departamento';
                                } elseif ($fuente === 'usuario_centro_costo') {
                                    $autoVal = optional($u->centroCosto)->nombre ?? 'Sin centro de costo';
                                } elseif ($fuente === 'fecha_actual') {
                                    $autoVal = now()->format('d/m/Y');
                                } elseif ($fuente === 'hora_actual') {
                                    $autoVal = now()->format('H:i');
                                } elseif ($fuente === 'fecha_hora_actual') {
                                    $autoVal = now()->format('d/m/Y H:i');
                                }
                            @endphp
                            <input type="text" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                value="{{ $autoVal }}" readonly
                                style="background:rgba(139,92,246,.05);border-color:rgba(139,92,246,.2);color:var(--text-color);">
                            <small style="color:#8b5cf6;font-size:.72rem">
                                <i class="bi bi-lightning-charge"></i> Completado automáticamente
                            </small>
                        @endif
                    </div>
                @endif
            @endforeach

            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.75rem;flex-wrap:wrap;">
                <a href="{{ url()->previous() }}" class="btn-ghost" style="font-size:.85rem;">Cancelar</a>
                <button type="submit" name="estado" value="Borrador" class="btn-secondary" style="font-size:.85rem;">
                    <i class="bi bi-save"></i> Guardar Borrador
                </button>
                <button type="submit" name="estado" value="Pendiente" class="btn-premium" style="flex:1;min-width:160px;justify-content:center;font-size:.85rem;">
                    <i class="bi bi-send-fill"></i> Enviar Formulario
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
// ── Recopilar datos del formulario en JSON ──
const datos = {};

function getDatos() {
    document.querySelectorAll('.field-input').forEach(el => {
        if (el.type !== 'hidden' && !el.dataset.isFile) {
            datos[el.dataset.id] = el.value;
        }
    });
    return datos;
}

document.getElementById('dynamic-form')?.addEventListener('submit', function(e) {
    getDatos();
    document.getElementById('datos_json').value = JSON.stringify(datos);
});

function updateRadio(id, val) { datos[id] = val; }

function updateCheckbox(id) {
    const checked = [...document.querySelectorAll(`[data-check-group="${id}"]:checked`)]
        .map(c => c.value);
    datos[id] = checked;
}

// ── Firma digital (canvas) ──
document.querySelectorAll('canvas[id^="sig_"]').forEach(canvas => {
    const fieldId = canvas.id.replace('sig_', '');
    const hidden = document.getElementById('field_' + fieldId);
    const ctx = canvas.getContext('2d');
    let drawing = false;
    ctx.strokeStyle = '#1e1b4b';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';

    const getPos = e => {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - rect.left) * scaleX, y: (src.clientY - rect.top) * scaleY };
    };

    canvas.addEventListener('mousedown', e => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup', () => { drawing = false; hidden.value = canvas.toDataURL(); dados[fieldId] = hidden.value; });
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    canvas.addEventListener('touchend', () => { drawing = false; hidden.value = canvas.toDataURL(); datos[fieldId] = hidden.value; });
});

window.clearSignature = function(id) {
    const canvas = document.getElementById('sig_' + id);
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('field_' + id).value = '';
    delete datos[id];
};

// ── Conditional visibility ──
function evalConditions() {
    document.querySelectorAll('.cond-wrap[data-cond-field]').forEach(wrap => {
        const depId = wrap.dataset.condField;
        const op = wrap.dataset.condOp;
        const expected = wrap.dataset.condVal;

        // Get current value of the dependency field
        let val = '';
        const el = document.getElementById('field_' + depId);
        if (el) {
            val = el.value;
        } else {
            // Radio button
            const radio = document.querySelector(`input[name="field_${depId}"]:checked`);
            if (radio) val = radio.value;
            // Checkbox group
            const checks = document.querySelectorAll(`[data-check-group="${depId}"]:checked`);
            if (checks.length) val = [...checks].map(c => c.value).join(',');
        }

        let visible = false;
        switch (op) {
            case 'equals': visible = val === expected; break;
            case 'not_equals': visible = val !== expected; break;
            case 'filled': visible = val !== '' && val !== null && val !== undefined; break;
            case 'empty': visible = val === '' || val === null || val === undefined; break;
        }

        wrap.style.display = visible ? '' : 'none';
        // Disable required on hidden fields so form can submit
        wrap.querySelectorAll('[required]').forEach(inp => {
            inp.dataset.wasRequired = '1';
            if (!visible) inp.removeAttribute('required');
            else if (inp.dataset.wasRequired) inp.setAttribute('required', '');
        });
    });
}

// Attach listeners to all field inputs
document.querySelectorAll('.field-input, input[type="radio"], [data-check-group]').forEach(el => {
    el.addEventListener('change', evalConditions);
    el.addEventListener('input', evalConditions);
});
evalConditions();

// ── Dynamic Select (select_dynamic) ──
document.querySelectorAll('.dynamic-select-wrap').forEach(wrap => {
    const fieldId = wrap.dataset.fieldId;
    const url = wrap.dataset.url;
    const storeUrl = wrap.dataset.storeUrl;
    const searchInput = wrap.querySelector('.ds-search');
    const dropdown = wrap.querySelector('.ds-dropdown');
    const hidden = wrap.querySelector('input[type="hidden"]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let debounceTimer;

    function renderDropdown(items, query) {
        let html = '';
        if (items.length === 0 && query.length === 0) {
            html = '<div style="padding:.6rem .75rem;font-size:.82rem;color:var(--text-muted)">Escribe para buscar o crear...</div>';
        } else {
            items.forEach(val => {
                html += `<div class="ds-option" data-val="${val.replace(/"/g, '&quot;')}"
                    style="padding:.5rem .75rem;font-size:.85rem;cursor:pointer;transition:background .1s">${val}</div>`;
            });
            if (query.length > 0 && !items.map(i => i.toLowerCase()).includes(query.toLowerCase())) {
                html += `<div class="ds-option ds-create" data-val="${query.replace(/"/g, '&quot;')}"
                    style="padding:.5rem .75rem;font-size:.85rem;cursor:pointer;
                    border-top:1px solid currentColor;opacity:.9;display:flex;align-items:center;gap:.4rem">
                    <i class="bi bi-plus-circle"></i> Crear "<strong>${query}</strong>"
                </div>`;
            }
        }
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.ds-option').forEach(opt => {
            opt.addEventListener('mousedown', e => {
                e.preventDefault();
                selectOption(opt.dataset.val, opt.classList.contains('ds-create'));
            });
        });
    }

    function selectOption(val, isNew) {
        if (isNew) {
            // POST to create the option
            fetch(storeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ valor: val })
            }).catch(() => {});
        }
        searchInput.value = val;
        hidden.value = val;
        datos[fieldId] = val;
        dropdown.style.display = 'none';
        hidden.dispatchEvent(new Event('change'));
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        debounceTimer = setTimeout(() => {
            fetch(url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(items => renderDropdown(items, q))
            .catch(() => {});
        }, 250);
    });

    searchInput.addEventListener('focus', function() {
        const q = this.value.trim();
        fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(items => renderDropdown(items, q))
            .catch(() => {});
    });

    searchInput.addEventListener('blur', function() {
        setTimeout(() => { dropdown.style.display = 'none'; }, 200);
    });
});
</script>
@endpush
