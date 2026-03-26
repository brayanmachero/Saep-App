@extends('layouts.app')
@section('title', 'Editar Denuncia Ley Karin')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-pencil-fill" style="color:var(--primary-color)"></i> Editar Expediente</h2>
            <p class="page-subheading">Folio: <code style="background:var(--surface-bg);padding:.15rem .5rem;border-radius:6px;font-weight:600;font-size:.85rem;">{{ $leyKarin->folio }}</code></p>
        </div>
        <a href="{{ route('ley-karin.show', $leyKarin) }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <form method="POST" action="{{ route('ley-karin.update', $leyKarin) }}">
        @csrf @method('PUT')

        {{-- Datos de la Denuncia --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-folder2-open"></i> Datos de la Denuncia
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Fecha de Denuncia</label>
                    <input type="date" name="fecha_denuncia"
                           value="{{ old('fecha_denuncia', $leyKarin->fecha_denuncia?->format('Y-m-d')) }}"
                           class="form-input @error('fecha_denuncia') is-invalid @enderror" required>
                    @error('fecha_denuncia') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Tipo de Denuncia</label>
                    <select name="tipo" class="form-input @error('tipo') is-invalid @enderror" required>
                        <option value="">— Seleccionar —</option>
                        @foreach(\App\Models\LeyKarin::tiposMap() as $key => $label)
                            <option value="{{ $key }}" {{ old('tipo', $leyKarin->tipo) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Centro de Costo</label>
                    <select name="centro_costo_id" class="form-input @error('centro_costo_id') is-invalid @enderror" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $leyKarin->centro_costo_id) == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                        @endforeach
                    </select>
                    @error('centro_costo_id') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Canal de Recepción</label>
                    <select name="canal" class="form-input @error('canal') is-invalid @enderror">
                        <option value="">— Sin especificar —</option>
                        @foreach(\App\Models\LeyKarin::canalesMap() as $key => $label)
                            <option value="{{ $key }}" {{ old('canal', $leyKarin->canal) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('canal') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Estado del Expediente --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-flag-fill"></i> Estado del Expediente
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="selectEstado" class="form-input @error('estado') is-invalid @enderror" required>
                        @foreach(\App\Models\LeyKarin::estadosMap() as $key => $label)
                            <option value="{{ $key }}" {{ old('estado', $leyKarin->estado) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('estado') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Fecha de Resolución</label>
                    <input type="date" name="fecha_resolucion"
                           value="{{ old('fecha_resolucion', $leyKarin->fecha_resolucion?->format('Y-m-d')) }}"
                           class="form-input @error('fecha_resolucion') is-invalid @enderror">
                    @error('fecha_resolucion') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
            {{-- Aviso de notificación al denunciante --}}
            <div id="avisoResolucion" style="display:none;margin-top:1rem;background:#f0fdf4;border-left:3px solid #16a34a;border-radius:8px;padding:.75rem 1rem;">
                <p style="font-size:.85rem;color:#166534;margin:0;">
                    <i class="bi bi-envelope-check-fill"></i>
                    @if(!$leyKarin->anonima && $leyKarin->denunciante_id)
                        Al guardar con estado <strong>Resuelta</strong>, se enviará una notificación por correo al denunciante.
                    @else
                        La denuncia es anónima — no se notificará al denunciante.
                    @endif
                </p>
            </div>
        </div>

        {{-- Denunciado --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-person-x-fill"></i> Datos del Denunciado
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Nombre del Denunciado</label>
                    <input type="text" name="denunciado_nombre"
                           value="{{ old('denunciado_nombre', $leyKarin->denunciado_nombre) }}"
                           class="form-input @error('denunciado_nombre') is-invalid @enderror">
                    @error('denunciado_nombre') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Cargo del Denunciado</label>
                    <input type="text" name="denunciado_cargo"
                           value="{{ old('denunciado_cargo', $leyKarin->denunciado_cargo) }}"
                           class="form-input @error('denunciado_cargo') is-invalid @enderror">
                    @error('denunciado_cargo') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Descripción --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-chat-left-text-fill"></i> Descripción de los Hechos
            </h3>
            <div class="form-group">
                <textarea name="descripcion_hechos" rows="5"
                          class="form-input @error('descripcion_hechos') is-invalid @enderror"
                          required>{{ old('descripcion_hechos', $leyKarin->descripcion_hechos) }}</textarea>
                @error('descripcion_hechos') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Investigación --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-search"></i> Investigación
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Investigador Asignado</label>
                    <select name="investigador_id" class="form-input @error('investigador_id') is-invalid @enderror">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('investigador_id', $leyKarin->investigador_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                    @error('investigador_id') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Plazo Legal de Investigación</label>
                    <input type="date" name="fecha_plazo_investigacion"
                           value="{{ old('fecha_plazo_investigacion', $leyKarin->fecha_plazo_investigacion?->format('Y-m-d')) }}"
                           class="form-input @error('fecha_plazo_investigacion') is-invalid @enderror">
                    <small style="color:var(--text-muted);font-size:.75rem;margin-top:.25rem;display:block;">
                        30 días hábiles según Ley 21.643
                    </small>
                    @error('fecha_plazo_investigacion') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="form-group" style="margin-top:.5rem;">
                <label>Resultado de la Investigación</label>
                <textarea name="resultado_investigacion" rows="4"
                          class="form-input @error('resultado_investigacion') is-invalid @enderror"
                          placeholder="Completar al finalizar la investigación...">{{ old('resultado_investigacion', $leyKarin->resultado_investigacion) }}</textarea>
                @error('resultado_investigacion') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div class="form-group" style="margin-top:.5rem;">
                <label>Medidas Cautelares</label>
                <textarea name="medidas_cautelares" rows="3"
                          class="form-input @error('medidas_cautelares') is-invalid @enderror"
                          placeholder="Medidas adoptadas para proteger al denunciante...">{{ old('medidas_cautelares', $leyKarin->medidas_cautelares) }}</textarea>
                @error('medidas_cautelares') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div class="form-group" style="margin-top:.5rem;">
                <label>Medidas Adoptadas</label>
                <textarea name="medidas_adoptadas" rows="3"
                          class="form-input @error('medidas_adoptadas') is-invalid @enderror"
                          placeholder="Medidas disciplinarias o correctivas aplicadas...">{{ old('medidas_adoptadas', $leyKarin->medidas_adoptadas) }}</textarea>
                @error('medidas_adoptadas') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Opciones --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-gear-fill"></i> Opciones
            </h3>
            <label class="checkbox-label" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                <input type="hidden" name="confidencial" value="0">
                <input type="checkbox" name="confidencial" value="1" {{ old('confidencial', $leyKarin->confidencial) ? 'checked' : '' }}>
                <i class="bi bi-lock-fill" style="color:#dc2626;"></i> Marcar como confidencial
            </label>
        </div>

        {{-- Acciones --}}
        <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
            <a href="{{ route('ley-karin.show', $leyKarin) }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium" id="btnSubmit">
                <i class="bi bi-check-lg"></i> Actualizar Expediente
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('btnSubmit')?.addEventListener('click', function() {
    this.disabled = true;
    this.closest('form').submit();
});

// Mostrar aviso cuando se selecciona "Resuelta"
const selectEstado = document.getElementById('selectEstado');
const avisoResolucion = document.getElementById('avisoResolucion');
if (selectEstado && avisoResolucion) {
    function toggleAviso() {
        avisoResolucion.style.display = selectEstado.value === 'RESUELTA' ? 'block' : 'none';
    }
    selectEstado.addEventListener('change', toggleAviso);
    toggleAviso();
}
</script>
@endpush
