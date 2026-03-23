@extends('layouts.app')
@section('title','Editar Accidente SST')
@section('content')
<div class="page-container" style="max-width:900px">
    <div class="page-header">
        <div>
            <h1>Editar Accidente SST</h1>
            <p style="color:var(--text-muted);margin:0">Caso N° {{ $accidenteSst->numero_caso ?? $accidenteSst->id }}</p>
        </div>
        <a href="{{ route('accidentes-sst.show', $accidenteSst) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('accidentes-sst.update', $accidenteSst) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha <span class="required">*</span></label>
                    <input type="date" name="fecha_accidente"
                           value="{{ old('fecha_accidente', \Carbon\Carbon::parse($accidenteSst->fecha_accidente)->format('Y-m-d')) }}"
                           class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Hora</label>
                    <input type="time" name="hora_accidente" value="{{ old('hora_accidente', $accidenteSst->hora_accidente) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo" class="form-control" required>
                        @foreach(['accidente_trabajo','accidente_trayecto','enfermedad_profesional','casi_accidente','incidente'] as $t)
                            <option value="{{ $t }}" {{ old('tipo', $accidenteSst->tipo) === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Gravedad <span class="required">*</span></label>
                    <select name="gravedad" class="form-control" required>
                        @foreach(['leve','moderado','grave','fatal','sin_lesión'] as $g)
                            <option value="{{ $g }}" {{ old('gravedad', $accidenteSst->gravedad) === $g ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$g)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control" required>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $accidenteSst->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Trabajador</label>
                    <select name="trabajador_id" class="form-control">
                        <option value="">— No especificado —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('trabajador_id', $accidenteSst->trabajador_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Descripción <span class="required">*</span></label>
                <textarea name="descripcion" class="form-control" rows="4" required>{{ old('descripcion', $accidenteSst->descripcion) }}</textarea>
            </div>
            <div class="form-group">
                <label>Lesiones / Diagnóstico</label>
                <textarea name="lesiones" class="form-control" rows="2">{{ old('lesiones', $accidenteSst->lesiones) }}</textarea>
            </div>
            <div class="form-group">
                <label>Causas</label>
                <textarea name="causas" class="form-control" rows="3">{{ old('causas', $accidenteSst->causas) }}</textarea>
            </div>
            <div class="form-group">
                <label>Medidas Preventivas</label>
                <textarea name="medidas_preventivas" class="form-control" rows="3">{{ old('medidas_preventivas', $accidenteSst->medidas_preventivas) }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Días Perdidos</label>
                    <input type="number" name="dias_perdidos" value="{{ old('dias_perdidos', $accidenteSst->dias_perdidos) }}" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['notificado','investigacion','cerrado','impugnado'] as $e)
                            <option value="{{ $e }}" {{ old('estado', $accidenteSst->estado) === $e ? 'selected' : '' }}>
                                {{ ucfirst($e) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>DIAT / Folio Mutualidad</label>
                    <input type="text" name="numero_diat" value="{{ old('numero_diat', $accidenteSst->numero_diat) }}" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('accidentes-sst.show', $accidenteSst) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
