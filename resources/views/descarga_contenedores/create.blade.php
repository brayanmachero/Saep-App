@extends('layouts.app')
@section('title','Nueva Descarga')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Nueva descarga de contenedor</h2>
            <p class="page-subheading">Registro manual con selección de trabajadores participantes.</p>
        </div>
        <a href="{{ route('descarga-contenedores.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')

    <div class="glass-card">
        <form method="POST" action="{{ route('descarga-contenedores.store') }}">
            @include('descarga_contenedores._form')
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
                <a href="{{ route('descarga-contenedores.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-save"></i> Guardar descarga
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
