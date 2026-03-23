@extends('layouts.app')
@section('title','Editar Visita SST')
@section('content')
<div class="page-container" style="max-width:860px">
    <div class="page-header">
        <div>
            <h1>Editar Visita SST</h1>
            <p style="color:var(--text-muted);margin:0">N° {{ $visitaSst->numero_visita ?? $visitaSst->id }}</p>
        </div>
        <a href="{{ route('visitas-sst.show', $visitaSst) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('visitas-sst.update', $visitaSst) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha de Visita <span class="required">*</span></label>
                    <input type="date" name="fecha_visita" value="{{ old('fecha_visita', \Carbon\Carbon::parse($visitaSst->fecha_visita)->format('Y-m-d')) }}"
                           class="form-control @error('fecha_visita') is-invalid @enderror" required>
                    @error('fecha_visita')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Tipo de Visita <span class="required">*</span></label>
                    <select name="tipo_visita" class="form-control" required>
                        @foreach(['inspección_general','observación_preventiva','seguimiento','auditoría_interna','visita_mutualidad','otra'] as $t)
                            <option value="{{ $t }}" {{ old('tipo_visita', $visitaSst->tipo_visita) === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Centro de Costo <span class="required">*</span></label>
                    <select name="centro_costo_id" class="form-control" required>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $visitaSst->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Inspector</label>
                    <select name="inspector_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('inspector_id', $visitaSst->inspector_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Área / Zona Inspeccionada</label>
                <input type="text" name="area_inspeccionada" value="{{ old('area_inspeccionada', $visitaSst->area_inspeccionada) }}" class="form-control">
            </div>
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="4">{{ old('observaciones', $visitaSst->observaciones) }}</textarea>
            </div>
            <div class="form-group">
                <label>Medidas Correctivas</label>
                <textarea name="medidas_correctivas" class="form-control" rows="3">{{ old('medidas_correctivas', $visitaSst->medidas_correctivas) }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['pendiente','en_proceso','cerrado'] as $e)
                            <option value="{{ $e }}" {{ old('estado', $visitaSst->estado) === $e ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$e)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Cierre</label>
                    <input type="date" name="fecha_cierre"
                           value="{{ old('fecha_cierre', $visitaSst->fecha_cierre ? \Carbon\Carbon::parse($visitaSst->fecha_cierre)->format('Y-m-d') : '') }}"
                           class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('visitas-sst.show', $visitaSst) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
