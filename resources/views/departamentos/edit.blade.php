@extends('layouts.app')

@section('title', 'Editar Departamento')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Departamento</h2>
            <p class="page-subheading">{{ $departamento->nombre }}</p>
        </div>
        <a href="{{ route('departamentos.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('departamentos.update', $departamento) }}">
            @csrf @method('PUT')

            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" class="form-input @error('codigo') is-invalid @enderror"
                    value="{{ old('codigo', $departamento->codigo) }}" style="text-transform:uppercase;">
                @error('codigo') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" class="form-input @error('nombre') is-invalid @enderror"
                    value="{{ old('nombre', $departamento->nombre) }}">
                @error('nombre') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" class="form-input" rows="3">{{ old('descripcion', $departamento->descripcion) }}</textarea>
            </div>

            <div class="form-group">
                <label>Estado</label>
                <select name="activo" class="form-input">
                    <option value="1" {{ old('activo', $departamento->activo) ? 'selected' : '' }}>Activo</option>
                    <option value="0" {{ !old('activo', $departamento->activo) ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <a href="{{ route('departamentos.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-save-fill"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
