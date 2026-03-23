@extends('layouts.app')
@section('title','Nueva Visita SST')
@section('content')
<div class="page-container" style="max-width:860px">
    <div class="page-header">
        <div><h1>Nueva Visita / Inspección SST</h1></div>
        <a href="{{ route('visitas-sst.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('visitas-sst.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha de Visita <span class="required">*</span></label>
                    <input type="date" name="fecha_visita" value="{{ old('fecha_visita', date('Y-m-d')) }}"
                           class="form-control @error('fecha_visita') is-invalid @enderror" required>
                    @error('fecha_visita')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Tipo de Visita <span class="required">*</span></label>
                    <select name="tipo_visita" class="form-control @error('tipo_visita') is-invalid @enderror" required>
                        <option value="">Seleccionar...</option>
                        @foreach(['inspección_general','observación_preventiva','seguimiento','auditoría_interna','visita_mutualidad','otra'] as $t)
                            <option value="{{ $t }}" {{ old('tipo_visita') === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$t)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo_visita')<span class="form-error">{{ $message }}</span>@enderror
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
                    <label>Inspector</label>
                    <select name="inspector_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('inspector_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Área / Zona Inspeccionada</label>
                <input type="text" name="area_inspeccionada" value="{{ old('area_inspeccionada') }}"
                       class="form-control" placeholder="Ej: Bodega principal, Línea de producción">
            </div>
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="4"
                          placeholder="Describir hallazgos, observaciones y condiciones encontradas...">{{ old('observaciones') }}</textarea>
            </div>
            <div class="form-group">
                <label>Medidas Correctivas</label>
                <textarea name="medidas_correctivas" class="form-control" rows="3"
                          placeholder="Acciones remediales propuestas...">{{ old('medidas_correctivas') }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['pendiente','en_proceso','cerrado'] as $e)
                            <option value="{{ $e }}" {{ old('estado','pendiente') === $e ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_',' ',$e)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Cierre Esperado</label>
                    <input type="date" name="fecha_cierre" value="{{ old('fecha_cierre') }}" class="form-control">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('visitas-sst.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Guardar Visita</button>
            </div>
        </form>
    </div>
</div>
@endsection
