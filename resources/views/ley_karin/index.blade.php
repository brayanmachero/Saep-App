@extends('layouts.app')
@section('title','Ley Karin — Denuncias')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h1>Ley Karin — Denuncias</h1>
            <p style="color:var(--text-muted);margin:0">Gestión de denuncias por acoso laboral, sexual y violencia en el trabajo (Ley 21.643)</p>
        </div>
        <a href="{{ route('ley-karin.create') }}" class="btn-premium">
            <i class="bi bi-plus-lg"></i> Nueva Denuncia
        </a>
    </div>
    @include('partials._alerts')

    @if(session('folio_generado'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        <i class="bi bi-check-circle-fill"></i>
        Denuncia creada con folio <strong>{{ session('folio_generado') }}</strong>. Guárdelo para seguimiento.
    </div>
    @endif

    <div class="glass-card">
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr>
                <th>Folio</th><th>Fecha</th><th>Tipo</th><th>Centro</th><th>Denunciante</th><th>Estado</th><th>Confidencial</th><th>Acciones</th>
            </tr></thead>
            <tbody>
            @forelse($casos as $caso)
            <tr>
                <td><code>{{ $caso->folio }}</code></td>
                <td>{{ \Carbon\Carbon::parse($caso->fecha_denuncia)->format('d/m/Y') }}</td>
                <td>
                    <span class="badge badge-warning">{{ ucfirst(str_replace('_',' ',$caso->tipo_denuncia)) }}</span>
                </td>
                <td>{{ $caso->centroCosto->nombre ?? '—' }}</td>
                <td>
                    @if($caso->anonima)
                        <em style="color:var(--text-muted)">Anónima</em>
                    @else
                        {{ $caso->denunciante->name ?? $caso->nombre_denunciante ?? '—' }}
                    @endif
                </td>
                <td><span class="{{ $caso->estadoBadge['class'] }}">{{ $caso->estadoBadge['label'] }}</span></td>
                <td>
                    <span class="badge {{ $caso->confidencial ? 'badge-danger' : 'badge-secondary' }}">
                        {{ $caso->confidencial ? '🔒 Sí' : 'No' }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('ley-karin.show', $caso) }}" class="icon-btn" title="Ver expediente">
                        <i class="bi bi-folder2-open"></i>
                    </a>
                    <a href="{{ route('ley-karin.edit', $caso) }}" class="icon-btn" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">
                No hay denuncias registradas.
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if($casos->hasPages())
        <div style="padding:1rem 0">{{ $casos->links() }}</div>
        @endif
    </div>
</div>
@endsection
