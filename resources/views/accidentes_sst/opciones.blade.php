@extends('layouts.app')
@section('title','Catálogo Opciones Accidentes')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Catálogo de Opciones — Accidentes SST</h2>
            <p class="page-subheading">Gestiona las opciones de lesiones, causas y medidas preventivas</p>
        </div>
        <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Volver a Accidentes</a>
    </div>
    @include('partials._alerts')

    {{-- Tabs por tipo --}}
    <div style="display:flex;gap:.5rem;margin-bottom:1.5rem">
        @foreach(['lesion' => 'Lesiones / Diagnóstico', 'causa' => 'Causas', 'medida' => 'Medidas Preventivas'] as $key => $label)
        <a href="{{ route('accidentes-sst.opciones', ['tipo' => $key]) }}"
           class="btn-{{ $tipo === $key ? 'premium' : 'secondary' }}"
           style="position:relative">
            {{ $label }}
            <span class="badge badge-info" style="margin-left:.35rem">{{ $conteos[$key] ?? 0 }}</span>
        </a>
        @endforeach
    </div>

    <div class="glass-card">
        {{-- Formulario para agregar --}}
        <form method="POST" action="{{ route('accidentes-sst.opciones.store') }}" style="display:flex;gap:.75rem;margin-bottom:1.5rem">
            @csrf
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="text" name="nombre" class="form-control" placeholder="Nueva opción..." required
                   style="flex:1" maxlength="300">
            <button type="submit" class="btn-premium"><i class="bi bi-plus-lg"></i> Agregar</button>
        </form>

        {{-- Tabla de opciones --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:60%">Nombre</th>
                    <th>Estado</th>
                    <th>Usos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($opciones as $op)
            <tr id="row-{{ $op->id }}" style="{{ !$op->activo ? 'opacity:.5' : '' }}">
                <td>
                    <span class="opcion-nombre" id="nombre-{{ $op->id }}">{{ $op->nombre }}</span>
                    <input type="text" class="form-control opcion-edit" id="edit-{{ $op->id }}"
                           value="{{ $op->nombre }}" style="display:none" maxlength="300">
                </td>
                <td>
                    <span class="badge {{ $op->activo ? 'badge-success' : 'badge-secondary' }}">
                        {{ $op->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td><code>{{ $op->accidentes()->count() }}</code></td>
                <td style="display:flex;gap:.35rem">
                    <button class="icon-btn" title="Editar" onclick="toggleEdit({{ $op->id }})">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="icon-btn" title="{{ $op->activo ? 'Desactivar' : 'Activar' }}"
                            onclick="toggleActivo({{ $op->id }})">
                        <i class="bi bi-{{ $op->activo ? 'eye-slash' : 'eye' }}"></i>
                    </button>
                    @if($op->accidentes()->count() === 0)
                    <form method="POST" action="{{ route('accidentes-sst.opciones.destroy', $op) }}"
                          style="display:inline" onsubmit="return confirm('¿Eliminar esta opción?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger"><i class="bi bi-trash-fill"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay opciones registradas. Agrega la primera arriba.
            </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleEdit(id) {
    const span = document.getElementById('nombre-' + id);
    const input = document.getElementById('edit-' + id);
    if (input.style.display === 'none') {
        span.style.display = 'none';
        input.style.display = '';
        input.focus();
        input.addEventListener('keydown', function handler(e) {
            if (e.key === 'Enter') { saveEdit(id); input.removeEventListener('keydown', handler); }
            if (e.key === 'Escape') { cancelEdit(id); input.removeEventListener('keydown', handler); }
        });
    } else {
        saveEdit(id);
    }
}

function cancelEdit(id) {
    const span = document.getElementById('nombre-' + id);
    const input = document.getElementById('edit-' + id);
    input.value = span.textContent;
    input.style.display = 'none';
    span.style.display = '';
}

function saveEdit(id) {
    const span = document.getElementById('nombre-' + id);
    const input = document.getElementById('edit-' + id);
    const nuevo = input.value.trim();
    if (!nuevo || nuevo === span.textContent) { cancelEdit(id); return; }

    fetch('/accidentes-sst/opciones/' + id, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ nombre: nuevo })
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            span.textContent = nuevo;
            cancelEdit(id);
        }
    });
}

function toggleActivo(id) {
    fetch('/accidentes-sst/opciones/' + id + '/toggle', {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(data => {
        if (data.ok) location.reload();
    });
}
</script>
@endsection
