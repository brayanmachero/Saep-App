@extends('layouts.app')

@section('title', 'Nuevo Usuario')

@section('content')
<div class="page-container" style="max-width:800px;">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Usuario</h2>
            <p class="page-subheading">Completa los campos para crear un usuario</p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('usuarios.store') }}">
            @csrf

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Nombre completo *</label>
                    <input type="text" name="name" class="form-input @error('name') is-invalid @enderror"
                        value="{{ old('name') }}" placeholder="Ej: Juan Pérez González">
                    @error('name') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Correo electrónico *</label>
                    <input type="email" name="email" class="form-input @error('email') is-invalid @enderror"
                        value="{{ old('email') }}" placeholder="usuario@empresa.cl">
                    @error('email') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>RUT</label>
                    <input type="text" name="rut" class="form-input @error('rut') is-invalid @enderror"
                        value="{{ old('rut') }}" placeholder="12.345.678-9">
                    @error('rut') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol_id" class="form-input @error('rol_id') is-invalid @enderror">
                        <option value="">Seleccionar rol</option>
                        @foreach($roles as $rol)
                            <option value="{{ $rol->id }}" {{ old('rol_id') == $rol->id ? 'selected' : '' }}>
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
                            <option value="{{ $dep->id }}" {{ old('departamento_id') == $dep->id ? 'selected' : '' }}>
                                {{ $dep->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" class="form-input @error('password') is-invalid @enderror"
                        placeholder="Mínimo 8 caracteres">
                    @error('password') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Confirmar Contraseña *</label>
                    <input type="password" name="password_confirmation" class="form-input"
                        placeholder="Repite la contraseña">
                </div>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <a href="{{ route('usuarios.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-person-check-fill"></i> Crear Usuario
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
