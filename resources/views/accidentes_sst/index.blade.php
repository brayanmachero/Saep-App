@extends('layouts.app')
@section('title','Accidentes y Enfermedades Profesionales')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Accidentes / Enf. Profesionales</h2>
            <p class="page-subheading">Registro de accidentes del trabajo y enfermedades profesionales</p>
        </div>
        <a href="{{ route('accidentes-sst.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Caso
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>N°</th><th>Fecha</th><th>Trabajador</th><th>Centro</th><th>Tipo</th><th>Gravedad</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($accidentes as $acc)
            <tr>
                <td><code>{{ $acc->numero_caso ?? $acc->id }}</code></td>
                <td>{{ \Carbon\Carbon::parse($acc->fecha_accidente)->format('d/m/Y') }}</td>
                <td>{{ $acc->trabajador->name ?? '—' }}</td>
                <td>{{ $acc->centroCosto->nombre ?? '—' }}</td>
                <td><span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$acc->tipo)) }}</span></td>
                <td><span class="{{ $acc->gravedadBadge['class'] }}">{{ $acc->gravedadBadge['label'] }}</span></td>
                <td>
                    <span class="badge badge-{{ $acc->estado === 'cerrado' ? 'success' : ($acc->estado === 'investigacion' ? 'warning' : 'secondary') }}">
                        {{ ucfirst(str_replace('_',' ',$acc->estado)) }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('accidentes-sst.show', $acc) }}" class="icon-btn"><i class="bi bi-eye-fill"></i></a>
                    <a href="{{ route('accidentes-sst.edit', $acc) }}" class="icon-btn"><i class="bi bi-pencil-fill"></i></a>
                    <form method="POST" action="{{ route('accidentes-sst.destroy', $acc) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este caso?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger"><i class="bi bi-trash-fill"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">
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
@endsection
