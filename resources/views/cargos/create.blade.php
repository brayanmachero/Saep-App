@extends('layouts.app')
@section('title','Nuevo Cargo')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div><h2 class="page-heading">Nuevo Cargo</h2></div>
        <a href="{{ route('cargos.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('cargos.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" name="codigo" value="{{ old('codigo') }}"
                           class="form-control @error('codigo') is-invalid @enderror"
                           placeholder="Ej: PREV">
                    @error('codigo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label>Nombre del Cargo <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-control @error('nombre') is-invalid @enderror"
                       placeholder="Ej: Prevencionista de Riesgos" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('cargos.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
