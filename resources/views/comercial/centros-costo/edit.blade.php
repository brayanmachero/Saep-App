@extends('layouts.app')
@section('title', 'Editar Centro de Costo Comercial')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Editar Centro de Costo</h2>
            <p class="page-subheading">{{ $centroCosto->codigo }} - {{ $centroCosto->nombre }}</p>
        </div>
        <a href="{{ route('comercial.centros-costo.show', $centroCosto) }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @include('partials._alerts')

    <div class="glass-card">
        <form method="POST" action="{{ route('comercial.centros-costo.update', $centroCosto) }}">
            @csrf @method('PUT')
            @include('comercial::centros-costo._form', ['centroCosto' => $centroCosto])
            <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.5rem">
                <a href="{{ route('comercial.centros-costo.show', $centroCosto) }}" class="btn-secondary">Cancelar</a>
                <button class="btn-premium" type="submit"><i class="bi bi-check-lg"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection
