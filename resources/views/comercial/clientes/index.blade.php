@extends('layouts.app')
@section('title', 'Clientes - Módulo Comercial')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Clientes</h2>
            <p class="page-subheading">Gestión de clientes para cotizaciones</p>
        </div>
        <a href="{{ route('comercial.clientes.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Cliente
        </a>
    </div>

    @include('partials._alerts')

    {{-- Tabla de Clientes --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>RUT</th>
                        <th>Nombre Comercial</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Centros de Costo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                    <tr>
                        <td>
                            <code style="font-size:.85rem">{{ $cliente->rut }}</code>
                        </td>
                        <td>
                            <strong>{{ $cliente->nombre_comercial ?? $cliente->nombre }}</strong>
                        </td>
                        <td style="font-size:.9rem">{{ $cliente->email }}</td>
                        <td style="font-size:.9rem">{{ $cliente->telefono ?? 'N/A' }}</td>
                        <td>
                            <span class="badge badge-info">{{ $cliente->centrosCosto->count() }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $cliente->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($cliente->estado) }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:.25rem">
                                <a href="{{ route('comercial.clientes.show', $cliente) }}" class="icon-btn" title="Ver Detalles">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <a href="{{ route('comercial.clientes.edit', $cliente) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            No hay clientes registrados. <a href="{{ route('comercial.clientes.create') }}">Crear el primero</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div style="padding:1.5rem;text-align:center">
            {{ $clientes->links() }}
        </div>
    </div>
</div>
@endsection
