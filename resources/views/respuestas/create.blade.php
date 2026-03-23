@extends('layouts.app')

@section('title', 'Nueva Solicitud')

@section('content')
<div class="page-container" style="max-width:800px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Nueva Solicitud</h2>
            <p class="page-subheading">Completa el formulario dinámico</p>
        </div>
        <a href="{{ route('respuestas.index') }}" class="btn-ghost">
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
                    <i class="bi bi-shield-check"></i> Esta solicitud requiere aprobación
                </span>
            @endif
        </div>

        <form method="POST" action="{{ route('respuestas.store') }}" id="dynamic-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="formulario_id" value="{{ $formulario->id }}">
            <input type="hidden" name="datos_json" id="datos_json" value="{}">

            @php $schema = json_decode($formulario->schema_json ?? '[]', true); @endphp

            @foreach($schema as $field)
                @if($field['type'] === 'divider')
                    <hr style="border-color:var(--surface-border);margin:1.5rem 0;">
                    <p style="text-align:center;color:var(--text-muted);font-size:0.85rem;">{{ $field['label'] }}</p>
                @else
                    <div class="form-group">
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
                        @endif
                    </div>
                @endif
            @endforeach

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.75rem;">
                <a href="{{ route('respuestas.index') }}" class="btn-ghost">Cancelar</a>
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
// ── Recopilar datos del formulario en JSON ──
const datos = {};

function getDatos() {
    document.querySelectorAll('.field-input').forEach(el => {
        if (el.type !== 'hidden') {
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
</script>
@endpush
