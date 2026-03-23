@extends('layouts.app')

@section('title', 'Nuevo Departamento')

@section('content')
<div class="page-container" style="max-width:600px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Departamento</h2>
        </div>
        <a href="{{ route('departamentos.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('departamentos.store') }}">
            @csrf

            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" class="form-input @error('codigo') is-invalid @enderror"
                    value="{{ old('codigo') }}" placeholder="Ej: RRHH" style="text-transform:uppercase;">
                @error('codigo') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" class="form-input @error('nombre') is-invalid @enderror"
                    value="{{ old('nombre') }}" placeholder="Ej: Recursos Humanos">
                @error('nombre') <span class="error-msg">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" class="form-input" rows="3"
                    placeholder="Descripción del departamento...">{{ old('descripcion') }}</textarea>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <a href="{{ route('departamentos.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-building-check"></i> Crear Departamento
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
