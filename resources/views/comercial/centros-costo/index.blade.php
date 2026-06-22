@extends('layouts.app')
@section('title', 'Centros de Costo Comerciales')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Centros de Costo Comerciales</h2>
            <p class="page-subheading">Centros asociados a clientes del cotizador</p>
        </div>
        <a href="{{ route('comercial.centros-costo.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Centro
        </a>
    </div>

    @include('partials._alerts')

    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Centro de Costo</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centrosCosto as $centro)
                    <tr>
                        <td><strong>{{ $centro->nombre }}</strong></td>
                        <td>{{ $centro->cliente?->nombre_comercial ?? $centro->cliente?->nombre }}</td>
                        <td><span class="badge {{ $centro->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($centro->estado) }}</span></td>
                        <td>
                            <div style="display:flex;gap:.25rem">
                                <a href="{{ route('comercial.centros-costo.edit', $centro) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;padding:2rem;color:var(--text-muted)">No hay centros de costo comerciales registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:1.5rem;text-align:center">{{ $centrosCosto->links() }}</div>
    </div>
</div>
@endsection
