@extends('layouts.app')

@section('content')
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main);">
            <i class="bi bi-shield-check" style="color: var(--primary-color);"></i> Protección de Datos Personales
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Gestione sus derechos conforme a la Ley 21.719
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('proteccion-datos.politica-privacidad') }}" target="_blank" class="btn-secondary-action">
            <i class="bi bi-file-earmark-text"></i> Ver Política de Privacidad
        </a>
        <a href="{{ route('proteccion-datos.crear-solicitud') }}" class="btn-primary-action">
            <i class="bi bi-plus-lg"></i> Nueva Solicitud ARCO
        </a>
    </div>
</div>

{{-- Estado del consentimiento --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="card-glass" style="padding: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; background: {{ $consentimiento ? '#ecfdf5' : '#fef2f2' }}; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-{{ $consentimiento ? 'check-circle-fill' : 'exclamation-circle-fill' }}" style="font-size: 1.3rem; color: {{ $consentimiento ? '#059669' : '#dc2626' }};"></i>
            </div>
            <div>
                <div style="font-weight: 600; color: var(--text-main);">Estado del Consentimiento</div>
                @if($consentimiento)
                    <div style="font-size: 0.85rem; color: #059669;">
                        Aceptado · Versión {{ $consentimiento->version_politica }} · {{ $consentimiento->fecha_aceptacion->format('d/m/Y H:i') }}
                    </div>
                @else
                    <div style="font-size: 0.85rem; color: #dc2626;">No ha aceptado la política de datos</div>
                @endif
            </div>
        </div>
        @if($consentimiento)
        <form action="{{ route('proteccion-datos.revocar-consentimiento') }}" method="POST" style="margin-top: 1rem;"
              onsubmit="return confirm('¿Está seguro de revocar su consentimiento? Algunos servicios podrían verse limitados.')">
            @csrf
            <button type="submit" style="background: none; border: 1px solid #fca5a5; color: #dc2626; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.8rem; cursor: pointer; font-weight: 500;">
                <i class="bi bi-x-circle"></i> Revocar consentimiento
            </button>
        </form>
        @endif
    </div>

    <div class="card-glass" style="padding: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; background: #eff6ff; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-download" style="font-size: 1.3rem; color: var(--primary-color);"></i>
            </div>
            <div>
                <div style="font-weight: 600; color: var(--text-main);">Portabilidad de Datos</div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">Descargue una copia de sus datos personales</div>
            </div>
        </div>
        <a href="{{ route('proteccion-datos.exportar') }}" class="btn-secondary-action" style="margin-top: 1rem; display: inline-flex; font-size: 0.8rem; padding: 0.4rem 1rem;">
            <i class="bi bi-file-earmark-arrow-down"></i> Exportar mis datos (JSON)
        </a>
    </div>

    <div class="card-glass" style="padding: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; background: #fef3c7; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-envelope-paper" style="font-size: 1.3rem; color: #d97706;"></i>
            </div>
            <div>
                <div style="font-weight: 600; color: var(--text-main);">Canal de Contacto</div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">protecciondatos@saep.cl</div>
            </div>
        </div>
    </div>
</div>

{{-- Derechos ARCO --}}
<div class="card-glass" style="padding: 1.5rem; margin-bottom: 2rem;">
    <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem;">
        <i class="bi bi-person-check" style="color: var(--primary-color);"></i> Sus Derechos ARCO
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem;">
        @php
        $derechos = [
            ['acceso', 'bi-eye', 'Acceso', 'Conocer sus datos'],
            ['rectificacion', 'bi-pencil-square', 'Rectificación', 'Corregir datos'],
            ['supresion', 'bi-trash3', 'Supresión', 'Eliminar datos'],
            ['oposicion', 'bi-hand-thumbs-down', 'Oposición', 'Oponerse al uso'],
            ['portabilidad', 'bi-box-arrow-right', 'Portabilidad', 'Exportar datos'],
        ];
        @endphp
        @foreach($derechos as [$tipo, $icono, $nombre, $desc])
        <a href="{{ route('proteccion-datos.crear-solicitud', ['tipo' => $tipo]) }}"
           style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem; text-align: center; text-decoration: none; transition: all 0.2s; cursor: pointer;"
           onmouseover="this.style.borderColor='var(--primary-color)'; this.style.transform='translateY(-2px)'"
           onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='none'">
            <i class="bi {{ $icono }}" style="font-size: 1.3rem; color: var(--primary-color); display: block; margin-bottom: 0.3rem;"></i>
            <strong style="font-size: 0.85rem; color: var(--text-main); display: block;">{{ $nombre }}</strong>
            <span style="font-size: 0.75rem; color: var(--text-muted);">{{ $desc }}</span>
        </a>
        @endforeach
    </div>
</div>

{{-- Historial de solicitudes --}}
<div class="card-glass" style="padding: 1.5rem;">
    <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem;">
        <i class="bi bi-clock-history" style="color: var(--primary-color);"></i> Mis Solicitudes ARCO
    </h3>

    @if($solicitudes->isEmpty())
    <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
        <i class="bi bi-inbox" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.4;"></i>
        <p style="font-size: 0.95rem;">No tiene solicitudes ARCO registradas</p>
        <a href="{{ route('proteccion-datos.crear-solicitud') }}" style="color: var(--primary-color); font-weight: 600; text-decoration: none; font-size: 0.9rem;">
            <i class="bi bi-plus-lg"></i> Crear primera solicitud
        </a>
    </div>
    @else
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--bg-color);">
                    <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-main); border-bottom: 2px solid var(--border-color);">N° Solicitud</th>
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
                    <td style="padding: 0.75rem 1rem; color: var(--text-main);">{{ $sol->nombre_tipo }}</td>
                    <td style="padding: 0.75rem 1rem; color: var(--text-muted);">{{ $sol->fecha_solicitud->format('d/m/Y') }}</td>
                    <td style="padding: 0.75rem 1rem; color: {{ $sol->fecha_vencimiento->isPast() && $sol->estado === 'pendiente' ? '#dc2626' : 'var(--text-muted)' }};">
                        {{ $sol->fecha_vencimiento->format('d/m/Y') }}
                        @if($sol->fecha_vencimiento->isPast() && $sol->estado === 'pendiente')
                            <i class="bi bi-exclamation-triangle-fill" style="color: #dc2626;" title="Vencida"></i>
                        @endif
                    </td>
                    <td style="padding: 0.75rem 1rem;">
                        <span style="background: {{ $sol->color_estado }}20; color: {{ $sol->color_estado }}; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                            {{ $sol->nombre_estado }}
                        </span>
                    </td>
                    <td style="padding: 0.75rem 1rem; text-align: center;">
                        <a href="{{ route('proteccion-datos.ver-solicitud', $sol) }}" style="color: var(--primary-color); text-decoration: none;" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top: 1rem;">
        {{ $solicitudes->links() }}
    </div>
    @endif
</div>

<style>
.btn-primary-action {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: var(--primary-color); color: #fff; padding: 0.6rem 1.2rem;
    border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem;
    transition: background 0.2s;
}
.btn-primary-action:hover { background: var(--primary-hover); }
.btn-secondary-action {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: var(--surface-color); color: var(--text-main); padding: 0.6rem 1.2rem;
    border: 1px solid var(--border-color); border-radius: 8px;
    text-decoration: none; font-weight: 500; font-size: 0.85rem;
    transition: all 0.2s;
}
.btn-secondary-action:hover { border-color: var(--primary-color); color: var(--primary-color); }
</style>
@endsection
