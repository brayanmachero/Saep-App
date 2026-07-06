@extends('layouts.app')
@section('title','Cargas Contenedores')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Cargas</h2>
            <p class="page-subheading">Tandas generadas desde programación, correo o Excel.</p>
        </div>
        @if(auth()->user()->tieneAcceso('descarga_contenedores', 'puede_crear'))
        <a href="{{ route('descarga-contenedores.carga-rapida') }}" class="btn-premium">
            <i class="bi bi-clipboard-plus"></i> Nueva carga
        </a>
        @endif
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')
    @include('descarga_contenedores._context_help', [
        'title' => 'Historial de cargas',
        'items' => [
            'Cada carga agrupa una importación masiva realizada desde Programación.',
            'Filas con alerta indica registros que quedaron con datos pendientes, tarifa por revisar o información incompleta.',
            'Ver registros abre el listado filtrado por el nombre de la tanda.',
        ],
    ])

    <div class="stats-grid">
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-layers"></i></div>
            <div class="stat-info"><h3>{{ $stats['tandas'] }}</h3><p>Tandas</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon success"><i class="bi bi-table"></i></div>
            <div class="stat-info"><h3>{{ $stats['registros'] }}</h3><p>Registros creados</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon warning"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-info"><h3>{{ $stats['alertas'] }}</h3><p>Filas con alerta</p></div>
        </div>
        <div class="glass-card stat-item">
            <div class="stat-icon primary"><i class="bi bi-clock-history"></i></div>
            <div class="stat-info">
                <h3>{{ $stats['ultima'] ? \Carbon\Carbon::parse($stats['ultima'])->format('d/m') : '—' }}</h3>
                <p>Última carga</p>
            </div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('descarga-contenedores.cargas') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Nombre u origen...">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="form-control">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="form-control">
            </div>
            <button type="submit" class="btn-premium"><i class="bi bi-search"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','fecha_desde','fecha_hasta']))
                <a href="{{ route('descarga-contenedores.cargas') }}" class="btn-secondary"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Carga</th>
                        <th>Origen</th>
                        <th>Filas</th>
                        <th>Alertas</th>
                        <th>Creado por</th>
                        <th>Registros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cargas as $carga)
                    <tr>
                        <td>{{ $carga->created_at?->format('d/m/Y H:i') }}</td>
                        <td><strong>{{ $carga->nombre ?: 'Carga sin nombre' }}</strong></td>
                        <td><span class="badge info">{{ ucfirst($carga->origen) }}</span></td>
                        <td>{{ $carga->filas_creadas }} / {{ $carga->filas_detectadas }}</td>
                        <td>
                            @if($carga->filas_con_alertas > 0)
                                <span class="badge warning">{{ $carga->filas_con_alertas }}</span>
                            @else
                                0
                            @endif
                        </td>
                        <td>{{ $carga->creadoPor?->nombre_completo ?: $carga->creadoPor?->name ?: '—' }}</td>
                        <td>
                            <a href="{{ route('descarga-contenedores.index', ['buscar' => $carga->nombre]) }}" class="btn-secondary">
                                <i class="bi bi-arrow-right"></i> Ver {{ $carga->descargas_count }}
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">No hay cargas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cargas->hasPages())
            <div style="padding:1rem 0">{{ $cargas->links() }}</div>
        @endif
    </div>
</div>
@endsection
