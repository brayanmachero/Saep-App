@extends('layouts.app')
@section('title','Editar Centro de Costo')
@section('content')
<div class="page-container" style="max-width:700px">
    <div class="page-header">
        <div>
            <h1>Editar Centro de Costo</h1>
            <p style="color:var(--text-muted);margin:0">{{ $centroCosto->nombre }}</p>
        </div>
        <a href="{{ route('centros-costo.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('centros-costo.update', $centroCosto) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Código <span class="required">*</span></label>
                    <input type="text" name="codigo" value="{{ old('codigo', $centroCosto->codigo) }}"
                           class="form-control @error('codigo') is-invalid @enderror" required>
                    @error('codigo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Tipo Nómina <span class="required">*</span></label>
                    <select name="razon_social" class="form-control @error('razon_social') is-invalid @enderror" required>
                        <option value="NORMAL" {{ old('razon_social', $centroCosto->razon_social) === 'NORMAL' ? 'selected' : '' }}>Normal</option>
                        <option value="TRANSITORIO" {{ old('razon_social', $centroCosto->razon_social) === 'TRANSITORIO' ? 'selected' : '' }}>Transitorio</option>
                    </select>
                    @error('razon_social')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label>Nombre del Centro <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre', $centroCosto->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $centroCosto->activo) ? 'checked' : '' }}>
                    Centro activo
                </label>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('centros-costo.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
