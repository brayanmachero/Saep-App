@extends('layouts.app')
@section('title', 'Contratación — Configuración')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-gear-fill" style="color:#0ea5e9"></i> Contratación — Configuración
            </h2>
            <p class="page-subheading">Correos de notificación para nuevas postulaciones</p>
        </div>
        <a href="{{ route('contratacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div style="max-width:600px;">
        <div class="glass-card" style="padding:1.75rem;">
            <form method="POST" action="{{ route('contratacion.guardar-configuracion') }}">
                @csrf
                @method('PATCH')

                <div style="margin-bottom:1.5rem;">
                    <label style="font-size:.85rem;font-weight:700;color:var(--text-main);display:block;margin-bottom:.4rem;">
                        Correos de notificación
                    </label>
                    <textarea name="emails" class="form-control" rows="4"
                        placeholder="rrhh@empresa.cl, gerencia@empresa.cl">{{ $emails }}</textarea>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:.4rem;">
                        Separa múltiples correos con comas. Recibirán una notificación por cada nueva postulación.
                    </div>
                    @error('emails')
                    <div style="font-size:.78rem;color:#ef4444;margin-top:.3rem;">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn-premium" style="padding:.55rem 1.25rem;font-size:.85rem;">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
