@extends('layouts.app')
@section('title','Nuevo Programa SST')
@section('content')
<div class="page-container" style="max-width:760px">
    <div class="page-header">
        <div><h1>Nuevo Programa SST</h1></div>
        <a href="{{ route('carta-gantt.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('carta-gantt.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" name="codigo" value="{{ old('codigo') }}"
                           class="form-control" placeholder="Ej: SST-2025-001">
                </div>
                <div class="form-group">
                    <label>Año <span class="required">*</span></label>
                    <input type="number" name="anio" value="{{ old('anio', date('Y')) }}"
                           class="form-control @error('anio') is-invalid @enderror"
                           min="2020" max="2099" required>
                    @error('anio')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label>Nombre del Programa <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-control @error('nombre') is-invalid @enderror"
                       placeholder="Ej: Programa Anual de SST 2025" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"
                          placeholder="Descripción del programa...">{{ old('descripcion') }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Centro de Costo</label>
                    <select name="centro_costo_id" class="form-control">
                        <option value="">— Todos los centros —</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                    <option value="ACTIVO" {{ old('estado','ACTIVO') === 'ACTIVO' ? 'selected' : '' }}>Activo</option>
                        <option value="BORRADOR" {{ old('estado') === 'BORRADOR' ? 'selected' : '' }}>Borrador</option>
                        <option value="CERRADO" {{ old('estado') === 'CERRADO' ? 'selected' : '' }}>Cerrado</option>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Responsable</label>
                    <select name="responsable_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('responsable_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('carta-gantt.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Crear Programa</button>
            </div>
        </form>
    </div>
</div>
@endsection
