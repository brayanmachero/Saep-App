@extends('layouts.app')
@section('title','Nuevo Accidente SST')
@section('content')
<div class="page-container" style="max-width:900px">
    <div class="page-header">
        <div><h1>Registrar Accidente / Enfermedad Profesional</h1></div>
        <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('accidentes-sst.store') }}">
            @csrf
            <h4 style="margin-top:0;color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Datos del Evento</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha del Accidente <span class="required">*</span></label>
                    <input type="date" name="fecha_accidente" value="{{ old('fecha_accidente', date('Y-m-d')) }}"
                           class="form-control @error('fecha_accidente') is-invalid @enderror" required>
                    @error('fecha_accidente')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Hora del Accidente</label>
                    <input type="time" name="hora_accidente" value="{{ old('hora_accidente') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo" class="form-control @error('tipo') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['accidente_trabajo','accidente_trayecto','enfermedad_profesional','casi_accidente','incidente'] as $t)
                            <option value="{{ $t }}" {{ old('tipo') === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Gravedad <span class="required">*</span></label>
                    <select name="gravedad" class="form-control @error('gravedad') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['leve','moderado','grave','fatal','sin_lesión'] as $g)
                            <option value="{{ $g }}" {{ old('gravedad') === $g ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$g)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('gravedad')<span class="form-error">{{ $message }}</span>@enderror
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
                    <label>Trabajador Afectado</label>
                    <select name="trabajador_id" class="form-control">
                        <option value="">— Seleccionar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('trabajador_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Descripción del Accidente <span class="required">*</span></label>
                <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror"
                          rows="4" placeholder="Describir circunstancias, lugar, actividad al momento del accidente...">{{ old('descripcion') }}</textarea>
                @error('descripcion')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Lesiones / Diagnóstico</label>
                <textarea name="lesiones" class="form-control" rows="2"
                          placeholder="Tipo de lesión, parte del cuerpo afectada...">{{ old('lesiones') }}</textarea>
            </div>
            <div class="form-group">
                <label>Causas del Accidente</label>
                <textarea name="causas" class="form-control" rows="3"
                          placeholder="Causa básica e inmediata...">{{ old('causas') }}</textarea>
            </div>
            <div class="form-group">
                <label>Medidas Preventivas</label>
                <textarea name="medidas_preventivas" class="form-control" rows="3"
                          placeholder="Acciones tomadas para prevenir recurrencia...">{{ old('medidas_preventivas') }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Días Perdidos</label>
                    <input type="number" name="dias_perdidos" value="{{ old('dias_perdidos', 0) }}"
                           class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['notificado','investigacion','cerrado','impugnado'] as $e)
                            <option value="{{ $e }}" {{ old('estado','notificado') === $e ? 'selected' : '' }}>
                                {{ ucfirst($e) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>DIAT / Folio Mutualidad</label>
                    <input type="text" name="numero_diat" value="{{ old('numero_diat') }}" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Registrar Accidente</button>
            </div>
        </form>
    </div>
</div>
@endsection
