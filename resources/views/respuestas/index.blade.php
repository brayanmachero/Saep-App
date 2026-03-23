@extends('layouts.app')

@section('title', 'Solicitudes')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Solicitudes</h2>
            <p class="page-subheading">Gestión de formularios y aprobaciones</p>
        </div>
        <a href="{{ route('respuestas.create') }}" class="btn-premium">
            <i class="bi bi-plus-circle-fill"></i> Nueva Solicitud
        </a>
    </div>

    <!-- Filtros -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('respuestas.index') }}" class="filter-form">
            <div class="filter-group">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    class="form-input" placeholder="Buscar por solicitante...">
            </div>
            <div class="filter-group">
                <select name="formulario_id" class="form-input">
                    <option value="">Todos los formularios</option>
                    @foreach($formularios as $f)
                        <option value="{{ $f->id }}" {{ request('formulario_id') == $f->id ? 'selected' : '' }}>
                            {{ $f->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <select name="estado" class="form-input">
                    <option value="">Todos los estados</option>
                    @foreach(['Borrador','Pendiente','Aprobado','Rechazado','Revisión'] as $est)
                        <option value="{{ $est }}" {{ request('estado') === $est ? 'selected' : '' }}>
                            {{ $est }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary">
                <i class="bi bi-search"></i> Filtrar
            </button>
            @if(request()->hasAny(['buscar','estado','formulario_id']))
                <a href="{{ route('respuestas.index') }}" class="btn-ghost">
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
                        <th>ID</th>
                        <th>Solicitante</th>
                        <th>Departamento</th>
                        <th>Formulario</th>
                        <th>Versión</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($respuestas as $r)
                    @php
                        $badgeMap = ['Pendiente'=>'warning','Aprobado'=>'success','Rechazado'=>'danger','Borrador'=>'','Revisión'=>'warning'];
                    @endphp
                    <tr>
                        <td><strong>#REQ-{{ str_pad($r->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                        <td>{{ $r->usuario->name ?? '—' }}</td>
                        <td>{{ $r->usuario->departamento->nombre ?? '—' }}</td>
                        <td>{{ $r->formulario->nombre ?? '—' }}</td>
                        <td><span class="badge">v{{ $r->version_form }}</span></td>
                        <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                        <td><span class="badge {{ $badgeMap[$r->estado] ?? '' }}">{{ $r->estado }}</span></td>
                        <td>
                            <div style="display:flex;gap:0.25rem;">
                                <a href="{{ route('respuestas.show', $r) }}" class="icon-btn" title="Ver"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                @if($r->estado === 'Borrador' && $r->usuario_id === auth()->id())
                                <a href="{{ route('respuestas.edit', $r) }}" class="icon-btn" title="Editar"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <form method="POST" action="{{ route('respuestas.destroy', $r) }}"
                                    onsubmit="return confirm('¿Eliminar esta solicitud?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" style="width:30px;height:30px;">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                            No hay solicitudes
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $respuestas->links() }}</div>
    </div>
</div>
@endsection
