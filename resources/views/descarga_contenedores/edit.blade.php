@extends('layouts.app')
@section('title','Editar Descarga')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar descarga</h2>
            <p class="page-subheading">{{ $descarga->contenedor ?: 'Registro #'.$descarga->id }}</p>
        </div>
        <a href="{{ route('descarga-contenedores.show', $descarga) }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <div class="glass-card">
        <form method="POST" action="{{ route('descarga-contenedores.update', $descarga) }}">
            @include('descarga_contenedores._form', ['descarga' => $descarga])
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('descarga-contenedores.show', $descarga) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-save"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
