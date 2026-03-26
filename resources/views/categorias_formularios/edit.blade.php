@extends('layouts.app')
@section('title','Editar Categoría')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Categoría</h2>
            <p style="color:var(--text-muted);margin:0">{{ $categoria->nombre }}</p>
        </div>
        <a href="{{ route('categorias-formularios.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('categorias-formularios.update', $categoria) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre', $categoria->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Ícono</label>
                    <input type="text" name="icono" value="{{ old('icono', $categoria->icono) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Color (hex)</label>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="color" name="color" value="{{ old('color', $categoria->color) }}"
                               id="colorPicker" style="width:48px;height:38px;border:none;cursor:pointer;border-radius:6px">
                        <input type="text" id="colorText" value="{{ old('color', $categoria->color) }}"
                               class="form-control" style="flex:1" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="{{ old('orden', $categoria->orden) }}" class="form-control" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $categoria->activo) ? 'checked' : '' }}>
                    Categoría activa
                </label>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('categorias-formularios.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
<script>
const picker = document.getElementById('colorPicker');
const txt = document.getElementById('colorText');
picker.addEventListener('input', () => txt.value = picker.value);
</script>
@endsection
