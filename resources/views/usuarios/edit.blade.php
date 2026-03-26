@extends('layouts.app')

@section('title', 'Editar Usuario')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Usuario</h2>
            <p class="page-subheading">{{ $usuario->nombre_completo }}</p>
        </div>
        <a href="{{ route('usuarios.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('usuarios.update', $usuario) }}">
            @csrf @method('PUT')

            {{-- Sección: Datos Personales --}}
            <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-person-fill" style="color:var(--primary-color);"></i> Datos Personales
                </h3>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="name" class="form-input @error('name') is-invalid @enderror"
                        value="{{ old('name', $usuario->name) }}">
                    @error('name') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Apellido Paterno</label>
                    <input type="text" name="apellido_paterno" class="form-input @error('apellido_paterno') is-invalid @enderror"
                        value="{{ old('apellido_paterno', $usuario->apellido_paterno) }}">
                    @error('apellido_paterno') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Apellido Materno</label>
                    <input type="text" name="apellido_materno" class="form-input @error('apellido_materno') is-invalid @enderror"
                        value="{{ old('apellido_materno', $usuario->apellido_materno) }}">
                    @error('apellido_materno') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>RUT</label>
                    <input type="text" name="rut" class="form-input @error('rut') is-invalid @enderror"
                        value="{{ old('rut', $usuario->rut) }}" placeholder="12.345.678-9">
                    @error('rut') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Correo electrónico *</label>
                    <input type="email" name="email" class="form-input @error('email') is-invalid @enderror"
                        value="{{ old('email', $usuario->email) }}">
                    @error('email') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" class="form-input @error('telefono') is-invalid @enderror"
                        value="{{ old('telefono', $usuario->telefono) }}" placeholder="+56 9 1234 5678">
                    @error('telefono') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-input @error('fecha_nacimiento') is-invalid @enderror"
                        value="{{ old('fecha_nacimiento', $usuario->fecha_nacimiento) }}">
                    @error('fecha_nacimiento') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Nacionalidad</label>
                    <input type="text" name="nacionalidad" class="form-input @error('nacionalidad') is-invalid @enderror"
                        value="{{ old('nacionalidad', $usuario->nacionalidad) }}" placeholder="Ej: Chilena">
                    @error('nacionalidad') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Sexo</label>
                    <select name="sexo" class="form-input">
                        <option value="">Seleccionar</option>
                        <option value="M" {{ old('sexo', $usuario->sexo) == 'M' ? 'selected' : '' }}>Masculino</option>
                        <option value="F" {{ old('sexo', $usuario->sexo) == 'F' ? 'selected' : '' }}>Femenino</option>
                        <option value="Otro" {{ old('sexo', $usuario->sexo) == 'Otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado Civil</label>
                    <select name="estado_civil" class="form-input">
                        <option value="">Seleccionar</option>
                        <option value="Soltero/a" {{ old('estado_civil', $usuario->estado_civil) == 'Soltero/a' ? 'selected' : '' }}>Soltero/a</option>
                        <option value="Casado/a" {{ old('estado_civil', $usuario->estado_civil) == 'Casado/a' ? 'selected' : '' }}>Casado/a</option>
                        <option value="Divorciado/a" {{ old('estado_civil', $usuario->estado_civil) == 'Divorciado/a' ? 'selected' : '' }}>Divorciado/a</option>
                        <option value="Viudo/a" {{ old('estado_civil', $usuario->estado_civil) == 'Viudo/a' ? 'selected' : '' }}>Viudo/a</option>
                    </select>
                </div>
            </div>

            {{-- Sección: Datos Laborales --}}
            <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;margin-top:2rem;">
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-briefcase-fill" style="color:var(--primary-color);"></i> Datos Laborales
                </h3>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Cargo</label>
                    <select name="cargo_id" class="form-input">
                        <option value="">Sin cargo</option>
                        @foreach($cargos as $cargo)
                            <option value="{{ $cargo->id }}" {{ old('cargo_id', $usuario->cargo_id) == $cargo->id ? 'selected' : '' }}>
                                {{ $cargo->nombre }}
                            </option>
                        @endforeach
                    </select>
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
                    <label>Centro de Costo</label>
                    <select name="centro_costo_id" class="form-input">
                        <option value="">Sin centro de costo</option>
                        @foreach($centrosCosto as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $usuario->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de Nómina</label>
                    <select name="tipo_nomina" class="form-input">
                        <option value="NORMAL" {{ old('tipo_nomina', $usuario->tipo_nomina) == 'NORMAL' ? 'selected' : '' }}>Normal</option>
                        <option value="TRANSITORIO" {{ old('tipo_nomina', $usuario->tipo_nomina) == 'TRANSITORIO' ? 'selected' : '' }}>Transitorio</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Razón Social</label>
                    <input type="text" name="razon_social" class="form-input @error('razon_social') is-invalid @enderror"
                        value="{{ old('razon_social', $usuario->razon_social) }}" placeholder="Nombre de la empresa">
                    @error('razon_social') <span class="error-msg">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label>Fecha de Ingreso</label>
                    <input type="date" name="fecha_ingreso" class="form-input @error('fecha_ingreso') is-invalid @enderror"
                        value="{{ old('fecha_ingreso', $usuario->fecha_ingreso) }}">
                    @error('fecha_ingreso') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Sección: Acceso al Sistema --}}
            <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;margin-top:2rem;">
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-shield-lock-fill" style="color:var(--primary-color);"></i> Acceso al Sistema
                </h3>
            </div>

            <div class="form-grid-2">
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
