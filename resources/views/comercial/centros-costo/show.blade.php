@extends('layouts.app')
@section('title', 'Centro de Costo Comercial')
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
            <h2 class="page-heading">{{ $centroCosto->nombre }}</h2>
            <p class="page-subheading">{{ $centroCosto->codigo }} • {{ $centroCosto->cliente?->nombre_comercial ?? $centroCosto->cliente?->nombre }}</p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('comercial.centros-costo.edit', $centroCosto) }}" class="btn-secondary"><i class="bi bi-pencil-fill"></i> Editar</a>
            <a href="{{ route('comercial.centros-costo.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @include('partials._alerts')

    <div class="glass-card" style="margin-bottom:1.5rem">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
            <div><div style="font-size:.85rem;color:var(--text-muted)">Cliente</div><strong>{{ $centroCosto->cliente?->nombre_comercial ?? $centroCosto->cliente?->nombre }}</strong></div>
            <div><div style="font-size:.85rem;color:var(--text-muted)">Responsable</div><strong>{{ $centroCosto->responsable ?? '—' }}</strong></div>
            <div><div style="font-size:.85rem;color:var(--text-muted)">Email Responsable</div><strong>{{ $centroCosto->email_responsable ?? '—' }}</strong></div>
            <div><div style="font-size:.85rem;color:var(--text-muted)">Estado</div><span class="badge {{ $centroCosto->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($centroCosto->estado) }}</span></div>
        </div>
    </div>

    <div class="glass-card">
        <h3 style="margin:0 0 1rem 0;font-size:1rem">Cotizaciones</h3>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead><tr><th>Número</th><th>Cargo</th><th>Estado</th><th>Precio Venta</th><th>Fecha</th></tr></thead>
                <tbody>
                    @forelse($centroCosto->cotizaciones as $cotizacion)
                    <tr>
                        <td><a href="{{ route('comercial.cotizaciones.show', $cotizacion) }}">{{ $cotizacion->numero }}</a></td>
                        <td>{{ $cotizacion->cargo }}</td>
                        @php($estadoCotizacion = $estadoOperativoCotizacion($cotizacion->estado))
                        <td><span class="badge {{ $estadoCotizacion === 'vigente' ? 'badge-success' : ($estadoCotizacion === 'en_cotizacion' ? 'badge-warning' : 'badge-secondary') }}">{{ $estadoLabelsCotizacion[$estadoCotizacion] ?? ucfirst(str_replace('_', ' ', $estadoCotizacion)) }}</span></td>
                        <td>${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</td>
                        <td>{{ $cotizacion->fecha_cotizacion?->format('d/m/Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--text-muted)">Sin cotizaciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
