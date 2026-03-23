@extends('layouts.app')
@section('title','Centros de Costo')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h1>Centros de Costo</h1>
            <p style="color:var(--text-muted);margin:0">Clientes donde SAEP presta servicios</p>
        </div>
        <a href="{{ route('centros-costo.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Centro
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nombre</th><th>Tipo Nómina</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($centros as $centro)
            <tr>
                <td><code>{{ $centro->codigo }}</code></td>
                <td><strong>{{ $centro->nombre }}</strong></td>
                <td>
                    <span class="badge {{ $centro->razon_social === 'TRANSITORIO' ? 'badge-warning' : 'badge-info' }}">
                        {{ $centro->razon_social }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $centro->activo ? 'badge-success' : 'badge-danger' }}">
                        {{ $centro->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('centros-costo.edit', $centro) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <form method="POST" action="{{ route('centros-costo.destroy', $centro) }}" style="display:inline"
                          onsubmit="return confirm('¿Desactivar este centro?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn" style="color:#ef4444" title="Desactivar">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay centros registrados. <a href="{{ route('centros-costo.create') }}">Crear el primero</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
