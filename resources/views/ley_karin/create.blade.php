@extends('layouts.app')
@section('title','Nueva Denuncia Ley Karin')
@section('content')
<div class="page-container" style="max-width:900px">
    <div class="page-header">
        <div>
            <h1>Nueva Denuncia — Ley Karin</h1>
            <p style="color:var(--text-muted);margin:0">Ley 21.643 · Acoso laboral, sexual y violencia en el trabajo</p>
        </div>
        <a href="{{ route('ley-karin.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')

    <div class="glass-card" style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.2);margin-bottom:1.5rem">
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <i class="bi bi-shield-lock-fill" style="font-size:1.5rem;color:#dc2626;flex-shrink:0;margin-top:.1rem"></i>
            <div>
                <strong>Confidencialidad</strong>
                <p style="margin:.25rem 0 0;font-size:.9rem;color:var(--text-muted)">
                    Este registro es estrictamente confidencial. Solo el personal autorizado tendrá acceso al expediente.
                    El folio será generado automáticamente y servirá como número de seguimiento para el denunciante.
                </p>
            </div>
        </div>
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('ley-karin.store') }}">
            @csrf
            <h4 style="margin-top:0;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Datos de la Denuncia</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha de Denuncia <span class="required">*</span></label>
                    <input type="date" name="fecha_denuncia" value="{{ old('fecha_denuncia', date('Y-m-d')) }}"
                           class="form-control @error('fecha_denuncia') is-invalid @enderror" required>
                    @error('fecha_denuncia')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Tipo de Denuncia <span class="required">*</span></label>
                    <select name="tipo_denuncia" class="form-control @error('tipo_denuncia') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['acoso_laboral','acoso_sexual','violencia_trabajo','discriminación','otra'] as $t)
                            <option value="{{ $t }}" {{ old('tipo_denuncia') === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo_denuncia')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control @error('centro_costo_id') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('centro_costo_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Canal de Denuncia</label>
                    <select name="canal" class="form-control">
                        @foreach(['presencial','escrito','correo_electronico','formulario_web','telefono','anonimo'] as $c)
                            <option value="{{ $c }}" {{ old('canal') === $c ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$c)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h4 style="margin-top:1.5rem;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Denunciante</h4>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="anonima" value="1" id="esAnonima" {{ old('anonima') ? 'checked' : '' }}>
                    Denuncia anónima
                </label>
            </div>
            <div id="datosDenunciante">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Denunciante</label>
                        <input type="text" name="nombre_denunciante" value="{{ old('nombre_denunciante') }}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>O seleccionar usuario interno</label>
                        <select name="denunciante_id" class="form-control">
                            <option value="">— Externo / No registrado —</option>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}" {{ old('denunciante_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} {{ $u->apellido_paterno }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <h4 style="margin-top:1.5rem;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Denunciado</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre Denunciado</label>
                    <input type="text" name="nombre_denunciado" value="{{ old('nombre_denunciado') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Cargo del Denunciado</label>
                    <input type="text" name="cargo_denunciado" value="{{ old('cargo_denunciado') }}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Descripción de los Hechos <span class="required">*</span></label>
                <textarea name="descripcion_hechos" class="form-control @error('descripcion_hechos') is-invalid @enderror"
                          rows="5" placeholder="Describir en detalle los hechos denunciados, fechas, lugares, testigos...">{{ old('descripcion_hechos') }}</textarea>
                @error('descripcion_hechos')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Investigador Asignado</label>
                    <select name="investigador_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('investigador_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Plazo Legal de Investigación</label>
                    <input type="date" name="fecha_plazo_investigacion" value="{{ old('fecha_plazo_investigacion') }}" class="form-control">
                    <small style="color:var(--text-muted)">La ley otorga 30 días hábiles</small>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="confidencial" value="0">
                    <input type="checkbox" name="confidencial" value="1" {{ old('confidencial', true) ? 'checked' : '' }}>
                    Marcar como confidencial (acceso restringido)
                </label>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('ley-karin.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-shield-check"></i> Registrar Denuncia
                </button>
            </div>
        </form>
    </div>
</div>
<script>
const anonima = document.getElementById('esAnonima');
const datos = document.getElementById('datosDenunciante');
anonima.addEventListener('change', () => {
    datos.style.opacity = anonima.checked ? '.4' : '1';
    datos.querySelectorAll('input,select').forEach(el => el.disabled = anonima.checked);
});
if (anonima.checked) {
    datos.style.opacity = '.4';
    datos.querySelectorAll('input,select').forEach(el => el.disabled = true);
}
</script>
@endsection
