@extends('layouts.app')
@section('title','Crear Tablero')
@section('content')
<div class="page-container" style="max-width:640px;">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-kanban" style="color:var(--primary-color)"></i> Nuevo Tablero</h2>
            <p class="page-subheading">Se crearán columnas por defecto: Backlog, Por Hacer, En Progreso, Revisión, Completado</p>
        </div>
    </div>

    @include('partials._alerts')

    <div class="glass-card" style="padding:1.5rem;">
        <form method="POST" action="{{ route('kanban.store') }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.3rem;">Nombre del tablero *</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" class="form-input" required maxlength="200" placeholder="Ej: Proyecto Implementación SST">
                @error('nombre') <span style="color:#dc2626;font-size:.75rem;">{{ $message }}</span> @enderror
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.3rem;">Descripción</label>
                <textarea name="descripcion" class="form-input" rows="3" placeholder="Descripción opcional del tablero">{{ old('descripcion') }}</textarea>
            </div>
            <div style="margin-bottom:1.5rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.3rem;">Centro de Costo</label>
                <select name="centro_costo_id" class="form-input">
                    <option value="">— Sin asignar —</option>
                    @foreach($centros as $c)
                        <option value="{{ $c->id }}" {{ old('centro_costo_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn-premium"><i class="bi bi-check-lg"></i> Crear Tablero</button>
                <a href="{{ route('kanban.index') }}" class="btn-secondary" style="padding:.5rem 1rem;font-size:.82rem;">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
