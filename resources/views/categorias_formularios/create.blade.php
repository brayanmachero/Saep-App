@extends('layouts.app')
@section('title','Nueva Categoría')
@section('content')
<div class="page-container" style="max-width:680px">
    <div class="page-header">
        <div><h1>Nueva Categoría de Formularios</h1></div>
        <a href="{{ route('categorias-formularios.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('categorias-formularios.store') }}">
            @csrf
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-control @error('nombre') is-invalid @enderror"
                       placeholder="Ej: Prevención de Riesgos" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Ícono (emoji o clase)</label>
                    <input type="text" name="icono" value="{{ old('icono','📋') }}"
                           class="form-control" placeholder="📋">
                    <small style="color:var(--text-muted)">Usa un emoji directamente</small>
                </div>
                <div class="form-group">
                    <label>Color (hex)</label>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="color" name="color" value="{{ old('color','#6366f1') }}"
                               id="colorPicker" style="width:48px;height:38px;border:none;cursor:pointer;border-radius:6px">
                        <input type="text" id="colorText" value="{{ old('color','#6366f1') }}"
                               class="form-control" style="flex:1" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="{{ old('orden',0) }}"
                           class="form-control" min="0">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('categorias-formularios.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Guardar</button>
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
