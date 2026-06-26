@extends('layouts.app')

@section('content')
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main);">
            <i class="bi bi-shield-lock" style="color: var(--primary-color);"></i> Administración — Protección de Datos
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Gestión de solicitudes ARCO recibidas (Ley 21.719)
        </p>
    </div>
</div>

{{-- Stats --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card-glass" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 700; color: #f59e0b;">{{ $stats['pendientes'] }}</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Pendientes</div>
    </div>
    <div class="card-glass" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 700; color: #3b82f6;">{{ $stats['en_revision'] }}</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">En Revisión</div>
    </div>
    <div class="card-glass" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 700; color: #dc2626;">{{ $stats['vencidas'] }}</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Vencidas</div>
    </div>
    <div class="card-glass" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color);">{{ $stats['total_mes'] }}</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Este Mes</div>
    </div>
    <div class="card-glass" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 700; color: #7c3aed;">{{ $stats['publicas'] }}</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Públicas</div>
    </div>
    <div class="card-glass" style="padding: 1.25rem; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: 700; color: #1d4ed8;">{{ $stats['bloqueos_activos'] }}</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Bloqueos activos</div>
    </div>
</div>

{{-- Filtros --}}
<div class="card-glass" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('proteccion-datos.administrar') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px;">Estado</label>
            <select name="estado" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: var(--bg-color); color: var(--text-main);">
                <option value="">Todos</option>
                <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="en_revision" {{ request('estado') === 'en_revision' ? 'selected' : '' }}>En Revisión</option>
                <option value="aprobada" {{ request('estado') === 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                <option value="rechazada" {{ request('estado') === 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                <option value="completada" {{ request('estado') === 'completada' ? 'selected' : '' }}>Completada</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px;">Tipo</label>
            <select name="tipo" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: var(--bg-color); color: var(--text-main);">
                <option value="">Todos</option>
                <option value="acceso" {{ request('tipo') === 'acceso' ? 'selected' : '' }}>Acceso</option>
                <option value="rectificacion" {{ request('tipo') === 'rectificacion' ? 'selected' : '' }}>Rectificación</option>
                <option value="supresion" {{ request('tipo') === 'supresion' ? 'selected' : '' }}>Supresión</option>
                <option value="oposicion" {{ request('tipo') === 'oposicion' ? 'selected' : '' }}>Oposición</option>
                <option value="portabilidad" {{ request('tipo') === 'portabilidad' ? 'selected' : '' }}>Portabilidad</option>
                <option value="bloqueo" {{ request('tipo') === 'bloqueo' ? 'selected' : '' }}>Bloqueo</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px;">Canal</label>
            <select name="canal" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: var(--bg-color); color: var(--text-main);">
                <option value="">Todos</option>
                <option value="interno" {{ request('canal') === 'interno' ? 'selected' : '' }}>Interno</option>
                <option value="publico" {{ request('canal') === 'publico' ? 'selected' : '' }}>Público</option>
            </select>
        </div>
        <button type="submit" style="padding: 0.5rem 1.2rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; font-weight: 500;">
            <i class="bi bi-funnel"></i> Filtrar
        </button>
        @if(request()->hasAny(['estado', 'tipo', 'canal']))
        <a href="{{ route('proteccion-datos.administrar') }}" style="padding: 0.5rem 1rem; color: var(--text-muted); font-size: 0.85rem; text-decoration: none;">
            <i class="bi bi-x"></i> Limpiar
        </a>
        @endif
    </form>
</div>

{{-- Tabla de solicitudes --}}
<div class="card-glass" style="padding: 1.5rem;">
    @if($solicitudes->isEmpty())
    <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
        <i class="bi bi-inbox" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.4;"></i>
        <p>No hay solicitudes ARCO que coincidan con los filtros</p>
    </div>
    @else
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--bg-color);">
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">N° Solicitud</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Titular</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Canal</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Tipo</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Fecha</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Vencimiento</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Estado</th>
                    <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($solicitudes as $sol)
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.75rem 1rem; font-weight: 600; color: var(--primary-color);">{{ $sol->numero_solicitud }}</td>
                    <td style="padding: 0.75rem 1rem; color: var(--text-main);">
                        {{ $sol->titular_nombre_mostrar }}
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $sol->titular_email_mostrar ?? '—' }}</div>
                        @if($sol->titular_rut)
                            <div style="font-size: 0.72rem; color: var(--text-muted);">RUT: {{ $sol->titular_rut }}</div>
                        @endif
                    </td>
                    <td style="padding: 0.75rem 1rem; color: var(--text-main);">
                        {{ $sol->canal_label }}
                        @if($sol->titular_contexto)
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ str_replace('_', ' ', $sol->titular_contexto) }}</div>
                        @endif
                        @if($sol->bloqueo_temporal_activo)
                            <div style="display:inline-flex;margin-top:0.25rem;padding:0.15rem 0.45rem;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:0.7rem;font-weight:700;">
                                <i class="bi bi-pause-circle"></i>&nbsp;Bloqueo
                            </div>
                        @endif
                    </td>
                    <td style="padding: 0.75rem 1rem; color: var(--text-main);">{{ $sol->nombre_tipo }}</td>
                    <td style="padding: 0.75rem 1rem; color: var(--text-muted);">{{ $sol->fecha_solicitud->format('d/m/Y') }}</td>
                    <td style="padding: 0.75rem 1rem; color: {{ $sol->fecha_vencimiento->isPast() && in_array($sol->estado, ['pendiente','en_revision']) ? '#dc2626' : 'var(--text-muted)' }};">
                        {{ $sol->fecha_vencimiento->format('d/m/Y') }}
                        @if($sol->fecha_vencimiento->isPast() && in_array($sol->estado, ['pendiente','en_revision']))
                            <i class="bi bi-exclamation-triangle-fill" style="color: #dc2626;" title="Vencida"></i>
                        @endif
                    </td>
                    <td style="padding: 0.75rem 1rem;">
                        <span style="background: {{ $sol->color_estado }}20; color: {{ $sol->color_estado }}; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                            {{ $sol->nombre_estado }}
                        </span>
                    </td>
                    <td style="padding: 0.75rem 1rem; text-align: center;">
                        <a href="{{ route('proteccion-datos.ver-solicitud', $sol) }}" style="color: var(--primary-color); text-decoration: none; font-weight: 500; font-size: 0.85rem;">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top: 1rem;">
        {{ $solicitudes->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
