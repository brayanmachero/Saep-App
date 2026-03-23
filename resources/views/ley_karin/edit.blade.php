@extends('layouts.app')
@section('title','Editar Denuncia Ley Karin')
@section('content')
<div class="page-container" style="max-width:900px">
    <div class="page-header">
        <div>
            <h1>Editar Denuncia Ley Karin</h1>
            <p style="color:var(--text-muted);margin:0">Folio: {{ $leyKarin->folio }}</p>
        </div>
        <a href="{{ route('ley-karin.show', $leyKarin) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('ley-karin.update', $leyKarin) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha de Denuncia <span class="required">*</span></label>
                    <input type="date" name="fecha_denuncia"
                           value="{{ old('fecha_denuncia', \Carbon\Carbon::parse($leyKarin->fecha_denuncia)->format('Y-m-d')) }}"
                           class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo_denuncia" class="form-control" required>
                        @foreach(['acoso_laboral','acoso_sexual','violencia_trabajo','discriminación','otra'] as $t)
                            <option value="{{ $t }}" {{ old('tipo_denuncia', $leyKarin->tipo_denuncia) === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control" required>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $leyKarin->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['recibida','en_investigacion','resuelta','archivada','derivada_dt'] as $e)
                            <option value="{{ $e }}" {{ old('estado', $leyKarin->estado) === $e ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$e)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Descripción de los Hechos <span class="required">*</span></label>
                <textarea name="descripcion_hechos" class="form-control" rows="5" required>{{ old('descripcion_hechos', $leyKarin->descripcion_hechos) }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre Denunciado</label>
                    <input type="text" name="nombre_denunciado" value="{{ old('nombre_denunciado', $leyKarin->nombre_denunciado) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Cargo Denunciado</label>
                    <input type="text" name="cargo_denunciado" value="{{ old('cargo_denunciado', $leyKarin->cargo_denunciado) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Investigador</label>
                    <select name="investigador_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('investigador_id', $leyKarin->investigador_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Plazo Legal</label>
                    <input type="date" name="fecha_plazo_investigacion"
                           value="{{ old('fecha_plazo_investigacion', $leyKarin->fecha_plazo_investigacion ? \Carbon\Carbon::parse($leyKarin->fecha_plazo_investigacion)->format('Y-m-d') : '') }}"
                           class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Resultado de la Investigación</label>
                <textarea name="resultado_investigacion" class="form-control" rows="4"
                          placeholder="Completar al finalizar la investigación...">{{ old('resultado_investigacion', $leyKarin->resultado_investigacion) }}</textarea>
            </div>
            <div class="form-group">
                <label>Medidas Adoptadas</label>
                <textarea name="medidas_adoptadas" class="form-control" rows="3">{{ old('medidas_adoptadas', $leyKarin->medidas_adoptadas) }}</textarea>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="confidencial" value="0">
                    <input type="checkbox" name="confidencial" value="1" {{ old('confidencial', $leyKarin->confidencial) ? 'checked' : '' }}>
                    Confidencial
                </label>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('ley-karin.show', $leyKarin) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar Expediente</button>
            </div>
        </form>
    </div>
</div>
@endsection
