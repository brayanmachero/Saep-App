@extends('layouts.app')

@section('title', 'Editar Usuario')

@section('content')
<div class="page-container" style="max-width:800px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Usuario</h2>
            <p class="page-subheading">{{ $usuario->name }}</p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('usuarios.update', $usuario) }}">
            @csrf @method('PUT')

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Nombre completo *</label>
                    <input type="text" name="name" class="form-input @error('name') is-invalid @enderror"
                        value="{{ old('name', $usuario->name) }}">
                    @error('name') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Correo electrónico *</label>
                    <input type="email" name="email" class="form-input @error('email') is-invalid @enderror"
                        value="{{ old('email', $usuario->email) }}">
                    @error('email') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>RUT</label>
                    <input type="text" name="rut" class="form-input"
                        value="{{ old('rut', $usuario->rut) }}" placeholder="12.345.678-9">
                </div>

                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol_id" class="form-input @error('rol_id') is-invalid @enderror">
                        @foreach($roles as $rol)
                            <option value="{{ $rol->id }}" {{ old('rol_id', $usuario->rol_id) == $rol->id ? 'selected' : '' }}>
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('rol_id') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Departamento</label>
                    <select name="departamento_id" class="form-input">
                        <option value="">Sin departamento</option>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}" {{ old('departamento_id', $usuario->departamento_id) == $dep->id ? 'selected' : '' }}>
                                {{ $dep->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select name="activo" class="form-input">
                        <option value="1" {{ old('activo', $usuario->activo) ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ !old('activo', $usuario->activo) ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nueva Contraseña <small style="color:var(--text-muted)">(dejar en blanco para no cambiar)</small></label>
                    <input type="password" name="password" class="form-input @error('password') is-invalid @enderror"
                        placeholder="Mínimo 8 caracteres">
                    @error('password') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Confirmar Nueva Contraseña</label>
                    <input type="password" name="password_confirmation" class="form-input"
                        placeholder="Repite la nueva contraseña">
                </div>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <a href="{{ route('usuarios.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-save-fill"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
