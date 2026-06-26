@extends('layouts.app')

@section('title', 'Permisos por Rol')

@section('content')
@php
    $totalRoles = $roles->count();
    $totalModulos = $todosModulos->count();
    $totalGrupos = $todosModulos->pluck('grupo')->unique()->count();
    $totalUsuariosAsignados = $roles->sum('users_count');
    $permisosPlanos = collect($permisos)->flatMap(fn ($items) => $items);
    $totalCeldas = max(1, $totalRoles * $totalModulos);
    $totalConVer = $permisosPlanos->filter(fn ($perm) => (bool) ($perm->puede_ver ?? false))->count();
    $coberturaVer = (int) round(($totalConVer / $totalCeldas) * 100);
@endphp

<div class="page-container permissions-page" data-permissions-page>
    <div class="page-header permissions-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-key-fill permissions-heading-icon"></i>
                Permisos por Rol
            </h2>
            <p class="page-subheading">Administre roles, módulos y acciones disponibles dentro de la plataforma.</p>
        </div>
        <div class="permissions-header-actions">
            <button type="button" onclick="abrirModal('modal-crear-modulo')" class="btn-secondary permissions-action-btn">
                <i class="bi bi-grid-fill"></i>
                Nuevo Módulo
            </button>
            <button type="button" onclick="abrirModal('modal-crear-rol')" class="btn-premium permissions-action-btn">
                <i class="bi bi-plus-lg"></i>
                Nuevo Rol
            </button>
        </div>
    </div>

    @include('partials._alerts')

    <section class="permissions-summary-grid" aria-label="Resumen de permisos">
        <div class="permissions-summary-card">
            <div class="summary-icon primary"><i class="bi bi-people-fill"></i></div>
            <div>
                <strong>{{ $totalRoles }}</strong>
                <span>Roles activos</span>
            </div>
        </div>
        <div class="permissions-summary-card">
            <div class="summary-icon accent"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            <div>
                <strong>{{ $totalModulos }}</strong>
                <span>Módulos gestionados</span>
            </div>
        </div>
        <div class="permissions-summary-card">
            <div class="summary-icon success"><i class="bi bi-person-check-fill"></i></div>
            <div>
                <strong>{{ $totalUsuariosAsignados }}</strong>
                <span>Usuarios con rol</span>
            </div>
        </div>
        <div class="permissions-summary-card">
            <div class="summary-icon info"><i class="bi bi-eye-fill"></i></div>
            <div>
                <strong>{{ $coberturaVer }}%</strong>
                <span>Cobertura de lectura</span>
            </div>
        </div>
    </section>

    <section class="permissions-directory">
        <div class="glass-card permissions-directory-card">
            <div class="permissions-section-title">
                <div>
                    <h3><i class="bi bi-people-fill"></i> Roles del Sistema</h3>
                    <p>{{ $totalRoles }} perfiles de acceso configurados.</p>
                </div>
            </div>
            <div class="role-chip-list">
                @foreach($roles as $rol)
                    <article class="role-chip {{ $rol->esSuperAdmin() ? 'is-super' : '' }}" id="rol-card-{{ $rol->id }}">
                        <div class="role-chip-main">
                            <strong>{{ $rol->nombre }}</strong>
                            <span>{{ $rol->codigo }}</span>
                            <small>{{ $rol->users_count }} usuario{{ $rol->users_count !== 1 ? 's' : '' }}</small>
                        </div>
                        <div class="chip-actions">
                            <button
                                type="button"
                                onclick="abrirEditarRol({{ $rol->id }}, @js($rol->nombre), @js($rol->codigo))"
                                class="icon-action"
                                title="Editar rol"
                                aria-label="Editar rol {{ $rol->nombre }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @if(!$rol->esSuperAdmin())
                                <form method="POST" action="{{ route('roles.destroy', $rol) }}" onsubmit="return confirm(@js('¿Eliminar el rol «' . $rol->nombre . '»? Esta acción no se puede deshacer.'))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-action danger" title="Eliminar rol" aria-label="Eliminar rol {{ $rol->nombre }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @else
                                <span class="locked-pill" title="SUPER_ADMIN mantiene acceso total efectivo">Total</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="glass-card permissions-directory-card">
            <div class="permissions-section-title">
                <div>
                    <h3><i class="bi bi-grid-fill"></i> Módulos del Sistema</h3>
                    <p>{{ $totalModulos }} módulos en {{ $totalGrupos }} grupos operativos.</p>
                </div>
            </div>
            <div class="module-group-list">
                @foreach($todosModulos->groupBy('grupo') as $grupo => $mods)
                    <div class="module-group">
                        <div class="module-group-title">
                            <span>{{ $grupo }}</span>
                            <small>{{ $mods->count() }} módulo{{ $mods->count() !== 1 ? 's' : '' }}</small>
                        </div>
                        <div class="module-chip-list">
                            @foreach($mods as $mod)
                                <article class="module-chip">
                                    <i class="bi {{ $mod->icono }}"></i>
                                    <div>
                                        <strong>{{ $mod->nombre }}</strong>
                                        <span>{{ $mod->slug }}</span>
                                    </div>
                                    <div class="chip-actions">
                                        <button
                                            type="button"
                                            onclick="abrirEditarModulo({{ $mod->id }}, @js($mod->nombre), @js($mod->slug), @js($mod->grupo), @js($mod->icono), @js($mod->descripcion ?? ''))"
                                            class="icon-action"
                                            title="Editar módulo"
                                            aria-label="Editar módulo {{ $mod->nombre }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('modulos.destroy', $mod) }}" onsubmit="return confirm(@js('¿Desactivar el módulo «' . $mod->nombre . '»?'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-action danger" title="Desactivar módulo" aria-label="Desactivar módulo {{ $mod->nombre }}">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('permisos.update') }}" class="permissions-form">
        @csrf
        @method('PUT')

        <section class="glass-card permissions-toolbar" aria-label="Herramientas de matriz">
            <div class="toolbar-field toolbar-search">
                <label for="permission-search">Buscar módulo</label>
                <div class="toolbar-input-wrap">
                    <i class="bi bi-search"></i>
                    <input id="permission-search" type="search" class="form-input" placeholder="Nombre, slug, descripción o grupo">
                </div>
            </div>
            <div class="toolbar-field">
                <label for="permission-group-filter">Grupo</label>
                <select id="permission-group-filter" class="form-input">
                    <option value="">Todos los grupos</option>
                    @foreach($grupos as $grupo)
                        <option value="{{ \Illuminate\Support\Str::lower($grupo) }}">{{ $grupo }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" class="btn-secondary toolbar-clear-button" data-clear-permission-filters>
                <i class="bi bi-x-circle"></i>
                Limpiar
            </button>
            <div class="permissions-legend" aria-label="Leyenda de permisos">
                <span><span class="perm-icon ver">V</span> Ver</span>
                <span><span class="perm-icon crear">C</span> Crear</span>
                <span><span class="perm-icon editar">E</span> Editar</span>
                <span><span class="perm-icon eliminar">X</span> Eliminar</span>
            </div>
            <div class="toolbar-count">
                <strong data-visible-module-count>{{ $totalModulos }}</strong>
                <span>módulos visibles</span>
            </div>
        </section>

        <div class="permissions-empty-state" data-permissions-empty hidden>
            <i class="bi bi-search"></i>
            <strong>Sin módulos visibles</strong>
            <span>Ajuste la búsqueda o el filtro de grupo.</span>
        </div>

        @foreach($modulos as $grupo => $modulosGrupo)
            <section class="glass-card permission-group-card" data-permission-group="{{ \Illuminate\Support\Str::lower($grupo) }}">
                <header class="permission-group-header">
                    <div>
                        <h3><i class="bi bi-folder-fill"></i> {{ $grupo }}</h3>
                        <span>{{ $modulosGrupo->count() }} módulo{{ $modulosGrupo->count() !== 1 ? 's' : '' }}</span>
                    </div>
                    <div class="group-actions">
                        <button type="button" class="matrix-action-button" data-permission-action="group-all">
                            <i class="bi bi-check2-square"></i>
                            Todo el grupo
                        </button>
                        <button type="button" class="matrix-action-button" data-permission-action="group-clear">
                            <i class="bi bi-square"></i>
                            Limpiar grupo
                        </button>
                    </div>
                </header>

                <div class="glass-table-container permission-table-wrap">
                    <table class="glass-table permission-table">
                        <thead>
                            <tr>
                                <th class="module-column">Módulo</th>
                                @foreach($roles as $rol)
                                    <th class="role-column {{ $rol->esSuperAdmin() ? 'is-super' : '' }}">
                                        <div class="role-column-title">{{ $rol->nombre }}</div>
                                        <div class="role-column-code">{{ $rol->codigo }}</div>
                                        @if($rol->esSuperAdmin())
                                            <div class="role-column-note">Acceso total efectivo</div>
                                        @else
                                            <div class="role-column-actions">
                                                <button type="button" data-permission-action="role-all" data-role-id="{{ $rol->id }}">Todo</button>
                                                <button type="button" data-permission-action="role-clear" data-role-id="{{ $rol->id }}">Limpiar</button>
                                            </div>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($modulosGrupo as $modulo)
                                <tr
                                    class="permission-row"
                                    data-module-id="{{ $modulo->id }}"
                                    data-group="{{ \Illuminate\Support\Str::lower($grupo) }}"
                                    data-search="{{ \Illuminate\Support\Str::lower($modulo->nombre . ' ' . $modulo->slug . ' ' . ($modulo->descripcion ?? '') . ' ' . $grupo) }}">
                                    <td class="module-cell">
                                        <div class="module-cell-content">
                                            <i class="bi {{ $modulo->icono }}"></i>
                                            <div>
                                                <strong>{{ $modulo->nombre }}</strong>
                                                <span>{{ $modulo->slug }}</span>
                                                @if($modulo->descripcion)
                                                    <small>{{ $modulo->descripcion }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="row-actions">
                                            <button type="button" data-permission-action="row-all" data-module-id="{{ $modulo->id }}">Todo</button>
                                            <button type="button" data-permission-action="row-clear" data-module-id="{{ $modulo->id }}">Limpiar</button>
                                        </div>
                                    </td>

                                    @foreach($roles as $rol)
                                        @php
                                            $key = "{$rol->id}_{$modulo->id}";
                                            $p = $permisos[$rol->id][$modulo->id] ?? null;
                                            $locked = $rol->esSuperAdmin();
                                            $ver = $locked || ($p ? (bool) $p->puede_ver : false);
                                            $crear = $locked || ($p ? (bool) $p->puede_crear : false);
                                            $editar = $locked || ($p ? (bool) $p->puede_editar : false);
                                            $eliminar = $locked || ($p ? (bool) $p->puede_eliminar : false);
                                        @endphp
                                        <td class="permission-cell {{ $locked ? 'is-locked' : '' }}">
                                            <div class="permission-checks" aria-label="Permisos de {{ $rol->nombre }} en {{ $modulo->nombre }}">
                                                <label class="perm-check perm-ver {{ $ver ? 'active' : '' }} {{ $locked ? 'locked' : '' }}" title="{{ $locked ? 'Acceso total efectivo de SUPER_ADMIN' : 'Ver' }}">
                                                    <input type="checkbox" name="permisos[{{ $key }}][ver]" value="1" data-role-id="{{ $rol->id }}" data-module-id="{{ $modulo->id }}" {{ $ver ? 'checked' : '' }} {{ $locked ? 'disabled' : '' }}>
                                                    <span>V</span>
                                                </label>
                                                <label class="perm-check perm-crear {{ $crear ? 'active' : '' }} {{ $locked ? 'locked' : '' }}" title="{{ $locked ? 'Acceso total efectivo de SUPER_ADMIN' : 'Crear' }}">
                                                    <input type="checkbox" name="permisos[{{ $key }}][crear]" value="1" data-role-id="{{ $rol->id }}" data-module-id="{{ $modulo->id }}" {{ $crear ? 'checked' : '' }} {{ $locked ? 'disabled' : '' }}>
                                                    <span>C</span>
                                                </label>
                                                <label class="perm-check perm-editar {{ $editar ? 'active' : '' }} {{ $locked ? 'locked' : '' }}" title="{{ $locked ? 'Acceso total efectivo de SUPER_ADMIN' : 'Editar' }}">
                                                    <input type="checkbox" name="permisos[{{ $key }}][editar]" value="1" data-role-id="{{ $rol->id }}" data-module-id="{{ $modulo->id }}" {{ $editar ? 'checked' : '' }} {{ $locked ? 'disabled' : '' }}>
                                                    <span>E</span>
                                                </label>
                                                <label class="perm-check perm-eliminar {{ $eliminar ? 'active' : '' }} {{ $locked ? 'locked' : '' }}" title="{{ $locked ? 'Acceso total efectivo de SUPER_ADMIN' : 'Eliminar' }}">
                                                    <input type="checkbox" name="permisos[{{ $key }}][eliminar]" value="1" data-role-id="{{ $rol->id }}" data-module-id="{{ $modulo->id }}" {{ $eliminar ? 'checked' : '' }} {{ $locked ? 'disabled' : '' }}>
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
            </section>
        @endforeach

        <div class="permissions-savebar">
            <div>
                <strong>Matriz de permisos</strong>
                <span data-dirty-badge>Sin cambios pendientes</span>
            </div>
            <button type="submit" class="btn-premium" data-save-button>
                <i class="bi bi-floppy-fill"></i>
                Guardar permisos
            </button>
        </div>
    </form>
</div>

{{-- Modal Crear Rol --}}
<div id="modal-crear-rol" class="permission-modal" onclick="if(event.target===this)cerrarModal('modal-crear-rol')">
    <div class="glass-card permission-modal-card" onclick="event.stopPropagation()">
        <h3><i class="bi bi-plus-circle"></i> Crear Nuevo Rol</h3>
        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            <div class="form-group">
                <label>Nombre del Rol *</label>
                <input type="text" name="nombre" class="form-input" required placeholder="Ej: Analista de Datos">
            </div>
            <div class="form-group">
                <label>Código (opcional)</label>
                <input type="text" name="codigo" class="form-input input-uppercase" placeholder="Se genera automáticamente si se deja vacío">
                <span class="field-hint">Identificador único, ej: ANALISTA_DATOS.</span>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal('modal-crear-rol')" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-check-lg"></i> Crear Rol</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Editar Rol --}}
