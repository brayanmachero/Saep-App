@extends('layouts.app')
@section('title','Editar Programa SST')
@section('content')
<div class="page-container" style="max-width:760px">
    <div class="page-header">
        <div>
            <h1>Editar Programa SST</h1>
            <p style="color:var(--text-muted);margin:0">{{ $cartaGantt->nombre }}</p>
        </div>
        <a href="{{ route('carta-gantt.show', $cartaGantt) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Gantt
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <form method="POST" action="{{ route('carta-gantt.update', $cartaGantt) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>Código</label>
                    <input type="text" name="codigo" value="{{ old('codigo', $cartaGantt->codigo) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label>Año <span class="required">*</span></label>
                    <input type="number" name="anio" value="{{ old('anio', $cartaGantt->anio) }}"
                           class="form-control @error('anio') is-invalid @enderror" min="2020" max="2099" required>
                    @error('anio')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre', $cartaGantt->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required>
                @error('nombre')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3">{{ old('descripcion', $cartaGantt->descripcion) }}</textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Centro de Costo</label>
                    <select name="centro_costo_id" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id', $cartaGantt->centro_costo_id) == $cc->id ? 'selected' : '' }}>
                                {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        @foreach(['ACTIVO','BORRADOR','CERRADO'] as $e)
                        <option value="{{ $e }}" {{ old('estado', $cartaGantt->estado) === $e ? 'selected' : '' }}>
                            {{ ucfirst(strtolower($e)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Responsable</label>
                <select name="responsable_id" class="form-control">
                    <option value="">— Sin asignar —</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" {{ old('responsable_id', $cartaGantt->responsable_id) == $u->id ? 'selected' : '' }}>
                            {{ $u->name }} {{ $u->apellido_paterno }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('carta-gantt.show', $cartaGantt) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
