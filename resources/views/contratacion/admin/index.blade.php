@extends('layouts.app')
@section('title', 'Contratación — Postulantes')

@section('content')
<div class="page-container">

    @include('partials._alerts')

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
            <a href="{{ route('contratacion.export-excel') }}" class="btn-ghost">
                <i class="bi bi-file-earmark-excel-fill" style="color:#22c55e"></i> Exportar Excel
            </a>
            <a href="{{ route('contratacion.configuracion') }}" class="btn-ghost">
                <i class="bi bi-gear-fill"></i> Configuración
            </a>
            <a href="{{ route('contratacion.create') }}" class="btn-premium">
                <i class="bi bi-person-plus-fill"></i> Ingresar manual
            </a>
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
    <div class="glass-card" style="padding:1rem 1.25rem;margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('contratacion.index') }}" class="d-flex" style="gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:200px;">
                <label style="font-size:.78rem;font-weight:600;color:var(--text-muted);margin-bottom:.3rem;display:block;">Buscar</label>
                <input type="text" name="buscar" class="form-control form-control-sm"
                    value="{{ request('buscar') }}"
                    placeholder="Folio, nombre, RUT o correo...">
            </div>
            <div style="min-width:160px;">
                <label style="font-size:.78rem;font-weight:600;color:var(--text-muted);margin-bottom:.3rem;display:block;">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="pendiente"   {{ request('estado') === 'pendiente'   ? 'selected' : '' }}>Pendiente</option>
                    <option value="en_revision" {{ request('estado') === 'en_revision' ? 'selected' : '' }}>En Revisión</option>
                    <option value="aprobado"    {{ request('estado') === 'aprobado'    ? 'selected' : '' }}>Aprobado</option>
                    <option value="rechazado"   {{ request('estado') === 'rechazado'   ? 'selected' : '' }}>Rechazado</option>
                </select>
            </div>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" class="btn-premium" style="padding:.5rem 1.1rem;font-size:.85rem;">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="{{ route('contratacion.index') }}" class="btn-ghost" style="padding:.5rem .85rem;font-size:.85rem;">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
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
                            @php $subidos = count($p->documentosSubidos()); @endphp
                            <span style="
                                padding:.25rem .6rem;border-radius:6px;font-size:.75rem;font-weight:700;
                                background:{{ $p->documentosCompletos() ? '#dcfce7' : '#fefce8' }};
                                color:{{ $p->documentosCompletos() ? '#166534' : '#854d0e' }};
                            ">{{ $subidos }}/4</span>
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
                            @if(!empty($p->documentosSubidos()))
                            <a href="{{ route('contratacion.zip', $p) }}" class="btn-icon" title="Descargar ZIP">
                                <i class="bi bi-file-zip-fill" style="color:#f59e0b;"></i>
                            </a>
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
@endsection
