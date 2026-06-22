@extends('layouts.app')
@section('title', 'Nuevo Cliente')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Cliente</h2>
            <p class="page-subheading">Registro rápido para cotizaciones</p>
        </div>
        <a href="{{ route('comercial.clientes.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <div class="glass-card" style="max-width:720px">
        <form method="POST" action="{{ route('comercial.clientes.store') }}">
            @csrf

            <div class="form-group">
                <label>Nombre Cliente <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-control @error('nombre') is-invalid @enderror"
                       placeholder="Ej: CCU, Walmart, SMU" required autofocus>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
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
