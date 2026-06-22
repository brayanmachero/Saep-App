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

    @if(auth()->user()->tieneAcceso('comercial', 'puede_crear'))
    <div class="glass-card" style="margin-bottom:1rem">
        <form method="POST" action="{{ route('comercial.clientes.importar') }}" enctype="multipart/form-data"
              style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
            @csrf
            <div class="form-group" style="flex:1;min-width:280px;margin-bottom:0">
                <label>Importar clientes y centros</label>
                <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt">
                @error('archivo')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="btn-secondary">
                <i class="bi bi-upload"></i> Importar CSV
            </button>
        </form>
    </div>
    @endif

    {{-- Tabla de Clientes --}}
    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Centros de Costo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                    <tr>
                        <td>
                            <strong>{{ $cliente->nombre_comercial ?? $cliente->nombre }}</strong>
                        </td>
                        <td>
                            <span class="badge badge-info">{{ $cliente->centros_costo_count }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $cliente->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($cliente->estado) }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:.25rem">
                                <a href="{{ route('comercial.clientes.edit', $cliente) }}" class="icon-btn" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;padding:2rem;color:var(--text-muted)">
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
