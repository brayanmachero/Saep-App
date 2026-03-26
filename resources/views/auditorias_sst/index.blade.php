@extends('layouts.app')
@section('title','Auditorías SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Auditorías SST</h2>
            <p class="page-subheading">Auditorías internas y externas de Seguridad y Salud en el Trabajo</p>
        </div>
        <a href="{{ route('auditorias-sst.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nueva Auditoría
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>N°</th><th>Fecha</th><th>Tipo</th><th>Centro</th><th>Auditor</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($auditorias as $a)
            <tr>
                <td><code>{{ $a->numero_auditoria ?? $a->id }}</code></td>
                <td>{{ \Carbon\Carbon::parse($a->fecha_auditoria)->format('d/m/Y') }}</td>
                <td><span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$a->tipo_auditoria)) }}</span></td>
                <td>{{ $a->centroCosto->nombre ?? '—' }}</td>
                <td>{{ $a->auditor->name ?? '—' }}</td>
                <td><span class="{{ $a->estadoBadge['class'] }}">{{ $a->estadoBadge['label'] }}</span></td>
                <td>
                    <a href="{{ route('auditorias-sst.show', $a) }}" class="icon-btn"><i class="bi bi-eye-fill"></i></a>
                    <a href="{{ route('auditorias-sst.edit', $a) }}" class="icon-btn"><i class="bi bi-pencil-fill"></i></a>
                    <form method="POST" action="{{ route('auditorias-sst.destroy', $a) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar esta auditoría?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn danger"><i class="bi bi-trash-fill"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay auditorías registradas. <a href="{{ route('auditorias-sst.create') }}">Crear la primera</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if($auditorias->hasPages())
        <div style="padding:1rem 0">{{ $auditorias->links() }}</div>
        @endif
    </div>
</div>
@endsection
