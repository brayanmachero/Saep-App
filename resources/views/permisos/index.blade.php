@extends('layouts.app')
@section('title', 'Permisos por Rol')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-key-fill" style="color:var(--accent-color)"></i> Permisos por Rol</h2>
            <p class="page-subheading">Gestione los roles del sistema y configure qué módulos puede ver y gestionar cada uno</p>
        </div>
        <button onclick="document.getElementById('modal-crear-rol').style.display='flex'" class="btn-premium" style="padding:.5rem 1rem;font-size:.82rem;">
            <i class="bi bi-plus-lg"></i> Nuevo Rol
        </button>
    </div>

    @include('partials._alerts')

    {{-- ===== ROLES EXISTENTES ===== --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <h3 style="font-size:.88rem;font-weight:700;color:var(--text-primary);margin-bottom:.75rem;">
            <i class="bi bi-people-fill" style="color:var(--primary-color);"></i> Roles del Sistema
            <span style="font-size:.72rem;color:var(--text-muted);font-weight:400;margin-left:.3rem;">({{ $roles->count() }})</span>
        </h3>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem;">
            @foreach($roles as $rol)
            <div style="display:flex;align-items:center;gap:.5rem;padding:.5rem .85rem;border-radius:8px;border:1px solid var(--border-color);background:var(--card-bg);font-size:.8rem;" id="rol-card-{{ $rol->id }}">
                <div>
                    <span style="font-weight:700;color:var(--text-primary);">{{ $rol->nombre }}</span>
                    <span style="font-size:.68rem;color:var(--text-muted);margin-left:.3rem;">({{ $rol->codigo }})</span>
                    <span style="font-size:.65rem;color:var(--text-muted);display:block;">{{ $rol->users()->count() }} usuario{{ $rol->users()->count() !== 1 ? 's' : '' }}</span>
                </div>
                <div style="display:flex;gap:.25rem;margin-left:.5rem;">
                    <button onclick="abrirEditarRol({{ $rol->id }}, '{{ addslashes($rol->nombre) }}', '{{ $rol->codigo }}')" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:.78rem;padding:.15rem;" title="Editar rol">
                        <i class="bi bi-pencil"></i>
                    </button>
                    @if($rol->codigo !== 'SUPER_ADMIN')
                    <form method="POST" action="{{ route('roles.destroy', $rol) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar el rol «{{ $rol->nombre }}»? Esta acción no se puede deshacer.')">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:.78rem;padding:.15rem;opacity:.6;" title="Eliminar rol" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.6">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ===== MATRIZ DE PERMISOS ===== --}}
    <form method="POST" action="{{ route('permisos.update') }}">
        @csrf @method('PUT')

        {{-- Leyenda --}}
        <div class="glass-card" style="padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;font-size:.78rem">
            <span style="font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Leyenda:</span>
            <span><span class="perm-icon ver">V</span> Ver</span>
            <span><span class="perm-icon crear">C</span> Crear</span>
            <span><span class="perm-icon editar">E</span> Editar</span>
            <span><span class="perm-icon eliminar">X</span> Eliminar</span>
            <span style="margin-left:auto;color:var(--text-muted)">
                <i class="bi bi-info-circle"></i> Los cambios aplican inmediatamente al guardar
            </span>
        </div>

        @foreach($modulos as $grupo => $modulosGrupo)
        <div class="glass-card" style="margin-bottom:1.25rem;overflow:hidden">
            <div style="background:linear-gradient(135deg,var(--primary-color),#1a2d6d);color:#fff;padding:.65rem 1.25rem;display:flex;align-items:center;gap:.5rem;margin:-1px -1px 0">
                <i class="bi bi-folder-fill" style="font-size:.9rem;opacity:.7"></i>
                <h3 style="margin:0;font-size:.88rem;font-weight:700;letter-spacing:.03em">{{ $grupo }}</h3>
                <span style="margin-left:auto;font-size:.68rem;opacity:.6;text-transform:uppercase">{{ $modulosGrupo->count() }} módulos</span>
            </div>
            <div class="glass-table-container" style="margin:0;border-radius:0">
                <table class="glass-table" style="font-size:.78rem;margin:0">
                    <thead>
                        <tr>
                            <th style="width:220px;text-align:left;padding-left:1rem">Módulo</th>
                            @foreach($roles as $rol)
                            <th style="text-align:center;min-width:120px">
                                <div style="font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">{{ $rol->nombre }}</div>
                                <div style="font-size:.62rem;color:var(--text-muted);font-weight:400">{{ $rol->codigo }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modulosGrupo as $modulo)
                        <tr>
                            <td style="padding-left:1rem">
                                <div style="display:flex;align-items:center;gap:.5rem">
                                    <i class="bi {{ $modulo->icono }}" style="font-size:.85rem;color:var(--accent-color);width:18px;text-align:center"></i>
                                    <div>
                                        <span style="font-weight:600">{{ $modulo->nombre }}</span>
                                        @if($modulo->descripcion)
                                        <div style="font-size:.66rem;color:var(--text-muted);line-height:1.2">{{ $modulo->descripcion }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            @foreach($roles as $rol)
                            @php
                                $key = "{$rol->id}_{$modulo->id}";
                                $p = $permisos[$rol->id][$modulo->id] ?? null;
                                $ver      = $p ? $p->puede_ver : false;
                                $crear    = $p ? $p->puede_crear : false;
                                $editar   = $p ? $p->puede_editar : false;
                                $eliminar = $p ? $p->puede_eliminar : false;
                            @endphp
                            <td style="text-align:center">
                                <div style="display:flex;gap:.25rem;justify-content:center;flex-wrap:wrap">
                                    <label class="perm-check {{ $ver ? 'active' : '' }}" title="Ver">
                                        <input type="checkbox" name="permisos[{{ $key }}][ver]" value="1" {{ $ver ? 'checked' : '' }} onchange="this.closest('.perm-check').classList.toggle('active', this.checked)">
                                        <span>V</span>
                                    </label>
                                    <label class="perm-check {{ $crear ? 'active' : '' }}" title="Crear">
                                        <input type="checkbox" name="permisos[{{ $key }}][crear]" value="1" {{ $crear ? 'checked' : '' }} onchange="this.closest('.perm-check').classList.toggle('active', this.checked)">
                                        <span>C</span>
                                    </label>
                                    <label class="perm-check {{ $editar ? 'active' : '' }}" title="Editar">
                                        <input type="checkbox" name="permisos[{{ $key }}][editar]" value="1" {{ $editar ? 'checked' : '' }} onchange="this.closest('.perm-check').classList.toggle('active', this.checked)">
                                        <span>E</span>
                                    </label>
                                    <label class="perm-check {{ $eliminar ? 'active' : '' }}" title="Eliminar">
                                        <input type="checkbox" name="permisos[{{ $key }}][eliminar]" value="1" {{ $eliminar ? 'checked' : '' }} onchange="this.closest('.perm-check').classList.toggle('active', this.checked)">
                                        <span>X</span>
                                    </label>
                                </div>
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach

        <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:.5rem;margin-bottom:2rem">
            <button type="submit" class="btn-premium" style="padding:.55rem 1.5rem">
                <i class="bi bi-floppy-fill"></i> Guardar Permisos
            </button>
        </div>
    </form>
</div>

@push('styles')
<style>
.perm-icon {
    display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;
    border-radius:4px;font-size:.6rem;font-weight:800;color:#fff;
}
.perm-icon.ver { background:#3b82f6; }
.perm-icon.crear { background:#22c55e; }
.perm-icon.editar { background:#f97316; }
.perm-icon.eliminar { background:#ef4444; }

.perm-check {
    display:inline-flex;align-items:center;justify-content:center;
    width:24px;height:24px;border-radius:5px;cursor:pointer;
    background:var(--bg-color);border:1.5px solid var(--border-color,#e5e7eb);
    transition:all .15s ease;position:relative;
}
.perm-check input { position:absolute;opacity:0;pointer-events:none; }
.perm-check span {
    font-size:.58rem;font-weight:800;color:var(--text-muted);letter-spacing:.02em;
    transition:color .15s;
}
.perm-check:hover { border-color:var(--accent-color);transform:scale(1.08); }
.perm-check.active {
    background:var(--primary-color);border-color:var(--primary-color);
}
.perm-check.active span { color:#fff; }
</style>
@endpush

<script>
// Toggle all permissions for a column (role)
function toggleColumn(rolId) {
    const checks = document.querySelectorAll(`input[name*="[${rolId}_"]`);
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => {
        c.checked = !allChecked;
        c.closest('.perm-check').classList.toggle('active', c.checked);
    });
}

function abrirEditarRol(id, nombre, codigo) {
    document.getElementById('editar-rol-nombre').value = nombre;
    document.getElementById('editar-rol-codigo').value = codigo;
    document.getElementById('form-editar-rol').action = '/roles/' + id;
    document.getElementById('modal-editar-rol').style.display = 'flex';
}

function cerrarModal(id) {
    document.getElementById(id).style.display = 'none';
}
</script>

{{-- Modal Crear Rol --}}
<div id="modal-crear-rol" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;justify-content:center;align-items:center;backdrop-filter:blur(2px);" onclick="if(event.target===this)cerrarModal('modal-crear-rol')">
    <div class="glass-card" style="width:90%;max-width:420px;padding:1.5rem;" onclick="event.stopPropagation()">
        <h3 style="margin:0 0 1rem;font-size:1rem;font-weight:700;color:var(--text-primary);">
            <i class="bi bi-plus-circle" style="color:var(--primary-color);"></i> Crear Nuevo Rol
        </h3>
        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            <div style="margin-bottom:.75rem;">
                <label style="font-size:.75rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.25rem;">Nombre del Rol *</label>
                <input type="text" name="nombre" class="form-input" required placeholder="Ej: Analista de Datos" style="font-size:.85rem;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size:.75rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.25rem;">Código (opcional)</label>
                <input type="text" name="codigo" class="form-input" placeholder="Se genera automáticamente si se deja vacío" style="font-size:.85rem;text-transform:uppercase;">
                <span style="font-size:.68rem;color:var(--text-muted);">Identificador único, ej: ANALISTA_DATOS</span>
            </div>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" onclick="cerrarModal('modal-crear-rol')" class="btn-secondary" style="padding:.4rem .85rem;font-size:.8rem;">Cancelar</button>
                <button type="submit" class="btn-premium" style="padding:.4rem .85rem;font-size:.8rem;"><i class="bi bi-check-lg"></i> Crear Rol</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Editar Rol --}}
<div id="modal-editar-rol" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;justify-content:center;align-items:center;backdrop-filter:blur(2px);" onclick="if(event.target===this)cerrarModal('modal-editar-rol')">
    <div class="glass-card" style="width:90%;max-width:420px;padding:1.5rem;" onclick="event.stopPropagation()">
        <h3 style="margin:0 0 1rem;font-size:1rem;font-weight:700;color:var(--text-primary);">
            <i class="bi bi-pencil" style="color:var(--primary-color);"></i> Editar Rol
        </h3>
        <form method="POST" id="form-editar-rol" action="">
            @csrf @method('PUT')
            <div style="margin-bottom:.75rem;">
                <label style="font-size:.75rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.25rem;">Nombre del Rol *</label>
                <input type="text" name="nombre" id="editar-rol-nombre" class="form-input" required style="font-size:.85rem;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size:.75rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.25rem;">Código *</label>
                <input type="text" name="codigo" id="editar-rol-codigo" class="form-input" required style="font-size:.85rem;text-transform:uppercase;">
            </div>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" onclick="cerrarModal('modal-editar-rol')" class="btn-secondary" style="padding:.4rem .85rem;font-size:.8rem;">Cancelar</button>
                <button type="submit" class="btn-premium" style="padding:.4rem .85rem;font-size:.8rem;"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
