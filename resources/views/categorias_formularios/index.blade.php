@extends('layouts.app')
@section('title','Categorías de Formularios')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Categorías de Formularios</h2>
            <p style="color:var(--text-muted);margin:0">Agrupación de formularios Kizeo</p>
        </div>
        @if(auth()->user()->tieneAcceso('categorias_formularios', 'puede_crear'))
        <a href="{{ route('categorias-formularios.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nueva Categoría
        </a>
        @endif
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>Orden</th><th>Ícono</th><th>Nombre</th><th>Color</th><th>Formularios</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($categorias as $cat)
            <tr>
                <td>{{ $cat->orden }}</td>
                <td style="font-size:1.5rem">{{ $cat->icono }}</td>
                <td>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $cat->color }};margin-right:6px;vertical-align:middle"></span>
                    <strong>{{ $cat->nombre }}</strong>
                </td>
                <td><code>{{ $cat->color }}</code></td>
                <td>{{ $cat->formularios_count ?? 0 }}</td>
                <td>
                    <span class="badge {{ $cat->activo ? 'badge-success' : 'badge-danger' }}">
                        {{ $cat->activo ? 'Activa' : 'Inactiva' }}
                    </span>
                </td>
                <td>
                    @if(auth()->user()->tieneAcceso('categorias_formularios', 'puede_editar'))
                    <a href="{{ route('categorias-formularios.edit', $cat) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    @endif
                    @if(auth()->user()->tieneAcceso('categorias_formularios', 'puede_eliminar'))
                    <form method="POST" action="{{ route('categorias-formularios.destroy', $cat) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar esta categoría?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger"><i class="bi bi-trash-fill"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay categorías. <a href="{{ route('categorias-formularios.create') }}">Crear la primera</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