<div id="modal-editar-rol" class="permission-modal" onclick="if(event.target===this)cerrarModal('modal-editar-rol')">
    <div class="glass-card permission-modal-card" onclick="event.stopPropagation()">
        <h3><i class="bi bi-pencil"></i> Editar Rol</h3>
        <form method="POST" id="form-editar-rol" action="">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Nombre del Rol *</label>
                <input type="text" name="nombre" id="editar-rol-nombre" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" id="editar-rol-codigo" class="form-input input-uppercase" required>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal('modal-editar-rol')" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Crear Módulo --}}
<div id="modal-crear-modulo" class="permission-modal" onclick="if(event.target===this)cerrarModal('modal-crear-modulo')">
    <div class="glass-card permission-modal-card modal-wide" onclick="event.stopPropagation()">
        <h3><i class="bi bi-grid-fill"></i> Crear Nuevo Módulo</h3>
        <form method="POST" action="{{ route('modulos.store') }}">
            @csrf
            <div class="modal-grid">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-input" required placeholder="Ej: Reportes SST">
                </div>
                <div class="form-group">
                    <label>Slug (opcional)</label>
                    <input type="text" name="slug" class="form-input" placeholder="Auto-generado">
                </div>
            </div>
            <div class="modal-grid">
                <div class="form-group">
                    <label>Grupo *</label>
                    <input type="text" name="grupo" class="form-input" required list="grupos-list" placeholder="Ej: Prevención SST">
                    <datalist id="grupos-list">
                        @foreach($grupos as $g)
                            <option value="{{ $g }}">
                        @endforeach
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Icono Bootstrap</label>
                    <input type="text" name="icono" class="form-input" placeholder="bi-grid">
                </div>
            </div>
            <div class="form-group">
                <label>Descripción (opcional)</label>
                <input type="text" name="descripcion" class="form-input" placeholder="Breve descripción del módulo">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal('modal-crear-modulo')" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-check-lg"></i> Crear Módulo</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Editar Módulo --}}
