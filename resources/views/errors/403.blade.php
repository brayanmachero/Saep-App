@extends('layouts.app')

@section('title', 'Acceso Denegado')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-shield-lock" style="font-size: 4rem; color: var(--danger-color, #ef4444);"></i>
                    <h2 class="mt-3">Acceso Denegado</h2>
                    <p class="text-muted mt-2">{{ $exception->getMessage() ?: 'No tienes permisos para acceder a esta sección.' }}</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-house me-1"></i> Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
