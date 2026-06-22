@extends('layouts.app')
@section('title', 'Cliente - ' . ($cliente->nombre_comercial ?? $cliente->nombre))
@php
    $estadoOperativoCotizacion = function ($estado) {
        return match ($estado) {
            'aprobada' => 'vigente',
            'rechazada', 'cancelada' => 'no_vigente',
            default => $estado,
        };
    };
    $estadoLabelsCotizacion = [
        'en_cotizacion' => 'En cotización',
        'vigente' => 'Vigente/Aprobado',
        'no_vigente' => 'No vigente',
    ];
@endphp
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">{{ $cliente->nombre_comercial ?? $cliente->nombre }}</h2>
            <p class="page-subheading">RUT: {{ $cliente->rut }}</p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('comercial.clientes.edit', $cliente) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('comercial.clientes.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @include('partials._alerts')

    {{-- Información del Cliente --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Información General</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem">
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Razón Social</div>
                <strong style="font-size:1.1rem">{{ $cliente->nombre }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Nombre Comercial</div>
                <strong style="font-size:1.1rem">{{ $cliente->nombre_comercial ?? '—' }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">RUT</div>
                <code style="font-size:1rem">{{ $cliente->rut }}</code>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Email</div>
                <a href="mailto:{{ $cliente->email }}" style="color:var(--accent-primary);text-decoration:none">
                    {{ $cliente->email }}
                </a>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Teléfono</div>
                <strong>{{ $cliente->telefono ?? '—' }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Estado</div>
                <span class="badge {{ $cliente->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                    {{ ucfirst($cliente->estado) }}
                </span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--surface-border)">
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Dirección</div>
                <strong>{{ $cliente->direccion ?? '—' }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Ciudad</div>
                <strong>{{ $cliente->ciudad ?? '—' }}</strong>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.5rem">Región</div>
                <strong>{{ $cliente->region ?? '—' }}</strong>
            </div>
        </div>
    </div>

    {{-- Contacto --}}
    @if($cliente->contacto_nombre || $cliente->contacto_email || $cliente->contacto_telefono)
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Contacto Principal</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem">
            @if($cliente->contacto_nombre)
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Nombre</div>
                <strong>{{ $cliente->contacto_nombre }}</strong>
            </div>
            @endif

            @if($cliente->contacto_email)
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Email</div>
                <a href="mailto:{{ $cliente->contacto_email }}" style="color:var(--accent-primary)">{{ $cliente->contacto_email }}</a>
            </div>
            @endif

            @if($cliente->contacto_telefono)
            <div>
                <div style="font-size:.85rem;color:var(--text-muted);font-weight:500;margin-bottom:.25rem">Teléfono</div>
                <strong>{{ $cliente->contacto_telefono }}</strong>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Centros de Costo --}}
    <div class="glass-card" style="margin-bottom:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Centros de Costo ({{ $cliente->centrosCosto->count() }})</h3>

        @if($cliente->centrosCosto->count() > 0)
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cliente->centrosCosto as $cc)
                    <tr>
                        <td><code>{{ $cc->codigo }}</code></td>
                        <td><strong>{{ $cc->nombre }}</strong></td>
                        <td>{{ $cc->ubicacion ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $cc->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($cc->estado) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div style="text-align:center;padding:2rem;color:var(--text-muted)">
            <p>No hay centros de costo registrados para este cliente.</p>
        </div>
        @endif
    </div>

    {{-- Cotizaciones Recientes --}}
    <div class="glass-card">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Cotizaciones Recientes ({{ $cliente->cotizaciones->count() }})</h3>

        @if($cliente->cotizaciones->count() > 0)
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Modalidad</th>
                        <th>Precio Venta</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cliente->cotizaciones()->latest()->take(10)->get() as $cotizacion)
                    <tr>
                        <td>
                            <a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}" style="color:var(--accent-primary);text-decoration:none">
                                {{ $cotizacion->numero }}
                            </a>
                        </td>
                        <td>
                            <span class="badge {{ $cotizacion->modalidad->codigo === 'EST' ? 'badge-info' : 'badge-warning' }}">
                                {{ $cotizacion->modalidad->codigo }}
                            </span>
                        </td>
                        <td><strong>${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</strong></td>
                        <td>
                            @php($estadoCotizacion = $estadoOperativoCotizacion($cotizacion->estado))
                            <span class="badge {{ $estadoCotizacion === 'vigente' ? 'badge-success' : ($estadoCotizacion === 'en_cotizacion' ? 'badge-warning' : 'badge-secondary') }}">
                                {{ $estadoLabelsCotizacion[$estadoCotizacion] ?? ucfirst(str_replace('_', ' ', $estadoCotizacion)) }}
                            </span>
                        </td>
                        <td style="font-size:.9rem">{{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div style="text-align:center;padding:2rem;color:var(--text-muted)">
            <p>No hay cotizaciones registradas para este cliente.</p>
        </div>
        @endif
    </div>
</div>
@endsection
