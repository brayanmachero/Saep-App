@extends('layouts.app')

@section('title', 'Departamentos')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Departamentos</h2>
            <p class="page-subheading">Organización de la empresa</p>
        </div>
        @if(auth()->user()->tieneAcceso('departamentos', 'puede_crear'))
        <a href="{{ route('departamentos.create') }}" class="btn-premium">
            <i class="bi bi-building-add"></i> Nuevo Departamento
        </a>
        @endif
    </div>

    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Usuarios</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departamentos as $dep)
                    <tr>
                        <td><code>{{ $dep->codigo }}</code></td>
                        <td>{{ $dep->nombre }}</td>
                        <td>{{ $dep->descripcion ?? '—' }}</td>
                        <td>
                            <span class="badge">{{ $dep->users_count }} usuarios</span>
                        </td>
                        <td>
                            <span class="badge {{ $dep->activo ? 'success' : 'danger' }}">
                                {{ $dep->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;">
                                @if(auth()->user()->tieneAcceso('departamentos', 'puede_editar'))
                                <a href="{{ route('departamentos.edit', $dep) }}" class="icon-btn" title="Editar"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                @endif
                                @if(auth()->user()->tieneAcceso('departamentos', 'puede_eliminar') && $dep->users_count === 0)
                                <form method="POST" action="{{ route('departamentos.destroy', $dep) }}"
                                    onsubmit="return confirm('¿Eliminar este departamento?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" title="Eliminar"
                                        style="width:30px;height:30px;">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">
                            No hay departamentos registrados
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $departamentos->links() }}</div>
    </div>
</div>
@endsection
