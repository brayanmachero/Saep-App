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
        @if(auth()->user()->tieneAcceso('formularios', 'puede_crear'))
        <a href="{{ route('formularios.create') }}" class="btn-premium">
            <i class="bi bi-plus-circle-fill"></i> Nuevo Formulario
        </a>
        @endif
    </div>

    <!-- Filtros -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('formularios.index') }}" class="filter-form">
            <div class="filter-group">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    class="form-input" placeholder="Buscar por nombre o código...">
            </div>
            <div class="filter-group">
                <select name="categoria_id" class="form-input">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ request('categoria_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
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
            @if(request()->hasAny(['buscar','activo','categoria_id']))
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
                        <th>Categoría</th>
                        <th>Depto.</th>
                        <th>Vigencia</th>
                        <th>Versión</th>
                        <th>Estado</th>
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
                            @if($form->requiere_aprobacion)
                                <i class="bi bi-shield-check" style="color:var(--accent-color);font-size:.7rem" title="Requiere aprobación"></i>
                            @endif
                            @if($form->frecuencia)
                                <span class="badge" style="font-size:.65rem;margin-left:.25rem">{{ ucfirst($form->frecuencia) }}</span>
                            @endif
                        </td>
                        <td>
                            @if($form->categoria)
                                <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem">
                                    <i class="bi {{ $form->categoria->icono }}" style="color:{{ $form->categoria->color }}"></i>
                                    {{ $form->categoria->nombre }}
                                </span>
                            @else
                                <span style="color:var(--text-muted)">—</span>
                            @endif
                        </td>
                        <td>{{ $form->departamento->nombre ?? '—' }}</td>
                        <td style="font-size:.78rem">
                            @if($form->fecha_inicio || $form->fecha_fin)
                                {{ $form->fecha_inicio?->format('d/m/Y') ?? '∞' }}
                                → {{ $form->fecha_fin?->format('d/m/Y') ?? '∞' }}
                            @else
                                <span style="color:var(--text-muted)">Permanente</span>
                            @endif
                        </td>
                        <td><span class="badge">v{{ $form->version }}</span></td>
                        <td>
                            <span class="badge {{ $form->activo ? 'success' : 'danger' }}">
                                {{ $form->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;">
                                <a href="{{ route('formularios.show', $form) }}" class="icon-btn" title="Ver"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                @if(auth()->user()->tieneAcceso('formularios', 'puede_editar'))
                                <a href="{{ route('formularios.edit', $form) }}" class="icon-btn" title="Editar"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                @endif
                                @if(auth()->user()->tieneAcceso('formularios', 'puede_eliminar'))
                                <form method="POST" action="{{ route('formularios.destroy', $form) }}"
                                    onsubmit="return confirm('¿Eliminar/desactivar este formulario?')">
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
                        <td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">
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
