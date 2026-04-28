@extends('layouts.app')
@section('title', 'Ingreso Manual — Contratación')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <!-- Header -->
    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-person-plus-fill" style="color:#6366f1"></i> Ingreso Manual
            </h2>
            <p class="page-subheading">Registra un postulante de forma manual sin pasar por el formulario público</p>
        </div>
        <div>
            <a href="{{ route('contratacion.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver al listado
            </a>
        </div>
    </div>

    <!-- Formulario -->
    <form method="POST" action="{{ route('contratacion.store-manual') }}" enctype="multipart/form-data">
        @csrf

        <!-- Datos personales -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <div style="padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);margin-bottom:1.25rem;">
                <h3 style="margin:0;font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-person-fill" style="color:#6366f1;"></i> Datos personales
                </h3>
            </div>
            <div style="padding:0 1.5rem 1.5rem;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem;">

                    <!-- Nombre -->
                    <div>
                        <label for="nombre" style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;">
                            Nombre completo <span style="color:#ef4444;">*</span>
                        </label>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            value="{{ old('nombre') }}"
                            class="form-control @error('nombre') is-invalid @enderror"
                            placeholder="Ej: Juan Pérez González"
                            required
                        >
                        @error('nombre')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- RUT -->
                    <div>
                        <label for="rut" style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;">
                            RUT <span style="color:#ef4444;">*</span>
                        </label>
                        <input
                            type="text"
                            id="rut"
                            name="rut"
                            value="{{ old('rut') }}"
                            class="form-control @error('rut') is-invalid @enderror"
                            placeholder="12.345.678-9"
                            data-rut
                            required
                        >
                        @error('rut')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" style="display:block;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;">
                            Correo electrónico <span style="color:#ef4444;">*</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            placeholder="postulante@correo.cl"
                            required
                        >
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        <!-- Documentos -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <div style="padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);margin-bottom:1.25rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                    <h3 style="margin:0;font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.5rem;">
                        <i class="bi bi-folder-fill" style="color:#f59e0b;"></i> Documentos
                    </h3>
                    <span style="font-size:.75rem;color:var(--text-muted);">JPG, PNG o PDF · máx. 10 MB por archivo · todos opcionales</span>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;">

                    @php
                    $documentos = [
                        'carnet_frontal'     => ['label' => 'Carnet de Identidad (Frontal)', 'icon' => 'bi-credit-card-fill', 'color' => '#0ea5e9'],
                        'carnet_reverso'     => ['label' => 'Carnet de Identidad (Reverso)', 'icon' => 'bi-credit-card-2-back-fill', 'color' => '#0ea5e9'],
                        'certificado_afp'    => ['label' => 'Certificado de AFP',             'icon' => 'bi-file-earmark-text-fill', 'color' => '#8b5cf6'],
                        'certificado_fonasa' => ['label' => 'Certificado FONASA',             'icon' => 'bi-heart-pulse-fill', 'color' => '#22c55e'],
                        'licencia_conducir'  => ['label' => 'Licencia de Conducir',           'icon' => 'bi-car-front-fill', 'color' => '#f59e0b'],
                    ];
                    @endphp

                    @foreach($documentos as $campo => $doc)
                    <div>
                        <label for="{{ $campo }}" style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:.35rem;">
                            <i class="bi {{ $doc['icon'] }}" style="color:{{ $doc['color'] }};font-size:.95rem;"></i>
                            {{ $doc['label'] }}
                        </label>
                        <input
                            type="file"
                            id="{{ $campo }}"
                            name="{{ $campo }}"
                            class="form-control @error($campo) is-invalid @enderror"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >
                        @error($campo)
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endforeach

                </div>
            </div>
        </div>

        <!-- Aviso envío de correo -->
        <div style="
            background:linear-gradient(135deg,#eef2ff 0%,#f0fdf4 100%);
            border:1px solid #c7d2fe;
            border-radius:12px;
            padding:1rem 1.25rem;
            display:flex;
            align-items:flex-start;
            gap:.75rem;
            margin-bottom:1.5rem;
        ">
            <i class="bi bi-envelope-check-fill" style="color:#6366f1;font-size:1.2rem;margin-top:.1rem;flex-shrink:0;"></i>
            <div>
                <p style="margin:0;font-size:.85rem;font-weight:600;color:#312e81;">Se enviarán notificaciones automáticamente</p>
                <p style="margin:.25rem 0 0;font-size:.8rem;color:#4338ca;">
                    Al guardar se enviará un acuse de recibo al postulante y una notificación al equipo de RRHH, igual que con el formulario público.
                </p>
            </div>
        </div>

        <!-- Acciones -->
        <div style="display:flex;justify-content:flex-end;gap:.75rem;flex-wrap:wrap;">
            <a href="{{ route('contratacion.index') }}" class="btn-ghost">
                <i class="bi bi-x-lg"></i> Cancelar
            </a>
            <button type="submit" class="btn-premium">
                <i class="bi bi-person-check-fill"></i> Guardar postulante
            </button>
        </div>

    </form>

</div>
@endsection
