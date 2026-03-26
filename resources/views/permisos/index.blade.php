@extends('layouts.app')
@section('title', 'Permisos por Rol')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-key-fill" style="color:var(--accent-color)"></i> Permisos por Rol</h2>
            <p class="page-subheading">Configure qué módulos puede ver y gestionar cada rol del sistema</p>
        </div>
    </div>

    @include('partials._alerts')

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
</script>
@endsection
