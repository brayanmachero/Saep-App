@extends('layouts.app')
@section('title','Editar Auditoría SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Auditoría SST</h2>
            <p style="color:var(--text-muted);margin:0">N° {{ $auditoriaSst->numero_auditoria ?? $auditoriaSst->id }}</p>
        </div>
        <a href="{{ route('auditorias-sst.show', $auditoriaSst) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('auditorias-sst.update', $auditoriaSst) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha <span class="required">*</span></label>
                    <input type="date" name="fecha_auditoria"
                           value="{{ old('fecha_auditoria', \Carbon\Carbon::parse($auditoriaSst->fecha_auditoria)->format('Y-m-d')) }}"
                           class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo_auditoria" class="form-control" required>
                        @foreach(['interna','externa','certificación','seguimiento','previa'] as $t)
                            <option value="{{ $t }}" {{ old('tipo_auditoria', $auditoriaSst->tipo_auditoria) === $t ? 'selected' : '' }}>
                                {{ ucfirst($t) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control" required>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $auditoriaSst->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Auditor</label>
                    <select name="auditor_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('auditor_id', $auditoriaSst->auditor_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Norma / Alcance</label>
                    <input type="text" name="norma_alcance" value="{{ old('norma_alcance', $auditoriaSst->norma_alcance) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Puntaje (%)</label>
                    <input type="number" name="puntaje" value="{{ old('puntaje', $auditoriaSst->puntaje) }}"
                           class="form-control" min="0" max="100" step="0.1">
                </div>
            </div>
            <div class="form-group">
                <label>Hallazgos</label>
                <textarea name="hallazgos" class="form-control" rows="4">{{ old('hallazgos', $auditoriaSst->hallazgos) }}</textarea>
            </div>
            <div class="form-group">
                <label>Plan de Acción Correctiva</label>
                <textarea name="plan_accion" class="form-control" rows="3">{{ old('plan_accion', $auditoriaSst->plan_accion) }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['programada','en_proceso','cerrada','cancelada'] as $e)
                            <option value="{{ $e }}" {{ old('estado', $auditoriaSst->estado) === $e ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$e)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Cierre</label>
                    <input type="date" name="fecha_cierre"
                           value="{{ old('fecha_cierre', $auditoriaSst->fecha_cierre ? \Carbon\Carbon::parse($auditoriaSst->fecha_cierre)->format('Y-m-d') : '') }}"
                           class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('auditorias-sst.show', $auditoriaSst) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
