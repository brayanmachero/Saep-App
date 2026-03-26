@extends('layouts.app')
@section('title','Editar Cargo')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Cargo</h2>
            <p class="page-subheading">{{ $cargo->nombre }}</p>
        </div>
        <a href="{{ route('cargos.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('cargos.update', $cargo) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" name="codigo" value="{{ old('codigo', $cargo->codigo) }}"
                           class="form-control @error('codigo') is-invalid @enderror">
                    @error('codigo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label>Nombre del Cargo <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre', $cargo->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $cargo->activo) ? 'checked' : '' }}>
                    Cargo activo
                </label>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('cargos.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
