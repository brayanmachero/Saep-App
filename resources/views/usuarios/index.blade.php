@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="page-container">

    @include('partials._alerts')

    <div class="page-header">
        <div>
            <h2 class="page-heading">Gestión de Usuarios</h2>
            <p class="page-subheading">Administra los usuarios y sus permisos</p>
        </div>
        @if(auth()->user()->tieneAcceso('usuarios', 'puede_crear'))
        <a href="{{ route('usuarios.create') }}" class="btn-premium">
            <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
        </a>
        @endif
    </div>

    <!-- Filtros -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('usuarios.index') }}" class="filter-form">
            <div class="filter-group">
                <input type="text" name="buscar" value="{{ request('buscar') }}" 
                    class="form-input" placeholder="Buscar por nombre, email o RUT...">
            </div>
            <div class="filter-group">
                <select name="rol_id" class="form-input">
                    <option value="">Todos los roles</option>
                    @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" {{ request('rol_id') == $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <select name="departamento_id" class="form-input">
                    <option value="">Todos los departamentos</option>
                    @foreach($departamentos as $dep)
                        <option value="{{ $dep->id }}" {{ request('departamento_id') == $dep->id ? 'selected' : '' }}>
                            {{ $dep->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <select name="cargo_id" class="form-input">
                    <option value="">Todos los cargos</option>
                    @foreach($cargos as $cargo)
                        <option value="{{ $cargo->id }}" {{ request('cargo_id') == $cargo->id ? 'selected' : '' }}>
                            {{ $cargo->nombre }}
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
            @if(request()->hasAny(['buscar','rol_id','departamento_id','cargo_id','activo']))
                <a href="{{ route('usuarios.index') }}" class="btn-ghost">
                    <i class="bi bi-x"></i> Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Tabla -->
    <div class="glass-card">
        {{-- Bulk actions bar --}}
        <div id="bulkBar" style="display:none;padding:.6rem 1rem;background:linear-gradient(135deg,#6366f1,#818cf8);border-radius:10px 10px 0 0;display:none;align-items:center;gap:.75rem;">
            <span style="color:#fff;font-weight:600;font-size:.85rem;">
                <i class="bi bi-check2-square"></i> <span id="bulkCount">0</span> seleccionado(s)
            </span>
            <button type="button" onclick="confirmBulkReset()" class="btn-secondary" style="font-size:.78rem;padding:.35rem .75rem;background:#fff;color:#6366f1;border:none;font-weight:700;">
                <i class="bi bi-key-fill"></i> Resetear Contraseñas
            </button>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        @if(auth()->user()->tieneAcceso('usuarios', 'puede_editar'))
                        <th style="width:40px;text-align:center;">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor:pointer;width:16px;height:16px;">
                        </th>
                        @endif
                        <th>Usuario</th>
                        <th>RUT</th>
                        <th>Cargo</th>
                        <th>Departamento</th>
                        <th>Centro de Costo</th>
                        <th>Razón Social</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($usuarios as $usuario)
                    <tr>
                        @if(auth()->user()->tieneAcceso('usuarios', 'puede_editar'))
                        <td style="text-align:center;">
                            <input type="checkbox" class="user-checkbox" value="{{ $usuario->id }}" onchange="updateBulkBar()" style="cursor:pointer;width:16px;height:16px;">
                        </td>
                        @endif
                        <td>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div class="avatar" style="width:34px;height:34px;font-size:0.8rem;flex-shrink:0;">
                                    {{ strtoupper(substr($usuario->name, 0, 1)) }}
                                </div>
                                <div>
                                    <span style="font-weight:600;">{{ $usuario->nombre_completo }}</span>
                                    <span style="display:block;font-size:0.8rem;color:var(--text-muted);">{{ $usuario->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $usuario->rut ?? '—' }}</td>
                        <td>{{ $usuario->cargo->nombre ?? '—' }}</td>
                        <td>{{ $usuario->departamento->nombre ?? '—' }}</td>
                        <td>{{ $usuario->centroCosto->nombre ?? '—' }}</td>
                        <td>{{ $usuario->razon_social ?? '—' }}</td>
                        <td><span class="badge">{{ $usuario->rol->nombre ?? '—' }}</span></td>
                        <td>
                            <span class="badge {{ $usuario->activo ? 'success' : 'danger' }}">
                                {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;">
                                @if(auth()->user()->tieneAcceso('usuarios', 'puede_editar'))
                                <a href="{{ route('usuarios.edit', $usuario) }}" class="icon-btn" title="Editar"
                                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                @if($usuario->id !== auth()->id())
                                <form method="POST" action="{{ route('usuarios.resetPassword', $usuario) }}"
                                    onsubmit="return confirm('¿Resetear la contraseña de {{ addslashes($usuario->nombre_completo) }}?')">
                                    @csrf
                                    <button type="submit" class="icon-btn warning" title="Resetear contraseña"
                                        style="width:30px;height:30px;">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
                                </form>
                                @endif
                                @endif
                                @if(auth()->user()->tieneAcceso('usuarios', 'puede_eliminar') && $usuario->id !== auth()->id())
                                <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}"
                                    onsubmit="return confirm('¿Desactivar este usuario?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" title="Desactivar"
                                        style="width:30px;height:30px;">
                                        <i class="bi bi-person-x-fill"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" style="text-align:center;color:var(--text-muted);padding:2rem;">
                            <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                            No hay usuarios
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">
            {{ $usuarios->links() }}
        </div>
    </div>
</div>

{{-- Hidden form for bulk reset --}}
<form id="bulkResetForm" method="POST" action="{{ route('usuarios.bulkResetPassword') }}" style="display:none;">
    @csrf
</form>

@endsection

@push('scripts')
<script>
function toggleSelectAll(source) {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = source.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('bulkCount').textContent = checked.length;
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
}

function confirmBulkReset() {
    const ids = [...document.querySelectorAll('.user-checkbox:checked')].map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`¿Resetear la contraseña de ${ids.length} usuario(s)?`)) return;

    const form = document.getElementById('bulkResetForm');
    form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    form.submit();
}
</script>
@endpush
