@extends('layouts.app')
@section('title', 'Nuevo Cliente')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Cliente</h2>
            <p class="page-subheading">Registrar nuevo cliente para cotizaciones</p>
        </div>
        <a href="{{ route('comercial.clientes.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <div class="glass-card">
        <form method="POST" action="{{ route('comercial.clientes.store') }}">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label>RUT <span class="required">*</span></label>
                    <input type="text" name="rut" value="{{ old('rut') }}"
                           class="form-control @error('rut') is-invalid @enderror"
                           placeholder="XX.XXX.XXX-X" required>
                    @error('rut')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Razón Social <span class="required">*</span></label>
                    <input type="text" name="nombre" value="{{ old('nombre') }}"
                           class="form-control @error('nombre') is-invalid @enderror"
                           placeholder="Nombre empresa" required>
                    @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Nombre Comercial</label>
                    <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial') }}"
                           class="form-control @error('nombre_comercial') is-invalid @enderror"
                           placeholder="Nombre comercial">
                    @error('nombre_comercial')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="contacto@empresa.com" required>
                    @error('email')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="{{ old('telefono') }}"
                           class="form-control @error('telefono') is-invalid @enderror"
                           placeholder="+56 9 XXXX XXXX">
                    @error('telefono')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Región <span class="required">*</span></label>
                    <input type="text" name="region" value="{{ old('region') }}"
                           class="form-control @error('region') is-invalid @enderror"
                           placeholder="Región" required>
                    @error('region')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" value="{{ old('direccion') }}"
                       class="form-control @error('direccion') is-invalid @enderror"
                       placeholder="Dirección completa">
                @error('direccion')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label>Ciudad</label>
                <input type="text" name="ciudad" value="{{ old('ciudad') }}"
                       class="form-control @error('ciudad') is-invalid @enderror"
                       placeholder="Ciudad">
                @error('ciudad')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label>Contacto - Nombre</label>
                <input type="text" name="contacto_nombre" value="{{ old('contacto_nombre') }}"
                       class="form-control" placeholder="Nombre del contacto">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Contacto - Email</label>
                    <input type="email" name="contacto_email" value="{{ old('contacto_email') }}"
                           class="form-control" placeholder="Email del contacto">
                </div>

                <div class="form-group">
                    <label>Contacto - Teléfono</label>
                    <input type="text" name="contacto_telefono" value="{{ old('contacto_telefono') }}"
                           class="form-control" placeholder="Teléfono del contacto">
                </div>
            </div>

            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('comercial.clientes.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-check-lg"></i> Crear Cliente
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
