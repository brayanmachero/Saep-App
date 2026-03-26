@extends('layouts.app')

@section('title', 'Documentación — ' . $meta['titulo'])

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $meta['titulo'] }}</h2>
            <p class="page-subheading">Documentación en preparación</p>
        </div>
        <a href="{{ route('documentacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="glass-card" style="text-align:center;padding:3rem 2rem;">
        <i class="bi bi-journal-text" style="font-size:3rem;color:var(--text-muted);display:block;margin-bottom:1rem;"></i>
        <h3 style="margin:0 0 .5rem;font-size:1.2rem;">Documentación pendiente</h3>
        <p style="color:var(--text-muted);max-width:400px;margin:0 auto;">
            La documentación del módulo <strong>{{ $meta['titulo'] }}</strong> está siendo elaborada y estará disponible próximamente.
        </p>
    </div>
</div>
@endsection
