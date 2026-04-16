@extends('layouts.app')
@section('title','Centros de Costo')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Centros de Costo</h2>
            <p class="page-subheading">Clientes donde SAEP presta servicios</p>
        </div>
        @if(auth()->user()->tieneAcceso('centros_costo', 'puede_crear'))
        <div style="display:flex;gap:.5rem">
            <button type="button" class="btn-secondary" onclick="document.getElementById('importModal').style.display='flex'">
                <i class="bi bi-upload"></i> Importar CSV
            </button>
            <a href="{{ route('centros-costo.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nuevo Centro
            </a>
        </div>
        @endif
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nombre</th><th>Tipo Nómina</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($centros as $centro)
            <tr>
                <td><code>{{ $centro->codigo }}</code></td>
                <td><strong>{{ $centro->nombre }}</strong></td>
                <td>
                    <span class="badge {{ $centro->razon_social === 'TRANSITORIO' ? 'badge-warning' : 'badge-info' }}">
                        {{ $centro->razon_social }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $centro->activo ? 'badge-success' : 'badge-danger' }}">
                        {{ $centro->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>
                    @if(auth()->user()->tieneAcceso('centros_costo', 'puede_editar'))
                    <a href="{{ route('centros-costo.edit', $centro) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    @endif
                    @if(auth()->user()->tieneAcceso('centros_costo', 'puede_eliminar'))
                    <form method="POST" action="{{ route('centros-costo.destroy', $centro) }}" style="display:inline"
                          onsubmit="return confirm('¿Desactivar este centro?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger" title="Desactivar">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay centros registrados. <a href="{{ route('centros-costo.create') }}">Crear el primero</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- Modal de Importación --}}
<div id="importModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:1rem">
    <div style="background:var(--surface-color);border:1px solid var(--surface-border);border-radius:14px;padding:1.5rem;max-width:520px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.2)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h3 style="margin:0;font-size:1.1rem"><i class="bi bi-upload" style="color:var(--accent-primary)"></i> Importar Centros de Costo</h3>
            <button type="button" onclick="document.getElementById('importModal').style.display='none'"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted)">&times;</button>
        </div>

        <div style="background:var(--bg-tertiary);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1rem;font-size:.85rem">
            <strong>Formato CSV (separador ;)</strong>
            <div style="margin-top:.35rem;color:var(--text-muted)">
                Columnas: <code>nombre</code> ; <code>razon_social</code> (NORMAL o TRANSITORIO)
            </div>
            <div style="margin-top:.35rem;color:var(--text-muted)">
                <i class="bi bi-info-circle"></i> Si el nombre ya existe, se actualizará el registro.
            </div>
        </div>

        <a href="{{ route('centros-costo.plantilla') }}" class="btn-secondary" style="margin-bottom:1rem;display:inline-flex">
            <i class="bi bi-download"></i> Descargar Plantilla CSV
        </a>

        <form method="POST" action="{{ route('centros-costo.importar') }}" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.5rem">Archivo CSV</label>
                <input type="file" name="archivo" accept=".csv,.txt" required class="form-control">
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <button type="button" class="btn-secondary" onclick="document.getElementById('importModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-premium"><i class="bi bi-upload"></i> Importar</button>
            </div>
        </form>
    </div>
</div>
@endsection