<div id="modal-editar-modulo" class="permission-modal" onclick="if(event.target===this)cerrarModal('modal-editar-modulo')">
    <div class="glass-card permission-modal-card modal-wide" onclick="event.stopPropagation()">
        <h3><i class="bi bi-pencil"></i> Editar Módulo</h3>
        <form method="POST" id="form-editar-modulo" action="">
            @csrf
            @method('PUT')
            <div class="modal-grid">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="editar-mod-nombre" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Slug *</label>
                    <input type="text" name="slug" id="editar-mod-slug" class="form-input" required>
                </div>
            </div>
            <div class="modal-grid">
                <div class="form-group">
                    <label>Grupo *</label>
                    <input type="text" name="grupo" id="editar-mod-grupo" class="form-input" required list="grupos-list">
                </div>
                <div class="form-group">
                    <label>Icono Bootstrap</label>
                    <input type="text" name="icono" id="editar-mod-icono" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <input type="text" name="descripcion" id="editar-mod-descripcion" class="form-input">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal('modal-editar-modulo')" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
.permissions-page {
    --permission-sticky-bg: var(--surface-color);
}

.permissions-heading-icon { color: var(--accent-color); }
.permissions-header-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.permissions-action-btn { min-height: 40px; padding: .55rem 1rem; font-size: .84rem; }

