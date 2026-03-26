@extends('layouts.app')
@section('title','Nuevo Programa SST')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-plus-circle-fill" style="color:var(--primary-color)"></i> Nuevo Programa SST</h2>
            <p class="page-subheading">El código se generará automáticamente al crear el programa</p>
        </div>
        <a href="{{ route('carta-gantt.index') }}" class="btn-ghost"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @include('partials._alerts')

    <form method="POST" action="{{ route('carta-gantt.store') }}">
        @csrf

        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-file-earmark-text"></i> Información del Programa
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Año *</label>
                    <input type="number" name="anio" value="{{ old('anio', date('Y')) }}"
                           class="form-input @error('anio') is-invalid @enderror" min="2020" max="2099" required>
                    @error('anio') <span class="error-msg">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-input">
                        <option value="ACTIVO" {{ old('estado','ACTIVO') === 'ACTIVO' ? 'selected' : '' }}>Activo</option>
                        <option value="BORRADOR" {{ old('estado') === 'BORRADOR' ? 'selected' : '' }}>Borrador</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Nombre del Programa *</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-input @error('nombre') is-invalid @enderror"
                       placeholder="Ej: Programa Anual de SST 2026" required>
                @error('nombre') <span class="error-msg">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" class="form-input" rows="3"
                          placeholder="Descripción general del programa...">{{ old('descripcion') }}</textarea>
            </div>
        </div>

        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1.25rem;font-weight:700;">
                <i class="bi bi-people-fill"></i> Asignación
            </h3>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Centro de Costo</label>
                    <select name="centro_costo_id" class="form-input">
                        <option value="">— Todos los centros —</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Responsable General</label>
                    <select name="responsable_id" class="form-input">
                        <option value="">— Sin asignar —</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}" {{ old('responsable_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} {{ $u->apellido_paterno ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('carta-gantt.index') }}" class="btn-ghost">Cancelar</a>
            <button type="submit" class="btn-premium" onclick="this.disabled=true;this.form.submit();">
                <i class="bi bi-check-lg"></i> Crear Programa
            </button>
        </div>
    </form>
</div>
@endsection
