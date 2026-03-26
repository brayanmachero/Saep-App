@extends('layouts.app')

@section('content')
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main);">
            <i class="bi bi-journal-text" style="color: var(--primary-color);"></i> Registro de Tratamiento de Datos
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Auditoría de actividades de tratamiento de datos personales (Art. 14 quinquies, Ley 19.628 ref.)
        </p>
    </div>
</div>

{{-- Filtros --}}
<div class="card-glass" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('proteccion-datos.registro-tratamiento') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Acción</label>
            <select name="accion" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: var(--bg-color); color: var(--text-main);">
                <option value="">Todas</option>
                <option value="consulta" {{ request('accion') === 'consulta' ? 'selected' : '' }}>Consulta</option>
                <option value="modificacion" {{ request('accion') === 'modificacion' ? 'selected' : '' }}>Modificación</option>
                <option value="eliminacion" {{ request('accion') === 'eliminacion' ? 'selected' : '' }}>Eliminación</option>
                <option value="exportacion" {{ request('accion') === 'exportacion' ? 'selected' : '' }}>Exportación</option>
                <option value="consentimiento" {{ request('accion') === 'consentimiento' ? 'selected' : '' }}>Consentimiento</option>
                <option value="revocacion_consentimiento" {{ request('accion') === 'revocacion_consentimiento' ? 'selected' : '' }}>Revocación</option>
                <option value="solicitud_arco" {{ request('accion') === 'solicitud_arco' ? 'selected' : '' }}>Solicitud ARCO</option>
                <option value="respuesta_arco" {{ request('accion') === 'respuesta_arco' ? 'selected' : '' }}>Respuesta ARCO</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Desde</label>
            <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                style="padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: var(--bg-color); color: var(--text-main);">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase;">Hasta</label>
            <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                style="padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: var(--bg-color); color: var(--text-main);">
        </div>
        <button type="submit" style="padding: 0.5rem 1.2rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; font-weight: 500;">
            <i class="bi bi-funnel"></i> Filtrar
        </button>
        @if(request()->hasAny(['accion', 'fecha_desde', 'fecha_hasta']))
        <a href="{{ route('proteccion-datos.registro-tratamiento') }}" style="padding: 0.5rem 1rem; color: var(--text-muted); font-size: 0.85rem; text-decoration: none;">
            <i class="bi bi-x"></i> Limpiar
        </a>
        @endif
    </form>
</div>

{{-- Tabla --}}
<div class="card-glass" style="padding: 1.5rem;">
    @if($registros->isEmpty())
    <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
        <i class="bi bi-journal" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.4;"></i>
        <p>No hay registros de tratamiento</p>
    </div>
    @else
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
            <thead>
                <tr style="background: var(--bg-color);">
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Fecha</th>
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Usuario</th>
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Acción</th>
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Tabla</th>
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Tipo Dato</th>
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Descripción</th>
                    <th style="padding: 0.7rem 0.75rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach($registros as $reg)
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.7rem 0.75rem; color: var(--text-muted); white-space: nowrap;">
                        {{ $reg->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td style="padding: 0.7rem 0.75rem; color: var(--text-main);">
                        {{ $reg->user?->nombre_completo ?? 'Sistema' }}
                    </td>
                    <td style="padding: 0.7rem 0.75rem;">
                        @php
                        $colorAccion = match($reg->accion) {
                            'consulta' => '#3b82f6',
                            'modificacion' => '#f59e0b',
                            'eliminacion' => '#dc2626',
                            'exportacion' => '#8b5cf6',
                            'consentimiento' => '#10b981',
                            'revocacion_consentimiento' => '#ef4444',
                            'solicitud_arco' => '#0f1b4c',
                            'respuesta_arco' => '#059669',
                            default => '#6b7280',
                        };
                        @endphp
                        <span style="background: {{ $colorAccion }}15; color: {{ $colorAccion }}; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase;">
                            {{ str_replace('_', ' ', $reg->accion) }}
                        </span>
                    </td>
                    <td style="padding: 0.7rem 0.75rem; color: var(--text-muted); font-family: monospace; font-size: 0.8rem;">{{ $reg->tabla_afectada }}</td>
                    <td style="padding: 0.7rem 0.75rem;">
                        <span style="background: {{ $reg->tipo_dato === 'sensible' ? '#fef2f2' : '#f0fdf4' }}; color: {{ $reg->tipo_dato === 'sensible' ? '#dc2626' : '#059669' }}; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                            {{ ucfirst($reg->tipo_dato) }}
                        </span>
                    </td>
                    <td style="padding: 0.7rem 0.75rem; color: var(--text-main); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $reg->descripcion }}">
                        {{ $reg->descripcion ?? '—' }}
                    </td>
                    <td style="padding: 0.7rem 0.75rem; color: var(--text-muted); font-family: monospace; font-size: 0.8rem;">{{ $reg->ip_address }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top: 1rem;">
        {{ $registros->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
