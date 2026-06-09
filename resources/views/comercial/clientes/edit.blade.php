@extends('layouts.app')
@section('title', 'Editar Cliente')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Cliente</h2>
            <p class="page-subheading">{{ $cliente->nombre_comercial ?? $cliente->nombre }}</p>
        </div>
        <a href="{{ route('comercial.clientes.show', $cliente) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <div class="glass-card">
        <form method="POST" action="{{ route('comercial.clientes.update', $cliente) }}">
            @csrf @method('PATCH')

            <div class="form-grid">
                <div class="form-group">
                    <label>RUT <span class="required">*</span></label>
                    <input type="text" name="rut" value="{{ old('rut', $cliente->rut) }}"
                           class="form-control @error('rut') is-invalid @enderror" required>
                    @error('rut')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Razón Social <span class="required">*</span></label>
                    <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre) }}"
                           class="form-control @error('nombre') is-invalid @enderror" required>
                    @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Nombre Comercial</label>
                    <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial', $cliente->nombre_comercial) }}"
                           class="form-control @error('nombre_comercial') is-invalid @enderror">
                    @error('nombre_comercial')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $cliente->email) }}"
                           class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono) }}"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label>Región <span class="required">*</span></label>
                    <input type="text" name="region" value="{{ old('region', $cliente->region) }}"
                           class="form-control @error('region') is-invalid @enderror" required>
                    @error('region')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" value="{{ old('direccion', $cliente->direccion) }}" class="form-control">
            </div>

            <div class="form-group">
                <label>Ciudad</label>
                <input type="text" name="ciudad" value="{{ old('ciudad', $cliente->ciudad) }}" class="form-control">
            </div>

            <div class="form-group">
                <label>Estado</label>
                <select name="estado" class="form-control">
                    <option value="activo" {{ $cliente->estado === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ $cliente->estado === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>

            <div class="form-group">
                <label>Contacto - Nombre</label>
                <input type="text" name="contacto_nombre" value="{{ old('contacto_nombre', $cliente->contacto_nombre) }}" class="form-control">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Contacto - Email</label>
                    <input type="email" name="contacto_email" value="{{ old('contacto_email', $cliente->contacto_email) }}" class="form-control">
                </div>

                <div class="form-group">
                    <label>Contacto - Teléfono</label>
                    <input type="text" name="contacto_telefono" value="{{ old('contacto_telefono', $cliente->contacto_telefono) }}" class="form-control">
                </div>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('comercial.clientes.show', $cliente) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-check-lg"></i> Actualizar Cliente
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
