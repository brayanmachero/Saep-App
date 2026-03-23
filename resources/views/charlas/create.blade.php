@extends('layouts.app')

@section('title', 'Nueva Charla SST')

@section('content')
<div class="page-container" style="max-width:820px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Nueva Charla SST</h2>
            <p class="page-subheading">Programa una nueva charla de prevención de riesgos</p>
        </div>
        <a href="{{ route('charlas.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form method="POST" action="{{ route('charlas.store') }}">
        @csrf

        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:1.25rem;">
                <i class="bi bi-info-circle"></i> Información General
            </h3>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" class="form-input @error('titulo') is-invalid @enderror"
                    value="{{ old('titulo') }}" placeholder="Ej: Charla de uso correcto de EPP" required>
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
                    <label>Lugar</label>
                    <input type="text" name="lugar" class="form-input"
                        value="{{ old('lugar') }}" placeholder="Ej: Sala de reuniones, Faena Norte">
                </div>
                <div class="form-group">
                    <label>Fecha y Hora *</label>
                    <input type="datetime-local" name="fecha_programada" class="form-input @error('fecha_programada') is-invalid @enderror"
                        value="{{ old('fecha_programada') }}" required>
                    @error('fecha_programada') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Duración (minutos) *</label>
                    <input type="number" name="duracion_minutos" class="form-input" min="1"
                        value="{{ old('duracion_minutos', 15) }}" required>
                </div>
                <div class="form-group">
                    <label>Supervisor / Relator</label>
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
            <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:1rem;">
                <i class="bi bi-file-text"></i> Contenido / Temario
            </h3>
            <div class="form-group">
                <textarea name="contenido" class="form-input" rows="6"
                    placeholder="Describe el contenido, temas y objetivos de la charla...">{{ old('contenido') }}</textarea>
            </div>
        </div>

        <!-- Asistentes -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:1rem;">
                <i class="bi bi-people-fill"></i> Asistentes
            </h3>
            <div style="margin-bottom:0.75rem;">
                <input type="text" id="buscar-asistente" class="form-input" placeholder="Buscar trabajador...">
            </div>
            <div style="max-height:260px;overflow-y:auto;border:1px solid var(--surface-border);border-radius:10px;padding:0.75rem;display:flex;flex-direction:column;gap:0.4rem;" id="lista-trabajadores">
                @foreach($trabajadores as $t)
                <label id="worker-{{ $t->id }}" style="display:flex;align-items:center;gap:0.75rem;padding:0.45rem 0.5rem;border-radius:8px;cursor:pointer;transition:background 0.1s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.05)'"
                    onmouseout="this.style.background=''">
                    <input type="checkbox" name="asistentes[]" value="{{ $t->id }}"
                        {{ in_array($t->id, old('asistentes', [])) ? 'checked' : '' }}
                        style="width:15px;height:15px;accent-color:var(--primary-color);">
                    <div class="avatar" style="width:30px;height:30px;flex-shrink:0;font-size:0.75rem;">
                        {{ strtoupper(substr($t->name, 0, 1)) }}
                    </div>
                    <div>
                        <span style="font-size:0.875rem;font-weight:500;">{{ $t->name }}</span>
                        <span style="display:block;font-size:0.75rem;color:var(--text-muted);">{{ $t->rol->nombre ?? '' }}</span>
                    </div>
                </label>
                @endforeach
            </div>
            <div style="margin-top:0.5rem;font-size:0.8rem;color:var(--text-muted);" id="count-asistentes">
                0 seleccionados
            </div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('charlas.index') }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium">
                <i class="bi bi-save-fill"></i> Crear Charla
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const checkboxes = document.querySelectorAll('input[name="asistentes[]"]');
const countEl = document.getElementById('count-asistentes');

function updateCount() {
    const n = [...checkboxes].filter(c => c.checked).length;
    countEl.textContent = n + ' seleccionado' + (n !== 1 ? 's' : '');
}
checkboxes.forEach(c => c.addEventListener('change', updateCount));
updateCount();

document.getElementById('buscar-asistente').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#lista-trabajadores label').forEach(lbl => {
        lbl.style.display = lbl.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
@endpush
