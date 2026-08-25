@extends('layouts.app')
@section('title', 'Contratación — Postulantes')

@section('content')
<div class="page-container">

    @include('partials._alerts')
    @php
        $puedeCrear = auth()->user()->tieneAcceso('contratacion', 'puede_crear');
        $puedeEditar = auth()->user()->tieneAcceso('contratacion', 'puede_editar');
        $puedeEliminar = auth()->user()->tieneAcceso('contratacion', 'puede_eliminar');
    @endphp

    <!-- Header -->
    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-person-badge-fill" style="color:#0ea5e9"></i> Contratación
            </h2>
            <p class="page-subheading">Gestión de postulantes y documentos para proceso de contratación</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="{{ route('contratacion-publico.inicio') }}" target="_blank" class="btn-ghost">
                <i class="bi bi-box-arrow-up-right"></i> Formulario Público
            </a>
            @if($puedeEditar)
            <a href="{{ route('contratacion.exportar.excel') }}" class="btn-ghost">
                <i class="bi bi-file-earmark-excel-fill" style="color:#22c55e"></i> Exportar Excel
            </a>
            <a href="{{ route('contratacion.configuracion') }}" class="btn-ghost">
                <i class="bi bi-gear-fill"></i> Configuración
            </a>
            @endif
            @if($puedeCrear)
            <a href="{{ route('contratacion.crear') }}" class="btn-premium">
                <i class="bi bi-person-plus-fill"></i> Ingresar manual
            </a>
            @endif
        </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem;">
        @php
        $statsCards = [
            ['label'=>'Total',        'value'=>$stats['total'],       'icon'=>'bi-people-fill',       'color'=>'#0f1b4c'],
            ['label'=>'Pendientes',   'value'=>$stats['pendiente'],   'icon'=>'bi-hourglass-split',   'color'=>'#f59e0b'],
            ['label'=>'En Revisión',  'value'=>$stats['en_revision'], 'icon'=>'bi-search',            'color'=>'#3b82f6'],
            ['label'=>'Aprobados',    'value'=>$stats['aprobado'],    'icon'=>'bi-check-circle-fill', 'color'=>'#22c55e'],
            ['label'=>'Rechazados',   'value'=>$stats['rechazado'],   'icon'=>'bi-x-circle-fill',     'color'=>'#ef4444'],
        ];
        @endphp
        @foreach($statsCards as $card)
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:{{ $card['color'] }}20;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi {{ $card['icon'] }}" style="font-size:1.15rem;color:{{ $card['color'] }}"></i>
            </div>
            <div>
                <p style="font-size:1.4rem;font-weight:800;margin:0;color:var(--text-main);">{{ $card['value'] }}</p>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;">{{ $card['label'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Filtros -->
    @php
        $activeFilters = collect(['buscar', 'estado'])->filter(fn($k) => request($k) !== null && request($k) !== '')->count();
    @endphp
    <div class="glass-card" style="padding:1rem 1.25rem;margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('contratacion.index') }}">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
                <i class="bi bi-funnel-fill" style="color:var(--accent-color)"></i>
                <h3 style="font-size:.85rem;font-weight:600;margin:0;color:var(--text-primary)">Filtros</h3>
                @if($activeFilters > 0)
                    <span style="background:var(--accent-color);color:#fff;font-size:.68rem;padding:.1rem .45rem;border-radius:10px;font-weight:700">{{ $activeFilters }} activo{{ $activeFilters > 1 ? 's' : '' }}</span>
                @endif
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-bottom:.75rem;">
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem;">Buscar</label>
                    <input type="text" name="buscar" class="form-input"
                        value="{{ request('buscar') }}"
                        placeholder="Folio, nombre, RUT o correo...">
                </div>
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem;">Estado</label>
                    <select name="estado" class="form-input" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="pendiente"   {{ request('estado') === 'pendiente'   ? 'selected' : '' }}>Pendiente</option>
                        <option value="en_revision" {{ request('estado') === 'en_revision' ? 'selected' : '' }}>En Revisión</option>
                        <option value="aprobado"    {{ request('estado') === 'aprobado'    ? 'selected' : '' }}>Aprobado</option>
                        <option value="rechazado"   {{ request('estado') === 'rechazado'   ? 'selected' : '' }}>Rechazado</option>
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end;gap:.5rem;">
                    <button type="submit" class="btn-secondary" style="flex:1;">
                        <i class="bi bi-funnel-fill"></i> Filtrar
                    </button>
                </div>
            </div>
            @if($activeFilters > 0)
                <div>
                    <a href="{{ route('contratacion.index') }}" style="font-size:.78rem;color:#ef4444;text-decoration:none;display:inline-flex;align-items:center;gap:.25rem;">
                        <i class="bi bi-x-circle"></i> Limpiar filtros
                    </a>
                </div>
            @endif
        </form>
    </div>

    <!-- Tabla -->
    <div class="glass-card" style="padding:0;overflow:hidden;">
        @if($postulantes->isEmpty())
        <div style="text-align:center;padding:3rem 2rem;color:var(--text-muted);">
            <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.4;"></i>
            No hay postulantes que coincidan con los filtros.
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="data-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Correo</th>
                        <th style="text-align:center;">Docs</th>
                        <th style="text-align:center;" title="Sincronización con SharePoint">SP</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($postulantes as $p)
                    <tr>
                        <td>
                            <code style="font-weight:700;font-size:.8rem;color:#0369a1;">{{ $p->folio }}</code>
                            @if($p->es_repostulacion)
                            <div style="margin-top:.35rem;"><span style="padding:.16rem .45rem;border-radius:999px;font-size:.66rem;font-weight:800;background:#ede9fe;color:#6d28d9;">Repostulación</span></div>
                            @elseif($p->es_vigente)
                            <div style="margin-top:.35rem;"><span style="padding:.16rem .45rem;border-radius:999px;font-size:.66rem;font-weight:800;background:#dcfce7;color:#166534;">Vigente</span></div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight:600;">{{ $p->nombre }}</div>
                            @if($p->google_name && $p->google_name !== $p->nombre)
                            <div style="font-size:.75rem;color:var(--text-muted);">{{ $p->google_name }}</div>
                            @endif
                        </td>
                        <td style="font-family:monospace;font-size:.85rem;">{{ $p->rut }}</td>
                        <td style="font-size:.85rem;">{{ $p->email }}</td>
                        <td style="text-align:center;">
                            @php $subidos = $p->documentosObligatoriosSubidos(); @endphp
                            <span style="
                                padding:.25rem .6rem;border-radius:6px;font-size:.75rem;font-weight:700;
                                background:{{ $p->documentosCompletos() ? '#dcfce7' : '#fefce8' }};
                                color:{{ $p->documentosCompletos() ? '#166534' : '#854d0e' }};
                            ">{{ $subidos }}/4</span>
                        </td>
                        <td style="text-align:center;">
                            @php $us = $p->ultimoSync; @endphp
                            @if(!$us)
                                <span title="Sin intentos registrados" style="padding:.2rem .55rem;border-radius:6px;font-size:.7rem;font-weight:700;background:#f1f5f9;color:#64748b;">—</span>
                            @elseif($us->status === 'exitoso')
                                <span title="Última sync: {{ $us->finished_at?->format('d/m/Y H:i') }} (intento {{ $us->intento }})" style="padding:.2rem .55rem;border-radius:6px;font-size:.7rem;font-weight:700;background:#dcfce7;color:#166534;">
                                    <i class="bi bi-check-circle-fill"></i> OK
                                </span>
                            @elseif($us->status === 'en_proceso')
                                <span title="En proceso desde {{ $us->started_at?->format('d/m/Y H:i') }}" style="padding:.2rem .55rem;border-radius:6px;font-size:.7rem;font-weight:700;background:#fef9c3;color:#854d0e;">
                                    <i class="bi bi-hourglass-split"></i>
                                </span>
                            @else
                                <span title="Falló: {{ Str::limit($us->error_mensaje, 120) }}" style="padding:.2rem .55rem;border-radius:6px;font-size:.7rem;font-weight:700;background:#fee2e2;color:#991b1b;">
                                    <i class="bi bi-exclamation-triangle-fill"></i> ERROR
                                </span>
                            @endif
                        </td>
                        <td>
                            <span style="
                                padding:.3rem .7rem;border-radius:8px;font-size:.75rem;font-weight:700;
                                background:{{ $p->estado_color }}20;color:{{ $p->estado_color }};
                            ">{{ $p->estado_label }}</span>
                        </td>
                        <td style="font-size:.82rem;color:var(--text-muted);">
                            {{ $p->created_at->format('d/m/Y') }}
                        </td>
                        <td style="text-align:center;">
                            <a href="{{ route('contratacion.show', $p) }}" class="btn-icon" title="Ver detalle">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            @if($puedeEditar && !empty($p->documentosSubidos()))
                            <a href="{{ route('contratacion.zip', $p) }}" class="btn-icon" title="Descargar ZIP">
                                <i class="bi bi-file-zip-fill" style="color:#f59e0b;"></i>
                            </a>
                            @endif
                            @if($puedeEliminar)
                            <button type="button" class="btn-icon" title="Eliminar registro"
                                onclick="confirmarEliminar('{{ $p->id }}', '{{ addslashes($p->folio) }}', '{{ addslashes($p->nombre) }}')"
                                style="color:#ef4444;">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($postulantes->hasPages())
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);">
            {{ $postulantes->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</div>

{{-- Modal eliminar --}}
@if($puedeEliminar)
<div id="modal-eliminar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;justify-content:center;align-items:center;backdrop-filter:blur(3px)">
    <div class="glass-card" style="max-width:440px;width:90%;padding:1.75rem;">
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
            <div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444;font-size:1.2rem;"></i>
            </div>
            <div>
                <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--text-main);">Eliminar registro</h3>
                <p style="margin:0;font-size:.78rem;color:var(--text-muted);">Esta acción no se puede deshacer</p>
            </div>
        </div>
        <p style="font-size:.875rem;color:var(--text-main);margin-bottom:.25rem;">¿Eliminar permanentemente el registro?</p>
        <p id="modal-eliminar-info" style="font-size:.82rem;font-weight:600;color:#dc2626;margin-bottom:1.25rem;"></p>
        <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:1.25rem;">Se eliminarán el registro y todos los documentos adjuntos del sistema.</p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button type="button" class="btn-ghost" onclick="cerrarModalEliminar()">Cancelar</button>
            <form id="form-eliminar" method="POST" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit" style="display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.2rem;border-radius:10px;background:#ef4444;color:#fff;border:none;font-weight:600;font-size:.875rem;cursor:pointer;">
                    <i class="bi bi-trash3-fill"></i> Eliminar definitivamente
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function confirmarEliminar(id, folio, nombre) {
    document.getElementById('modal-eliminar-info').textContent = folio + ' — ' + nombre;
    document.getElementById('form-eliminar').action = '/contratacion/' + id;
    document.getElementById('modal-eliminar').style.display = 'flex';
}
function cerrarModalEliminar() {
    document.getElementById('modal-eliminar').style.display = 'none';
}
document.getElementById('modal-eliminar').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalEliminar();
});
</script>
@endif

@endsection
