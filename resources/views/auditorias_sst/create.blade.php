@extends('layouts.app')
@section('title','Nueva Auditoría SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div><h2 class="page-heading">Nueva Auditoría SST</h2></div>
        <a href="{{ route('auditorias-sst.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('auditorias-sst.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha <span class="required">*</span></label>
                    <input type="date" name="fecha_auditoria" value="{{ old('fecha_auditoria', date('Y-m-d')) }}"
                           class="form-control @error('fecha_auditoria') is-invalid @enderror" required>
                    @error('fecha_auditoria')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Tipo de Auditoría <span class="required">*</span></label>
                    <select name="tipo_auditoria" class="form-control @error('tipo_auditoria') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['interna','externa','certificación','seguimiento','previa'] as $t)
                            <option value="{{ $t }}" {{ old('tipo_auditoria') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    @error('tipo_auditoria')<span class="form-error">{{ $message }}</span>@enderror
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
                    <label>Auditor / Responsable</label>
                    <select name="auditor_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('auditor_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Norma / Alcance</label>
                    <input type="text" name="norma_alcance" value="{{ old('norma_alcance') }}"
                           class="form-control" placeholder="Ej: ISO 45001, Decreto 40">
                </div>
                <div class="form-group">
                    <label>Puntaje Obtenido (%)</label>
                    <input type="number" name="puntaje" value="{{ old('puntaje') }}"
                           class="form-control" min="0" max="100" step="0.1">
                </div>
            </div>
            <div class="form-group">
                <label>Hallazgos / Observaciones</label>
                <textarea name="hallazgos" class="form-control" rows="4"
                          placeholder="Describir no conformidades, observaciones y puntos fuertes...">{{ old('hallazgos') }}</textarea>
            </div>
            <div class="form-group">
                <label>Plan de Acción Correctiva</label>
                <textarea name="plan_accion" class="form-control" rows="3"
                          placeholder="Acciones previstas para corregir hallazgos...">{{ old('plan_accion') }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['programada','en_proceso','cerrada','cancelada'] as $e)
                            <option value="{{ $e }}" {{ old('estado','programada') === $e ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$e)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Cierre</label>
                    <input type="date" name="fecha_cierre" value="{{ old('fecha_cierre') }}" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('auditorias-sst.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Guardar Auditoría</button>
            </div>
        </form>
    </div>
</div>
@endsection
