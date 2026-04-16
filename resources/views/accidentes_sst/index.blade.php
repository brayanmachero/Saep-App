@extends('layouts.app')
@section('title','Accidentes y Enfermedades Profesionales')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Accidentes / Enf. Profesionales</h2>
            <p class="page-subheading">Registro de accidentes del trabajo y enfermedades profesionales</p>
        </div>
        <div style="display:flex;gap:.5rem">
            @if(auth()->user()->tieneAcceso('accidentes_sst', 'puede_editar'))
            <a href="{{ route('accidentes-sst.opciones') }}" class="btn-secondary">
                <i class="bi bi-gear"></i> Catálogo
            </a>
            @endif
            @if(auth()->user()->tieneAcceso('accidentes_sst', 'puede_crear'))
            <a href="{{ route('accidentes-sst.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nuevo Caso
            </a>
            @endif
        </div>
    </div>
    @include('partials._alerts')

    {{-- Barra de filtros --}}
    <div class="glass-card" style="margin-bottom:1rem;padding:.75rem 1rem">
        <form method="GET" action="{{ route('accidentes-sst.index') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
            <div style="flex:1;min-width:180px">
                <label style="font-size:.75rem;color:var(--text-muted)">Buscar (RUT, nombre, folio)</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Buscar...">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="form-control">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="form-control">
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach(['ingresado','aceptado','rechazado','aprobado','cerrado'] as $e)
                    <option value="{{ $e }}" {{ request('estado') === $e ? 'selected' : '' }}>{{ ucfirst($e) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;color:var(--text-muted)">Gravedad</label>
                <select name="gravedad" class="form-control">
                    <option value="">Todas</option>
                    @foreach(['leve','moderado','grave','fatal'] as $g)
                    <option value="{{ $g }}" {{ request('gravedad') === $g ? 'selected' : '' }}>{{ ucfirst($g) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-premium" style="height:fit-content"><i class="bi bi-search"></i> Filtrar</button>
            @if(request()->hasAny(['buscar','fecha_desde','fecha_hasta','estado','gravedad']))
            <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary" style="height:fit-content"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif
        </form>
    </div>

    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>N°</th><th>Fecha</th><th>Trabajador</th><th>RUT</th><th>Centro</th><th>Tipo</th><th>Gravedad</th><th>Estado</th><th>Días</th><th>Reportado por</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($accidentes as $acc)
            <tr>
                <td><code>{{ $acc->numero_caso ?? $acc->id }}</code></td>
                <td>{{ \Carbon\Carbon::parse($acc->fecha_accidente)->format('d/m/Y') }}</td>
                <td>{{ $acc->trabajador_nombre ?? $acc->trabajador->name ?? '—' }}</td>
                <td><small>{{ $acc->trabajador_rut ?? '—' }}</small></td>
                <td>{{ $acc->centroCosto->nombre ?? '—' }}</td>
                <td><span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$acc->tipo)) }}</span></td>
                <td><span class="{{ $acc->gravedadBadge['class'] }}">{{ $acc->gravedadBadge['label'] }}</span></td>
                <td><span class="{{ $acc->estadoBadge['class'] }}">{{ $acc->estadoBadge['label'] }}</span></td>
                <td>{{ $acc->dias_perdidos ?? 0 }}</td>
                <td><small>{{ $acc->registradoPor->name ?? '—' }}</small></td>
                <td style="white-space:nowrap">
                    <a href="{{ route('accidentes-sst.show', $acc) }}" class="icon-btn" title="Ver"><i class="bi bi-eye-fill"></i></a>
                    @if(auth()->user()->tieneAcceso('accidentes_sst', 'puede_editar'))
                    <a href="{{ route('accidentes-sst.edit', $acc) }}" class="icon-btn" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                    <button class="icon-btn" title="Acción rápida"
                            onclick="abrirAccionRapida({{ $acc->id }}, '{{ $acc->estado }}', {{ $acc->dias_perdidos ?? 0 }})">
                        <i class="bi bi-lightning-fill"></i>
                    </button>
                    @endif
                    @if(auth()->user()->tieneAcceso('accidentes_sst', 'puede_eliminar'))
                    <form method="POST" action="{{ route('accidentes-sst.destroy', $acc) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este caso?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger"><i class="bi bi-trash-fill"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay accidentes registrados.
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if($accidentes->hasPages())
        <div style="padding:1rem 0">{{ $accidentes->links() }}</div>
        @endif
    </div>
</div>

{{-- Modal Acción Rápida --}}
<div id="modal-accion-rapida" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;justify-content:center;align-items:center">
    <div class="glass-card" style="width:400px;max-width:95vw">
        <h4 style="margin-bottom:1rem"><i class="bi bi-lightning-fill" style="color:var(--accent-primary)"></i> Acción Rápida</h4>
        <input type="hidden" id="ar_id">
        <div class="form-group">
            <label>Estado</label>
            <select id="ar_estado" class="form-control">
                @foreach(['ingresado','aceptado','rechazado','aprobado','cerrado'] as $e)
                <option value="{{ $e }}">{{ ucfirst($e) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Días Perdidos</label>
            <input type="number" id="ar_dias" class="form-control" min="0">
        </div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem">
            <button class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-premium" onclick="guardarAccionRapida()">Guardar</button>
        </div>
    </div>
</div>

<script>
function abrirAccionRapida(id, estado, dias) {
    document.getElementById('ar_id').value = id;
    document.getElementById('ar_estado').value = estado;
    document.getElementById('ar_dias').value = dias;
    document.getElementById('modal-accion-rapida').style.display = 'flex';
}
function cerrarModal() {
    document.getElementById('modal-accion-rapida').style.display = 'none';
}
function guardarAccionRapida() {
    const id = document.getElementById('ar_id').value;
    fetch('/accidentes-sst/' + id + '/accion-rapida', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            estado: document.getElementById('ar_estado').value,
            dias_perdidos: parseInt(document.getElementById('ar_dias').value)
        })
    }).then(r => r.json()).then(data => {
        if (data.ok) location.reload();
    });
}
document.getElementById('modal-accion-rapida').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
</script>
@endsection
