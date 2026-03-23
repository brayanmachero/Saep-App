@extends('layouts.app')

@section('title', 'Editar Charla SST')

@section('content')
<div class="page-container" style="max-width:820px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Charla SST</h2>
            <p class="page-subheading">{{ $charla->titulo }}</p>
        </div>
        <a href="{{ route('charlas.show', $charla) }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <form method="POST" action="{{ route('charlas.update', $charla) }}">
        @csrf
        @method('PUT')

        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:1.25rem;">
                <i class="bi bi-info-circle"></i> Información General
            </h3>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" class="form-input @error('titulo') is-invalid @enderror"
                    value="{{ old('titulo', $charla->titulo) }}" required>
                @error('titulo') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" class="form-input" required>
                        @foreach(['CHARLA_5MIN'=>'Charla 5 Minutos','CAPACITACION'=>'Capacitación','INDUCCION'=>'Inducción','CHARLA_ESPECIAL'=>'Charla Especial'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('tipo', $charla->tipo) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Lugar</label>
                    <input type="text" name="lugar" class="form-input"
                        value="{{ old('lugar', $charla->lugar) }}">
                </div>
                <div class="form-group">
                    <label>Fecha y Hora *</label>
                    <input type="datetime-local" name="fecha_programada" class="form-input"
                        value="{{ old('fecha_programada', $charla->fecha_programada->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="form-group">
                    <label>Duración (minutos) *</label>
                    <input type="number" name="duracion_minutos" class="form-input" min="1"
                        value="{{ old('duracion_minutos', $charla->duracion_minutos) }}" required>
                </div>
                <div class="form-group">
                    <label>Supervisor / Relator</label>
                    <select name="supervisor_id" class="form-input">
                        <option value="">Sin asignar</option>
                        @foreach($supervisores as $sup)
                            <option value="{{ $sup->id }}" {{ old('supervisor_id', $charla->supervisor_id) == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:1rem;">
                <i class="bi bi-file-text"></i> Contenido / Temario
            </h3>
            <div class="form-group">
                <textarea name="contenido" class="form-input" rows="6">{{ old('contenido', $charla->contenido) }}</textarea>
            </div>
        </div>

        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:1rem;">
                <i class="bi bi-people-fill"></i> Asistentes
            </h3>
            @if($charla->asistentes->where('estado', 'FIRMADO')->count() > 0)
            <div class="alert alert-warning" style="margin-bottom:0.75rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ $charla->asistentes->where('estado', 'FIRMADO')->count() }} asistente(s) ya firmaron.
                Solo se pueden quitar asistentes que aún no han firmado.
            </div>
            @endif
            <div style="margin-bottom:0.75rem;">
                <input type="text" id="buscar-asistente" class="form-input" placeholder="Buscar trabajador...">
            </div>
            <div style="max-height:260px;overflow-y:auto;border:1px solid var(--surface-border);border-radius:10px;padding:0.75rem;display:flex;flex-direction:column;gap:0.4rem;" id="lista-trabajadores">
                @foreach($trabajadores as $t)
                @php $yaFirmo = $charla->asistentes->where('usuario_id', $t->id)->where('estado','FIRMADO')->isNotEmpty(); @endphp
                <label id="worker-{{ $t->id }}" style="display:flex;align-items:center;gap:0.75rem;padding:0.45rem 0.5rem;border-radius:8px;cursor:pointer;"
                    onmouseover="this.style.background='rgba(255,255,255,0.05)'"
                    onmouseout="this.style.background=''">
                    <input type="checkbox" name="asistentes[]" value="{{ $t->id }}"
                        {{ in_array($t->id, old('asistentes', $asistentesIds)) ? 'checked' : '' }}
                        {{ $yaFirmo ? 'disabled' : '' }}
                        style="width:15px;height:15px;accent-color:var(--primary-color);">
                    @if($yaFirmo)
                        {{-- Keep signed value even if disabled --}}
                        <input type="hidden" name="asistentes[]" value="{{ $t->id }}">
                    @endif
                    <div class="avatar" style="width:30px;height:30px;flex-shrink:0;font-size:0.75rem;">
                        {{ strtoupper(substr($t->name, 0, 1)) }}
                    </div>
                    <div style="flex:1;">
                        <span style="font-size:0.875rem;font-weight:500;">{{ $t->name }}</span>
                        <span style="display:block;font-size:0.75rem;color:var(--text-muted);">{{ $t->rol->nombre ?? '' }}</span>
                    </div>
                    @if($yaFirmo)
                        <span class="badge success" style="font-size:0.7rem;"><i class="bi bi-pen-fill"></i> Firmado</span>
                    @endif
                </label>
                @endforeach
            </div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('charlas.show', $charla) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium">
                <i class="bi bi-save-fill"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('buscar-asistente').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#lista-trabajadores label').forEach(lbl => {
        lbl.style.display = lbl.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
@endpush
