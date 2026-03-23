@extends('layouts.app')

@section('title', 'Formularios')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Constructor de Formularios</h2>
            <p class="page-subheading">Diseña formularios dinámicos para tu organización</p>
        </div>
        <a href="{{ route('formularios.create') }}" class="btn-premium">
            <i class="bi bi-plus-circle-fill"></i> Nuevo Formulario
        </a>
    </div>

    <!-- Filtros -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('formularios.index') }}" class="filter-form">
            <div class="filter-group">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    class="form-input" placeholder="Buscar por nombre o código...">
            </div>
            <div class="filter-group">
                <select name="activo" class="form-input">
                    <option value="">Todos</option>
                    <option value="1" {{ request('activo') === '1' ? 'selected' : '' }}>Activos</option>
                    <option value="0" {{ request('activo') === '0' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">
                <i class="bi bi-search"></i> Filtrar
            </button>
            @if(request()->hasAny(['buscar','activo']))
                <a href="{{ route('formularios.index') }}" class="btn-ghost">
                    <i class="bi bi-x"></i> Limpiar
                </a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Departamento</th>
                        <th>Versión</th>
                        <th>Aprobación</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formularios as $form)
                    <tr>
                        <td><code>{{ $form->codigo }}</code></td>
                        <td>
                            <a href="{{ route('formularios.show', $form) }}" style="color:var(--primary-color);text-decoration:none;">
                                {{ $form->nombre }}
                            </a>
                        </td>
                        <td>{{ $form->departamento->nombre ?? '—' }}</td>
                        <td><span class="badge">v{{ $form->version }}</span></td>
                        <td>
                            @if($form->requiere_aprobacion)
                                <span class="badge warning"><i class="bi bi-check-circle"></i> Sí</span>
                            @else
                                <span class="badge">No</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $form->activo ? 'success' : 'danger' }}">
                                {{ $form->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>{{ $form->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div style="display:flex;gap:0.25rem;">
                                <a href="{{ route('formularios.show', $form) }}" class="icon-btn" title="Ver"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <a href="{{ route('formularios.edit', $form) }}" class="icon-btn" title="Editar"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <form method="POST" action="{{ route('formularios.destroy', $form) }}"
                                    onsubmit="return confirm('¿Eliminar/desactivar este formulario?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" title="Eliminar"
                                        style="width:30px;height:30px;">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">
                            <i class="bi bi-ui-checks-grid" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                            No hay formularios. ¡Crea el primero!
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $formularios->links() }}</div>
    </div>
</div>
@endsection
