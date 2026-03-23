@extends('layouts.app')
@section('title','Visitas e Inspecciones SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h1>Visitas e Inspecciones SST</h1>
            <p style="color:var(--text-muted);margin:0">Registro de visitas de campo y observaciones preventivas</p>
        </div>
        <a href="{{ route('visitas-sst.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nueva Visita
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>N°</th><th>Fecha</th><th>Centro</th><th>Tipo</th><th>Inspector</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($visitas as $v)
            <tr>
                <td><code>{{ $v->numero_visita ?? $v->id }}</code></td>
                <td>{{ \Carbon\Carbon::parse($v->fecha_visita)->format('d/m/Y') }}</td>
                <td>{{ $v->centroCosto->nombre ?? '—' }}</td>
                <td>
                    <span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$v->tipo_visita)) }}</span>
                </td>
                <td>{{ $v->inspector->name ?? '—' }}</td>
                <td><span class="{{ $v->estadoBadge['class'] }}">{{ $v->estadoBadge['label'] }}</span></td>
                <td>
                    <a href="{{ route('visitas-sst.show', $v) }}" class="icon-btn" title="Ver detalle">
                        <i class="bi bi-eye-fill"></i>
                    </a>
                    <a href="{{ route('visitas-sst.edit', $v) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <form method="POST" action="{{ route('visitas-sst.destroy', $v) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar esta visita?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn" style="color:#ef4444"><i class="bi bi-trash-fill"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay visitas registradas. <a href="{{ route('visitas-sst.create') }}">Registrar la primera</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if($visitas->hasPages())
        <div style="padding:1rem 0">{{ $visitas->links() }}</div>
        @endif
    </div>
</div>
@endsection
