@extends('layouts.app')
@section('title','Carta Gantt SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h1>Carta Gantt SST</h1>
            <p style="color:var(--text-muted);margin:0">Programas anuales de Seguridad y Salud en el Trabajo</p>
        </div>
        <a href="{{ route('carta-gantt.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nuevo Programa
        </a>
    </div>
    @include('partials._alerts')
    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>Código</th><th>Nombre</th><th>Año</th><th>Centro Costo</th><th>Avance</th><th>Estado</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($programas as $prog)
            <tr>
                <td><code>{{ $prog->codigo }}</code></td>
                <td><strong>{{ $prog->nombre }}</strong></td>
                <td>{{ $prog->anio }}</td>
                <td>{{ $prog->centroCosto->nombre ?? '—' }}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div style="flex:1;background:#e5e7eb;border-radius:9999px;height:8px;min-width:80px">
                            <div style="width:{{ $prog->porcentajeRealizado }}%;background:var(--primary);height:8px;border-radius:9999px"></div>
                        </div>
                        <span style="font-size:.8rem;font-weight:600">{{ $prog->porcentajeRealizado }}%</span>
                    </div>
                </td>
                <td>
                    <span class="badge badge-{{ $prog->estado === 'ACTIVO' ? 'success' : ($prog->estado === 'CERRADO' ? 'info' : 'secondary') }}">
                        {{ ucfirst(strtolower($prog->estado)) }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('carta-gantt.show', $prog) }}" class="icon-btn" title="Ver Gantt">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </a>
                    <a href="{{ route('carta-gantt.edit', $prog) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <form method="POST" action="{{ route('carta-gantt.destroy', $prog) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este programa?')">
                        @csrf @method('DELETE')
                        <button class="icon-btn" style="color:#ef4444"><i class="bi bi-trash-fill"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay programas SST. <a href="{{ route('carta-gantt.create') }}">Crear el primero</a>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
