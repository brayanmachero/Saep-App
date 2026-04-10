@extends('layouts.app')
@section('title','Carta Gantt SST')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-calendar3" style="color:var(--primary-color)"></i> Carta Gantt SST</h2>
            <p class="page-subheading">Programas anuales de Seguridad y Salud en el Trabajo</p>
        </div>
        @if(auth()->user()->tieneAcceso('carta_gantt', 'puede_crear'))
        <a href="{{ route('carta-gantt.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Programa
        </a>
        @endif
    </div>

    @include('partials._alerts')

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Programas</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary-color);">{{ $stats['total'] }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Activos</div>
            <div style="font-size:1.8rem;font-weight:700;color:#16a34a;">{{ $stats['activos'] }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Act. Vencidas</div>
            <div style="font-size:1.8rem;font-weight:700;color:{{ $stats['vencidas'] > 0 ? '#dc2626' : '#16a34a' }};">{{ $stats['vencidas'] }}</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('carta-gantt.index') }}" class="filter-form">
            <div class="filter-group">
                <label>Año</label>
                <select name="anio" class="form-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($anios as $a)
                        <option value="{{ $a }}" {{ request('anio') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Estado</label>
                <select name="estado" class="form-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="ACTIVO" {{ request('estado') === 'ACTIVO' ? 'selected' : '' }}>Activo</option>
                    <option value="BORRADOR" {{ request('estado') === 'BORRADOR' ? 'selected' : '' }}>Borrador</option>
                    <option value="CERRADO" {{ request('estado') === 'CERRADO' ? 'selected' : '' }}>Cerrado</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Centro de Costo</label>
                <select name="centro_costo_id" class="form-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($centros as $cc)
                        <option value="{{ $cc->id }}" {{ request('centro_costo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['anio','estado','centro_costo_id']))
                <a href="{{ route('carta-gantt.index') }}" class="btn-ghost" style="align-self:flex-end;"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    {{-- Tabla --}}
    <div class="glass-card">
        <div class="glass-table-container">
            <table class="glass-table">
                <thead><tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Año</th>
                    <th>Centro Costo</th>
                    <th>Responsable</th>
                    <th>Avance</th>
                    <th>Estado</th>
                    <th style="width:120px;">Acciones</th>
                </tr></thead>
                <tbody>
                @forelse($programas as $prog)
                <tr>
                    <td><code style="background:var(--surface-bg);padding:.15rem .4rem;border-radius:4px;font-size:.8rem;font-weight:600;">{{ $prog->codigo ?? '—' }}</code></td>
                    <td><strong>{{ $prog->nombre }}</strong></td>
                    <td>{{ $prog->anio }}</td>
                    <td>{{ $prog->centroCosto->nombre ?? '—' }}</td>
                    <td>{{ $prog->responsable->nombre_completo ?? '—' }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="flex:1;background:#e5e7eb;border-radius:9999px;height:8px;min-width:80px;">
                                <div style="width:{{ $prog->porcentajeRealizado }}%;background:linear-gradient(90deg,var(--primary-color),var(--accent-color,#f97316));height:8px;border-radius:9999px;transition:width .3s;"></div>
                            </div>
                            <span style="font-size:.8rem;font-weight:600;min-width:35px;">{{ $prog->porcentajeRealizado }}%</span>
                        </div>
                    </td>
                    <td><span class="badge {{ $prog->estadoBadge }}">{{ ucfirst(strtolower($prog->estado)) }}</span></td>
                    <td>
                        <div style="display:flex;gap:.35rem;">
                            <a href="{{ route('carta-gantt.show', $prog) }}" class="icon-btn" title="Ver Gantt"><i class="bi bi-grid-3x3-gap-fill"></i></a>
                            @if(auth()->user()->tieneAcceso('carta_gantt', 'puede_editar'))
                            <a href="{{ route('carta-gantt.edit', $prog) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            @endif
                            @if(auth()->user()->tieneAcceso('carta_gantt', 'puede_eliminar'))
                            <form method="POST" action="{{ route('carta-gantt.destroy', $prog) }}" style="display:inline" onsubmit="return confirm('¿Cerrar este programa?')">
                                @csrf @method('DELETE')
                                <button class="icon-btn danger" title="Cerrar"><i class="bi bi-archive-fill"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">
                    No hay programas SST. <a href="{{ route('carta-gantt.create') }}">Crear el primero</a>
                </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