.permissions-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .85rem;
    margin-bottom: 1rem;
}
.permissions-summary-card {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: .95rem 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    background: var(--surface-color);
    box-shadow: var(--glass-shadow);
}
.permissions-summary-card strong {
    display: block;
    font-size: 1.3rem;
    line-height: 1;
    color: var(--text-primary);
}
.permissions-summary-card span {
    display: block;
    margin-top: .22rem;
    font-size: .76rem;
    color: var(--text-muted);
}
.summary-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1.1rem;
    flex: 0 0 auto;
}
.summary-icon.primary { background: rgba(15, 27, 76, .1); color: var(--primary-color); }
.summary-icon.accent { background: rgba(249, 115, 22, .13); color: var(--accent-color); }
.summary-icon.success { background: rgba(16, 185, 129, .14); color: #059669; }
.summary-icon.info { background: rgba(59, 130, 246, .13); color: #2563eb; }

.permissions-directory {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: start;
}
.permissions-directory-card { padding: 1rem; }
.permissions-section-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .85rem;
}
.permissions-section-title h3 {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin: 0;
    font-size: .92rem;
    color: var(--text-primary);
}
.permissions-section-title p {
    margin: .25rem 0 0;
    color: var(--text-muted);
    font-size: .76rem;
}

.role-chip-list,
.module-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
}
.role-chip,
.module-chip {
    display: flex;
    align-items: center;
    gap: .65rem;
    min-width: 0;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--card-bg);
}
.role-chip {
    justify-content: space-between;
    padding: .55rem .65rem .55rem .8rem;
    width: min(100%, 240px);
}
.role-chip.is-super {
    border-color: rgba(249, 115, 22, .4);
    background: rgba(249, 115, 22, .08);
}
.role-chip-main strong,
.module-chip strong {
    display: block;
    color: var(--text-primary);
    font-size: .8rem;
    line-height: 1.2;
}
.role-chip-main span,
.module-chip span,
.role-chip-main small {
    display: block;
    color: var(--text-muted);
    font-size: .66rem;
    line-height: 1.35;
}
.chip-actions {
    display: inline-flex;
    align-items: center;
    gap: .22rem;
    flex: 0 0 auto;
}
.chip-actions form { display: inline-flex; }
.icon-action {
    width: 28px;
    height: 28px;
    display: inline-grid;
    place-items: center;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
}
.icon-action:hover {
    background: rgba(107, 114, 128, .1);
    color: var(--primary-color);
}
.icon-action.danger:hover {
    background: rgba(239, 68, 68, .1);
    color: #dc2626;
}
.locked-pill {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 .45rem;
    border-radius: 999px;
    font-size: .62rem;
    font-weight: 800;
    color: #9a3412;
    background: rgba(249, 115, 22, .16);
}

