@extends('layouts.app')
@section('title', 'Reporte de Clientes')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Reporte de Clientes</h2>
            <p class="page-subheading">Análisis de clientes y su actividad</p>
        </div>
    </div>

    @include('partials._alerts')

    {{-- Estadísticas Rápidas --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem">
        <div class="glass-card" style="border-left:4px solid var(--accent-primary)">
            <div style="font-size:.85rem;color:var(--text-muted)">Total Clientes</div>
            <div style="font-size:2rem;font-weight:700;margin-top:.5rem">{{ $totalClientes }}</div>
        </div>

        <div class="glass-card" style="border-left:4px solid var(--success-color)">
            <div style="font-size:.85rem;color:var(--text-muted)">Clientes Activos</div>
            <div style="font-size:2rem;font-weight:700;margin-top:.5rem">{{ $clientesActivos }}</div>
        </div>

        <div class="glass-card" style="border-left:4px solid var(--warning-color)">
            <div style="font-size:.85rem;color:var(--text-muted)">Total Cotizaciones</div>
            <div style="font-size:2rem;font-weight:700;margin-top:.5rem">{{ $totalCotizaciones }}</div>
        </div>

        <div class="glass-card" style="border-left:4px solid var(--accent-secondary)">
            <div style="font-size:.85rem;color:var(--text-muted)">Cotizaciones Vigentes</div>
            <div style="font-size:2rem;font-weight:700;margin-top:.5rem">{{ $cotizacionesVigentes }}</div>
        </div>
    </div>

    {{-- Tabla de Clientes --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>RUT</th>
                        <th>Nombre Comercial</th>
                        <th>Email</th>
                        <th>Región</th>
                        <th>Estado</th>
                        <th>Centros de Costo</th>
                        <th>Cotizaciones</th>
                        <th>Vigentes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                    <tr>
                        <td><code>{{ $cliente->rut }}</code></td>
                        <td>
                            <strong>{{ $cliente->nombre_comercial ?? $cliente->nombre }}</strong>
                        </td>
                        <td style="font-size:.9rem">{{ $cliente->email }}</td>
                        <td>{{ $cliente->region ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $cliente->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($cliente->estado) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-info">{{ $cliente->centros_costo_count }}</span>
                        </td>
                        <td>
                            <span class="badge badge-secondary">{{ $cliente->cotizaciones_count }}</span>
                        </td>
                        <td>
                            @php
                                $vigentes = $cliente->cotizaciones()->where('estado', 'vigente')->count();
                            @endphp
                            <span class="badge badge-success">{{ $vigentes }}</span>
                        </td>
                        <td>
                            <a href="{{ route('comercial.clientes.show', $cliente) }}" class="icon-btn" title="Ver">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            No hay clientes registrados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding:1.5rem;text-align:center">
            {{ $clientes->links() }}
        </div>
    </div>
</div>
@endsection
