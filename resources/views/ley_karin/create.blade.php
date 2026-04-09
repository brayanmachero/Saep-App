@extends('layouts.app')
@section('title', 'Nueva Denuncia Ley Karin')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-shield-exclamation" style="color:#dc2626"></i> Nueva Denuncia</h2>
            <p class="page-subheading">Ley 21.643 · Acoso laboral, sexual y violencia en el trabajo</p>
        </div>
        <a href="{{ route('ley-karin.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    {{-- Aviso de confidencialidad --}}
    <div class="glass-card" style="border-left:4px solid #dc2626;margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <div style="display:flex;gap:.75rem;align-items:flex-start;">
            <i class="bi bi-shield-lock-fill" style="font-size:1.25rem;color:#dc2626;flex-shrink:0;margin-top:.1rem;"></i>
            <div>
                <strong>Confidencialidad</strong>
                <p style="margin:.25rem 0 0;font-size:.88rem;color:var(--text-muted);line-height:1.5;">
                    Este registro es estrictamente confidencial. Solo el personal autorizado tendrá acceso al expediente.
                    El folio será generado automáticamente y servirá como número de seguimiento.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('ley-karin.store') }}">
        @csrf

        {{-- Datos de la denuncia --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-file-earmark-text"></i> Datos de la Denuncia
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Fecha de Denuncia *</label>
                    <input type="date" name="fecha_denuncia" value="{{ old('fecha_denuncia', date('Y-m-d')) }}"
                           class="form-input @error('fecha_denuncia') is-invalid @enderror" required>
                    @error('fecha_denuncia') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Tipo de Denuncia *</label>
                    <select name="tipo" class="form-input @error('tipo') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(\App\Models\LeyKarin::tiposMap() as $val => $lbl)
                            <option value="{{ $val }}" {{ old('tipo') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Centro de Costo *</label>
                    <select name="centro_costo_id" class="form-input @error('centro_costo_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                        @endforeach
                    </select>
                    @error('centro_costo_id') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Canal de Denuncia</label>
                    <select name="canal" class="form-input">
                        <option value="">Seleccionar...</option>
                        @foreach(\App\Models\LeyKarin::canalesMap() as $val => $lbl)
                            <option value="{{ $val }}" {{ old('canal') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Denunciante --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-person-fill"></i> Denunciante
            </h3>

            <div class="form-group" style="margin-bottom:1rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" name="anonima" value="1" id="esAnonima" {{ old('anonima') ? 'checked' : '' }}
                           style="width:18px;height:18px;accent-color:var(--primary-color);">
                    <span>Denuncia anónima</span>
                </label>
            </div>

            <div id="datosDenunciante" style="transition:opacity .2s;">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Nombre del Denunciante</label>
                        <input type="text" name="denunciante_nombre" value="{{ old('denunciante_nombre') }}"
                               class="form-input" placeholder="Nombre completo">
                    </div>
                    <div class="form-group">
                        <label>RUT</label>
                        <input type="text" name="denunciante_rut" data-rut value="{{ old('denunciante_rut') }}"
                               class="form-input">
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label>O seleccionar usuario interno</label>
                        <select name="denunciante_id" class="form-input">
                            <option value="">— Externo / No registrado —</option>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}" {{ old('denunciante_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} {{ $u->apellido_paterno ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Denunciado --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-person-x-fill"></i> Denunciado
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Nombre del Denunciado</label>
                    <input type="text" name="denunciado_nombre" value="{{ old('denunciado_nombre') }}"
                           class="form-input" placeholder="Nombre completo">
                </div>
                <div class="form-group">
                    <label>Cargo del Denunciado</label>
                    <input type="text" name="denunciado_cargo" value="{{ old('denunciado_cargo') }}"
                           class="form-input" placeholder="Ej: Supervisor, Jefatura">
                </div>
            </div>
        </div>

        {{-- Descripción --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-chat-left-text-fill"></i> Descripción de los Hechos
            </h3>
            <div class="form-group" style="margin-bottom:0;">
                <textarea name="descripcion_hechos" class="form-input @error('descripcion_hechos') is-invalid @enderror"
                          rows="6" required placeholder="Describir en detalle los hechos denunciados, fechas, lugares, testigos...">{{ old('descripcion_hechos') }}</textarea>
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
                    <select name="investigador_id" class="form-input">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('investigador_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Plazo Legal de Investigación</label>
                    <input type="date" name="fecha_plazo_investigacion" value="{{ old('fecha_plazo_investigacion') }}"
                           class="form-input">
                    <small style="color:var(--text-muted);display:block;margin-top:.25rem;">La ley otorga 30 días hábiles desde la recepción.</small>
                </div>
            </div>
        </div>

        {{-- Opciones --}}
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="hidden" name="confidencial" value="0">
                    <input type="checkbox" name="confidencial" value="1" {{ old('confidencial', true) ? 'checked' : '' }}
                           style="width:18px;height:18px;accent-color:#dc2626;">
                    <span><i class="bi bi-lock-fill" style="color:#dc2626;"></i> Marcar como confidencial (acceso restringido)</span>
                </label>
            </div>
        </div>

        {{-- Acciones --}}
        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('ley-karin.index') }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium" onclick="this.disabled=true;this.form.submit();">
                <i class="bi bi-shield-check"></i> Registrar Denuncia
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const anonima = document.getElementById('esAnonima');
    const datos = document.getElementById('datosDenunciante');
    function toggleAnonima() {
        datos.style.opacity = anonima.checked ? '.35' : '1';
        datos.style.pointerEvents = anonima.checked ? 'none' : 'auto';
        datos.querySelectorAll('input,select').forEach(function(el) { el.disabled = anonima.checked; });
    }
    anonima.addEventListener('change', toggleAnonima);
    toggleAnonima();
});
</script>
@endpush
@endsection
