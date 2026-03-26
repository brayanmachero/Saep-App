@extends('layouts.app')

@section('title', 'Nueva Charla SST')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-megaphone-fill" style="color:#0056b3"></i> Nueva Charla SST</h2>
            <p class="page-subheading">Programa una nueva charla, capacitación o inducción</p>
        </div>
        <a href="{{ route('charlas.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form method="POST" action="{{ route('charlas.store') }}">
        @csrf

        <!-- Datos básicos -->
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-info-circle"></i> Información General
            </h3>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" class="form-input @error('titulo') is-invalid @enderror"
                    value="{{ old('titulo') }}" placeholder="Ej: Charla uso correcto de EPP" required>
                @error('titulo') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" class="form-input @error('tipo') is-invalid @enderror" required>
                        @foreach(['CHARLA_5MIN'=>'Charla 5 Minutos','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('tipo') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Centro de Costo</label>
                    <select name="centro_costo_id" class="form-input">
                        <option value="">Sin asignar</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Lugar</label>
                    <input type="text" name="lugar" class="form-input"
                        value="{{ old('lugar') }}" placeholder="Ej: Sala de reuniones, Faena Norte">
                </div>
                <div class="form-group">
                    <label>Duración (minutos) *</label>
                    <input type="number" name="duracion_minutos" class="form-input" min="1"
                        value="{{ old('duracion_minutos', 15) }}" required>
                </div>
                <div class="form-group">
                    <label>Fecha y Hora *</label>
                    <input type="datetime-local" name="fecha_programada"
                        class="form-input @error('fecha_programada') is-invalid @enderror"
                        value="{{ old('fecha_programada') }}" required>
                    @error('fecha_programada') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Supervisor a cargo</label>
                    <select name="supervisor_id" class="form-input">
                        <option value="">Sin asignar</option>
                        @foreach($supervisores as $sup)
                            <option value="{{ $sup->id }}" {{ old('supervisor_id') == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Contenido -->
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
                <i class="bi bi-file-text"></i> Contenido / Temario
            </h3>
            <div class="form-group">
                <label>Descripción del contenido <small style="color:var(--text-muted)">(los asistentes deberán leerlo antes de firmar)</small></label>
                <textarea name="contenido" class="form-input" rows="7"
                    placeholder="Describe los temas, objetivos, procedimientos y medidas de seguridad...">{{ old('contenido') }}</textarea>
            </div>
        </div>

        <!-- Relatores -->
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;font-weight:700;margin:0;">
                    <i class="bi bi-person-badge-fill" style="color:#0056b3;"></i> Relatores / Instructores
                </h3>
                <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:0.8rem;" id="btn-add-relator">
                    <i class="bi bi-plus-circle"></i> Agregar Relator
                </button>
            </div>
            <div id="relatores-container"></div>
            <p style="font-size:0.78rem;color:var(--text-muted);margin-top:0.5rem;">
                <i class="bi bi-info-circle"></i> El relator firmará digitalmente desde la vista de detalle de la charla.
            </p>
        </div>

        <!-- Asistentes -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
                <i class="bi bi-people-fill"></i> Asistentes
            </h3>
            <input type="text" id="buscar-asistente" class="form-input" placeholder="Buscar trabajador..." style="margin-bottom:0.75rem;">
            <div style="max-height:280px;overflow-y:auto;border:1px solid var(--surface-border);border-radius:10px;padding:0.75rem;display:flex;flex-direction:column;gap:0.35rem;" id="lista-trabajadores">
                @foreach($trabajadores as $t)
                <label id="worker-{{ $t->id }}" style="display:flex;align-items:center;gap:0.75rem;padding:0.45rem 0.5rem;border-radius:8px;cursor:pointer;transition:background 0.12s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.05)'"
                    onmouseout="this.style.background=''">
                    <input type="checkbox" name="asistentes[]" value="{{ $t->id }}"
                        {{ in_array($t->id, old('asistentes', [])) ? 'checked' : '' }}
                        style="width:15px;height:15px;accent-color:#0056b3;">
                    <div class="avatar" style="width:30px;height:30px;flex-shrink:0;font-size:0.75rem;">{{ strtoupper(substr($t->name, 0, 1)) }}</div>
                    <div>
                        <span style="font-size:0.875rem;font-weight:500;">{{ $t->name }}</span>
                        <span style="display:block;font-size:0.73rem;color:var(--text-muted);">{{ $t->rol->nombre ?? '' }}</span>
                    </div>
                </label>
                @endforeach
            </div>
            <div style="margin-top:0.5rem;font-size:0.8rem;color:var(--text-muted);" id="count-asistentes">0 seleccionados</div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('charlas.index') }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium"><i class="bi bi-save-fill"></i> Crear Charla</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Asistentes filter & count
    const checkboxes = document.querySelectorAll('input[name="asistentes[]"]');
    const countEl = document.getElementById('count-asistentes');

    function updateCount() {
        const n = [...checkboxes].filter(c => c.checked).length;
        countEl.textContent = n + ' seleccionado' + (n !== 1 ? 's' : '');
    }
    checkboxes.forEach(c => c.addEventListener('change', updateCount));
    updateCount();

    document.getElementById('buscar-asistente').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#lista-trabajadores label').forEach(el => {
            el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Relatores
    let relatorIdx = 0;
    const usuarios = @json($trabajadores->map(fn($u) => ['id' => $u->id, 'name' => $u->name]));

    function buildUserOpts(selectedId) {
        return usuarios.map(u =>
            `<option value="${u.id}" ${u.id == selectedId ? 'selected' : ''}>${u.name}</option>`
        ).join('');
    }

    window.addRelatorRow = function (usuarioId, rol) {
        rol = rol || 'RELATOR';
        const i = relatorIdx++;
        const container = document.getElementById('relatores-container');
        const div = document.createElement('div');
        div.id = 'relator-row-' + i;
        div.style.cssText = 'display:flex;gap:0.75rem;align-items:center;margin-bottom:0.5rem;';
        div.innerHTML = `
            <select name="relatores[${i}][usuario_id]" class="form-input" style="flex:1;" required>
                <option value="">Seleccionar persona...</option>${buildUserOpts(usuarioId)}
            </select>
            <select name="relatores[${i}][rol]" class="form-input" style="width:200px;">
                <option value="RELATOR" ${rol==='RELATOR'?'selected':''}>Relator / Instructor</option>
                <option value="SUPERVISOR_CPHS" ${rol==='SUPERVISOR_CPHS'?'selected':''}>Supervisor / CPHS</option>
                <option value="INSTRUCTOR" ${rol==='INSTRUCTOR'?'selected':''}>Instructor Externo</option>
            </select>
            <button type="button" onclick="document.getElementById('relator-row-${i}').remove()"
                style="padding:8px 12px;border:none;border-radius:8px;background:rgba(220,38,38,0.15);color:#dc2626;cursor:pointer;flex-shrink:0;">
                <i class="bi bi-trash3-fill"></i>
            </button>
        `;
        container.appendChild(div);
    };

    document.getElementById('btn-add-relator').addEventListener('click', function () {
        addRelatorRow();
    });

    @if(old('relatores'))
        @foreach(old('relatores', []) as $rv)
            addRelatorRow('{{ $rv["usuario_id"] ?? "" }}', '{{ $rv["rol"] ?? "RELATOR" }}');
        @endforeach
    @endif
}());
</script>
@endpush