.module-group-list {
    display: flex;
    flex-direction: column;
    gap: .75rem;
}
.module-group + .module-group { margin-top: 0; }
.module-group {
    padding: .78rem;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: linear-gradient(180deg, rgba(249, 250, 251, .9), rgba(255, 255, 255, .96));
}
body.dark-mode .module-group {
    background: rgba(17, 24, 39, .28);
}
.module-group-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .65rem;
    padding-bottom: .52rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
}
.module-group-title small {
    flex: 0 0 auto;
    padding: .18rem .45rem;
    border-radius: 999px;
    background: rgba(107, 114, 128, .12);
    color: var(--text-muted);
    font-size: .62rem;
    font-weight: 800;
    text-transform: none;
}
.module-chip-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: .55rem;
}
.module-chip {
    padding: .48rem .55rem;
    width: 100%;
    max-width: none;
    min-height: 44px;
    justify-content: space-between;
}
.module-chip > i {
    color: var(--accent-color);
    font-size: .9rem;
    flex: 0 0 auto;
}
.module-chip > div {
    flex: 1 1 auto;
    min-width: 0;
}
.module-chip span {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.permissions-toolbar {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(170px, 220px) auto auto auto;
    gap: .85rem;
    align-items: end;
    padding: 1rem;
    margin-bottom: 1rem;
    position: sticky;
    top: .75rem;
    z-index: 20;
}
.toolbar-field label {
    display: block;
    margin-bottom: .32rem;
    color: var(--text-muted);
    font-size: .7rem;
    font-weight: 800;
    text-transform: uppercase;
}
.toolbar-input-wrap { position: relative; }
.toolbar-input-wrap i {
    position: absolute;
    top: 50%;
    left: .85rem;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}
.toolbar-input-wrap .form-input { padding-left: 2.4rem; }
.toolbar-clear-button {
    min-height: 42px;
    padding: .58rem .85rem;
    font-size: .78rem;
    white-space: nowrap;
}
.permissions-legend {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
    align-items: center;
    padding-bottom: .15rem;
    color: var(--text-muted);
    font-size: .75rem;
}
.toolbar-count {
    min-width: 120px;
    padding: .65rem .75rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    text-align: center;
    background: var(--card-bg);
}
.toolbar-count strong {
    display: block;
    color: var(--text-primary);
    line-height: 1;
}
.toolbar-count span {
    color: var(--text-muted);
    font-size: .68rem;
}

.permissions-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    min-height: 150px;
    margin-bottom: 1rem;
    border: 1px dashed var(--border-color);
    border-radius: 12px;
    color: var(--text-muted);
    background: var(--surface-color);
}
.permissions-empty-state i { font-size: 1.4rem; color: var(--accent-color); }
.permissions-empty-state strong { color: var(--text-primary); }
.permissions-empty-state[hidden] { display: none; }

