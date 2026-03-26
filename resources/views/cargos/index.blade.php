@extends('layouts.app')
@section('title','Cargos')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Cargos</h2>
            <p class="page-subheading">Cargos y puestos de trabajo</p>
        </div>
        <a href="{{ route('cargos.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Cargo
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nombre</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($cargos as $cargo)
            <tr>
                <td><code>{{ $cargo->codigo }}</code></td>
                <td><strong>{{ $cargo->nombre }}</strong></td>
                <td>
                    <span class="badge {{ $cargo->activo ? 'badge-success' : 'badge-danger' }}">
                        {{ $cargo->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('cargos.edit', $cargo) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <form method="POST" action="{{ route('cargos.destroy', $cargo) }}" style="display:inline"
                          onsubmit="return confirm('¿Desactivar este cargo?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger" title="Desactivar">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay cargos registrados. <a href="{{ route('cargos.create') }}">Crear el primero</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
