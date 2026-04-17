@extends('layouts.app')

@section('title', 'Editar Solicitud #' . str_pad($respuesta->id, 5, '0', STR_PAD_LEFT))

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Solicitud</h2>
            <p class="page-subheading">{{ $respuesta->formulario->nombre }}</p>
        </div>
        <a href="{{ route('respuestas.show', $respuesta) }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @if($respuesta->estado !== 'Borrador')
        <div class="alert alert-danger">
            <i class="bi bi-lock-fill"></i> Solo se pueden editar solicitudes en estado <strong>Borrador</strong>.
        </div>
    @else
    <div class="glass-card">
        <div style="margin-bottom:1.5rem;">
            <h3 style="font-size:1.1rem;font-weight:600;">{{ $respuesta->formulario->nombre }}</h3>
            @if($respuesta->formulario->descripcion)
                <p style="color:var(--text-muted);font-size:0.9rem;margin:0.25rem 0 0;">{{ $respuesta->formulario->descripcion }}</p>
            @endif
        </div>

        <form method="POST" action="{{ route('respuestas.update', $respuesta) }}" id="dynamic-edit-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="formulario_id" value="{{ $respuesta->formulario_id }}">
            <input type="hidden" name="datos_json" id="datos_json" value="{{ $respuesta->datos_json }}">

            @php
                $schema  = json_decode($respuesta->formulario->schema_json ?? '[]', true);
                $datos   = json_decode($respuesta->datos_json ?? '{}', true);
            @endphp

            @foreach($schema as $field)
                @if($field['type'] === 'divider')
                    <hr style="border-color:var(--surface-border);margin:1.5rem 0;">
                    <p style="text-align:center;color:var(--text-muted);font-size:0.85rem;">{{ $field['label'] }}</p>
                @else
                    @php $val = $datos[$field['id']] ?? ''; @endphp
                    <div class="form-group">
                        <label>
                            {{ $field['label'] }}
                            @if(!empty($field['required'])) <span style="color:#ef4444">*</span> @endif
                        </label>

                        @if($field['type'] === 'text')
                            <input type="text" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                value="{{ $val }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>

                        @elseif($field['type'] === 'textarea')
                            <textarea id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                rows="3" placeholder="{{ $field['placeholder'] ?? '' }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>{{ $val }}</textarea>

                        @elseif($field['type'] === 'number')
                            <input type="number" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                value="{{ $val }}"
                                @isset($field['min']) min="{{ $field['min'] }}" @endisset
                                @isset($field['max']) max="{{ $field['max'] }}" @endisset
                                {{ !empty($field['required']) ? 'required' : '' }}>

                        @elseif($field['type'] === 'date')
                            <input type="date" id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                value="{{ $val }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>

                        @elseif($field['type'] === 'select')
                            <select id="field_{{ $field['id'] }}" class="form-input field-input"
                                data-id="{{ $field['id'] }}"
                                {{ !empty($field['required']) ? 'required' : '' }}>
                                <option value="">Seleccionar...</option>
                                @foreach($field['options'] ?? [] as $opt)
                                    <option value="{{ $opt }}" {{ $val === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>

                        @elseif($field['type'] === 'radio')
                            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem;">
                                @foreach($field['options'] ?? [] as $opt)
                                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                        <input type="radio" name="field_{{ $field['id'] }}" value="{{ $opt }}"
                                            onchange="updateRadio('{{ $field['id'] }}', this.value)"
                                            {{ $val === $opt ? 'checked' : '' }}
                                            {{ !empty($field['required']) ? 'required' : '' }}>
                                        {{ $opt }}
                                    </label>
                                @endforeach
                            </div>

                        @elseif($field['type'] === 'checkbox')
                            @php $checked = is_array($val) ? $val : []; @endphp
                            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem;">
                                @foreach($field['options'] ?? [] as $opt)
                                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                                        <input type="checkbox" value="{{ $opt }}"
                                            data-check-group="{{ $field['id'] }}"
                                            onchange="updateCheckbox('{{ $field['id'] }}')"
                                            {{ in_array($opt, $checked) ? 'checked' : '' }}>
                                        {{ $opt }}
                                    </label>
                                @endforeach
                            </div>

                        @elseif($field['type'] === 'signature')
                            <div style="border:1px solid var(--surface-border);border-radius:10px;overflow:hidden;background:white;">
                                <canvas id="sig_{{ $field['id'] }}" width="600" height="150"
                                    style="width:100%;height:150px;display:block;cursor:crosshair;touch-action:none;"></canvas>
                            </div>
                            <input type="hidden" id="field_{{ $field['id'] }}" data-id="{{ $field['id'] }}" class="field-input"
                                   value="{{ $val }}">
                            <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                                <button type="button" class="btn-ghost" style="font-size:0.8rem;"
                                    onclick="clearSignature('{{ $field['id'] }}')">
                                    <i class="bi bi-eraser"></i> Limpiar firma
                                </button>
                            </div>

                        @elseif($field['type'] === 'file')
                            @php
                                $existingFiles = [];
                                if (is_array($val) && isset($val['path'])) {
                                    $existingFiles = [$val]; // single file legacy
                                } elseif (is_array($val) && isset($val[0]['path'])) {
                                    $existingFiles = $val; // multi-file
                                }
                            @endphp
                            @if(count($existingFiles))
                                <div style="margin-bottom:.5rem;">
                                    <small style="color:var(--text-muted);font-size:.72rem">Archivos actuales:</small>
                                    <div style="display:flex;flex-direction:column;gap:.25rem;margin-top:.25rem;">
                                        @foreach($existingFiles as $archivo)
                                            <a href="{{ asset('storage/' . $archivo['path']) }}" target="_blank"
                                               style="display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;color:var(--accent-color);text-decoration:none;">
                                                <i class="bi bi-paperclip"></i> {{ $archivo['name'] ?? 'Archivo' }}
                                                @if(isset($archivo['size']))
                                                    <small style="color:var(--text-muted);">({{ number_format($archivo['size']/1024, 0) }} KB)</small>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="file_{{ $field['id'] }}{{ !empty($field['multiple']) ? '[]' : '' }}"
                                id="field_{{ $field['id'] }}" class="form-input" data-id="{{ $field['id'] }}" data-is-file="1"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                {{ !empty($field['multiple']) ? 'multiple' : '' }}>
                            <small style="color:var(--text-muted);font-size:.72rem">
                                {{ count($existingFiles) ? 'Seleccionar para reemplazar' : 'PDF, Word, Excel o imágenes (máx. 10MB)' }}
                            </small>
                        @endif
                    </div>
                @endif
            @endforeach

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.75rem;">
                <a href="{{ route('respuestas.show', $respuesta) }}" class="btn-ghost">Cancelar</a>
                <button type="submit" name="estado" value="Borrador" class="btn-secondary">
                    <i class="bi bi-save"></i> Guardar Borrador
                </button>
                <button type="submit" name="estado" value="Pendiente" class="btn-premium">
                    <i class="bi bi-send-fill"></i> Enviar Solicitud
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
// Pre-cargar datos existentes
const datos = @json(json_decode($respuesta->datos_json ?? '{}', true));

document.getElementById('dynamic-edit-form')?.addEventListener('submit', function() {
    document.querySelectorAll('.field-input').forEach(el => {
        if (el.type !== 'hidden' && !el.dataset.isFile) datos[el.dataset.id] = el.value;
    });
    document.getElementById('datos_json').value = JSON.stringify(datos);
});

function updateRadio(id, val) { datos[id] = val; }

function updateCheckbox(id) {
    datos[id] = [...document.querySelectorAll(`[data-check-group="${id}"]:checked`)].map(c => c.value);
}

// ── Firmas: restaurar y dibujar si había valor previo ──
document.querySelectorAll('canvas[id^="sig_"]').forEach(canvas => {
    const fieldId = canvas.id.replace('sig_', '');
    const hidden  = document.getElementById('field_' + fieldId);
    const ctx     = canvas.getContext('2d');
    let drawing   = false;
    ctx.strokeStyle = '#1e1b4b';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';

    // Restaurar firma guardada
    if (hidden.value && hidden.value.startsWith('data:image')) {
        const img = new Image();
        img.onload = () => ctx.drawImage(img, 0, 0);
        img.src = hidden.value;
    }

    const getPos = e => {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - rect.left) * scaleX, y: (src.clientY - rect.top) * scaleY };
    };

    canvas.addEventListener('mousedown', e => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup', () => { drawing = false; hidden.value = canvas.toDataURL(); datos[fieldId] = hidden.value; });
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); }, {passive: false});
    canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }, {passive: false});
    canvas.addEventListener('touchend', () => { drawing = false; hidden.value = canvas.toDataURL(); datos[fieldId] = hidden.value; });
});

window.clearSignature = function(id) {
    const canvas = document.getElementById('sig_' + id);
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('field_' + id).value = '';
    delete datos[id];
};
</script>
@endpush