.permission-group-card {
    margin-bottom: 1rem;
    padding: 0;
    overflow: hidden;
}
.permission-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .85rem;
    padding: .82rem 1rem;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    color: #fff;
}
.permission-group-header h3 {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin: 0;
    font-size: .9rem;
}
.permission-group-header span {
    display: block;
    margin-top: .15rem;
    opacity: .72;
    font-size: .68rem;
    text-transform: uppercase;
}
.group-actions {
    display: flex;
    gap: .4rem;
    flex-wrap: wrap;
}
.matrix-action-button {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    min-height: 30px;
    padding: .35rem .6rem;
    border: 1px solid rgba(255, 255, 255, .28);
    border-radius: 8px;
    background: rgba(255, 255, 255, .12);
    color: #fff;
    font-size: .7rem;
    font-weight: 800;
    cursor: pointer;
}
.matrix-action-button:hover { background: rgba(255, 255, 255, .2); }

.permission-table-wrap {
    max-height: 620px;
    margin: 0;
    border-radius: 0;
    overflow: auto;
}
.permission-table {
    min-width: {{ 260 + (max(1, $totalRoles) * 138) }}px;
    margin: 0;
    font-size: .78rem;
}
.permission-table th,
.permission-table td {
    padding: .72rem .75rem;
    vertical-align: middle;
}
.permission-table thead th {
    position: sticky;
    top: 0;
    z-index: 7;
    background: var(--permission-sticky-bg);
}
.permission-table .module-column,
.permission-table .module-cell {
    position: sticky;
    left: 0;
    z-index: 8;
    width: 260px;
    min-width: 260px;
    background: var(--permission-sticky-bg);
    box-shadow: 1px 0 0 var(--border-color);
}
.permission-table thead .module-column { z-index: 10; }
.role-column {
    min-width: 138px;
    text-align: center;
}
.role-column.is-super {
    background: rgba(249, 115, 22, .08);
}
.role-column-title {
    color: var(--text-primary);
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
}
.role-column-code {
    margin-top: .12rem;
    color: var(--text-muted);
    font-size: .62rem;
    font-weight: 600;
}
.role-column-note {
    margin-top: .35rem;
    color: #9a3412;
    font-size: .62rem;
    font-weight: 800;
}
.role-column-actions {
    display: flex;
    justify-content: center;
    gap: .25rem;
    margin-top: .45rem;
}
.role-column-actions button,
.row-actions button {
    border: 1px solid var(--border-color);
    border-radius: 7px;
    background: var(--card-bg);
    color: var(--text-muted);
    font-size: .64rem;
    font-weight: 800;
    cursor: pointer;
    padding: .22rem .38rem;
}
.role-column-actions button:hover,
.row-actions button:hover {
    border-color: var(--accent-color);
    color: var(--primary-color);
}
.module-cell-content {
    display: flex;
    align-items: flex-start;
    gap: .55rem;
}
.module-cell-content > i {
    width: 20px;
    flex: 0 0 auto;
    color: var(--accent-color);
    text-align: center;
}
.module-cell-content strong {
    display: block;
    color: var(--text-primary);
    line-height: 1.2;
}
.module-cell-content span,
.module-cell-content small {
    display: block;
    color: var(--text-muted);
    line-height: 1.25;
}
.module-cell-content span {
    font-size: .68rem;
    font-weight: 700;
}
.module-cell-content small {
    margin-top: .14rem;
    font-size: .66rem;
}
.row-actions {
    display: flex;
    gap: .32rem;
    margin-top: .5rem;
    padding-left: 1.7rem;
}
.permission-cell { text-align: center; }
.permission-cell.is-locked {
    background: rgba(249, 115, 22, .05);
}
.permission-checks {
    display: inline-flex;
    justify-content: center;
    gap: .24rem;
    flex-wrap: wrap;
}
.perm-icon,
.perm-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    font-size: .58rem;
    font-weight: 900;
}
.perm-icon {
    color: #fff;
    width: 22px;
    height: 22px;
}
.perm-icon.ver,
.perm-check.perm-ver.active { background: #2563eb; border-color: #2563eb; }
.perm-icon.crear,
.perm-check.perm-crear.active { background: #16a34a; border-color: #16a34a; }
.perm-icon.editar,
.perm-check.perm-editar.active { background: #f97316; border-color: #f97316; }
.perm-icon.eliminar,
.perm-check.perm-eliminar.active { background: #dc2626; border-color: #dc2626; }
.perm-check {
    position: relative;
    border: 1.5px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-muted);
    cursor: pointer;
    transition: transform .15s ease, border-color .15s ease, background .15s ease;
}
.perm-check input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.perm-check span { line-height: 1; }
.perm-check:hover {
    transform: translateY(-1px);
    border-color: var(--accent-color);
}
.perm-check.active { color: #fff; }
.perm-check.locked {
    cursor: not-allowed;
    opacity: .78;
}
.perm-check.locked:hover {
    transform: none;
    border-color: #f97316;
}

.permissions-savebar {
    position: sticky;
    bottom: .75rem;
    z-index: 15;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 1rem 0 2rem;
    padding: .85rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 14px 32px rgba(15, 27, 76, .12);
    backdrop-filter: blur(10px);
}
body.dark-mode .permissions-savebar {
    background: rgba(31, 41, 55, .94);
}
.permissions-savebar strong {
    display: block;
    color: var(--text-primary);
    font-size: .85rem;
}
.permissions-savebar span {
    display: block;
    margin-top: .16rem;
    color: var(--text-muted);
    font-size: .72rem;
}
.permissions-savebar.is-dirty span { color: #d97706; font-weight: 800; }

.permission-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(0, 0, 0, .5);
    backdrop-filter: blur(2px);
}
.permission-modal-card {
    width: min(100%, 420px);
    max-height: min(92vh, 720px);
    overflow-y: auto;
    padding: 1.25rem;
}
.permission-modal-card.modal-wide { width: min(100%, 520px); }
.permission-modal-card h3 {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin: 0 0 1rem;
    color: var(--text-primary);
    font-size: 1rem;
}
.permission-modal-card h3 i { color: var(--accent-color); }
.permission-modal-card .form-group { margin-bottom: .85rem; }
.permission-modal-card label {
    font-size: .75rem;
    font-weight: 700;
    color: var(--text-muted);
}
.field-hint {
    display: block;
    margin-top: .25rem;
    color: var(--text-muted);
    font-size: .68rem;
}
.input-uppercase { text-transform: uppercase; }
.modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    margin-top: 1rem;
}
.modal-actions .btn-secondary,
.modal-actions .btn-premium {
    min-height: 38px;
    padding: .45rem .85rem;
    font-size: .8rem;
}

@media (max-width: 1180px) {
    .permissions-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .permissions-directory { grid-template-columns: 1fr; }
    .permissions-toolbar { grid-template-columns: 1fr 220px; }
    .permissions-legend,
    .toolbar-count { grid-column: span 1; }
}
@media (max-width: 760px) {
    .permissions-summary-grid,
    .permissions-toolbar {
        grid-template-columns: 1fr;
    }
    .permissions-header-actions {
        width: 100%;
    }
    .permissions-action-btn {
        flex: 1;
        justify-content: center;
    }
    .permission-group-header {
        align-items: flex-start;
        flex-direction: column;
    }
    .permissions-savebar {
        align-items: stretch;
        flex-direction: column;
    }
    .permissions-savebar .btn-premium {
        justify-content: center;
        width: 100%;
    }
    .modal-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
const roleUpdateTemplate = @js(route('roles.update', ['rol' => '__ID__']));
const moduleUpdateTemplate = @js(route('modulos.update', ['modulo' => '__ID__']));

function routeFromTemplate(template, id) {
    return template.replace('__ID__', id);
}

function abrirModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
}

function abrirEditarRol(id, nombre, codigo) {
    document.getElementById('editar-rol-nombre').value = nombre;
    document.getElementById('editar-rol-codigo').value = codigo;
    document.getElementById('form-editar-rol').action = routeFromTemplate(roleUpdateTemplate, id);
    abrirModal('modal-editar-rol');
}

function abrirEditarModulo(id, nombre, slug, grupo, icono, descripcion) {
    document.getElementById('editar-mod-nombre').value = nombre;
    document.getElementById('editar-mod-slug').value = slug;
    document.getElementById('editar-mod-grupo').value = grupo;
    document.getElementById('editar-mod-icono').value = icono;
    document.getElementById('editar-mod-descripcion').value = descripcion;
    document.getElementById('form-editar-modulo').action = routeFromTemplate(moduleUpdateTemplate, id);
    abrirModal('modal-editar-modulo');
}

document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-permissions-page]');
    if (!page) return;

    const searchInput = document.getElementById('permission-search');
    const groupFilter = document.getElementById('permission-group-filter');
    const clearFilters = page.querySelector('[data-clear-permission-filters]');
    const emptyState = page.querySelector('[data-permissions-empty]');
    const visibleCounter = page.querySelector('[data-visible-module-count]');
    const savebar = page.querySelector('.permissions-savebar');
    const dirtyBadge = page.querySelector('[data-dirty-badge]');

    const normalize = value => (value || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const markDirty = () => {
        savebar?.classList.add('is-dirty');
        if (dirtyBadge) {
            dirtyBadge.textContent = 'Cambios pendientes por guardar';
        }
    };

    const syncCheck = input => {
        input.closest('.perm-check')?.classList.toggle('active', input.checked);
    };

    const setInputs = (inputs, checked) => {
        let changed = false;
        inputs.forEach(input => {
            if (input.disabled) return;
            input.checked = checked;
            syncCheck(input);
            changed = true;
        });
        if (changed) markDirty();
    };

    page.querySelectorAll('.perm-check input').forEach(syncCheck);

    page.addEventListener('change', event => {
        if (event.target.matches('.perm-check input')) {
            syncCheck(event.target);
            markDirty();
        }
    });

    page.addEventListener('click', event => {
        const button = event.target.closest('[data-permission-action]');
        if (!button) return;

        const action = button.dataset.permissionAction;
        let inputs = [];

        if (action === 'role-all' || action === 'role-clear') {
            inputs = Array.from(page.querySelectorAll(`input[data-role-id="${button.dataset.roleId}"]`));
            setInputs(inputs, action === 'role-all');
            return;
        }

        if (action === 'row-all' || action === 'row-clear') {
            inputs = Array.from(page.querySelectorAll(`input[data-module-id="${button.dataset.moduleId}"]`));
            setInputs(inputs, action === 'row-all');
            return;
        }

        if (action === 'group-all' || action === 'group-clear') {
            const groupCard = button.closest('[data-permission-group]');
            inputs = Array.from(groupCard.querySelectorAll('.permission-row:not([hidden]) input'));
            setInputs(inputs, action === 'group-all');
        }
    });

    const applyFilters = () => {
        const query = normalize(searchInput?.value);
        const group = normalize(groupFilter?.value);
        let visibleRows = 0;

        page.querySelectorAll('[data-permission-group]').forEach(groupCard => {
            let visibleInGroup = 0;
            const groupMatches = !group || normalize(groupCard.dataset.permissionGroup) === group;

            groupCard.querySelectorAll('.permission-row').forEach(row => {
                const matchesSearch = !query || normalize(row.dataset.search).includes(query);
                const matchesGroup = groupMatches;
                const visible = matchesSearch && matchesGroup;
                row.hidden = !visible;
                if (visible) {
                    visibleInGroup += 1;
                    visibleRows += 1;
                }
            });

            groupCard.hidden = visibleInGroup === 0;
        });

        if (visibleCounter) {
            visibleCounter.textContent = visibleRows;
        }
        if (emptyState) {
            emptyState.hidden = visibleRows !== 0;
        }
    };

    searchInput?.addEventListener('input', applyFilters);
    groupFilter?.addEventListener('change', applyFilters);
    clearFilters?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (groupFilter) groupFilter.value = '';
        applyFilters();
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.permission-modal').forEach(modal => {
            modal.style.display = 'none';
        });
    });
});
</script>
@endpush
