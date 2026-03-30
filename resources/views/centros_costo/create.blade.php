@extends('layouts.app')
@section('title','Nuevo Centro de Costo')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Nuevo Centro de Costo</h2>
            <p class="page-subheading">Registrar cliente donde SAEP presta servicios</p>
        </div>
        <a href="{{ route('centros-costo.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('centros-costo.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>Tipo Nómina <span class="required">*</span></label>
                    <select name="razon_social" class="form-control @error('razon_social') is-invalid @enderror" required>
                        <option value="NORMAL" {{ old('razon_social') === 'NORMAL' ? 'selected' : '' }}>Normal</option>
                        <option value="TRANSITORIO" {{ old('razon_social') === 'TRANSITORIO' ? 'selected' : '' }}>Transitorio</option>
                    </select>
                    @error('razon_social')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label>Nombre del Centro <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-control @error('nombre') is-invalid @enderror"
                       placeholder="Nombre completo del cliente" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('centros-costo.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
