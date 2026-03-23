@extends('layouts.app')
@section('title','Expediente Ley Karin')
@section('content')
<div class="page-container" style="max-width:980px">
    <div class="page-header">
        <div>
            <h1>Expediente {{ $leyKarin->folio }}</h1>
            <p style="color:var(--text-muted);margin:0">
                {{ ucfirst(str_replace('_',' ',$leyKarin->tipo_denuncia)) }}
                · {{ \Carbon\Carbon::parse($leyKarin->fecha_denuncia)->format('d/m/Y') }}
                @if($leyKarin->confidencial)
                    <span class="badge badge-danger" style="margin-left:.5rem">🔒 Confidencial</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('ley-karin.edit', $leyKarin) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('ley-karin.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    {{-- Estado visual --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.5rem">
        <div style="display:flex;gap:2rem;flex-wrap:wrap;align-items:center">
            <div>
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Estado</div>
                <span class="{{ $leyKarin->estadoBadge['class'] }}" style="font-size:.95rem">{{ $leyKarin->estadoBadge['label'] }}</span>
            </div>
            <div>
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Centro</div>
                <div style="font-weight:600">{{ $leyKarin->centroCosto->nombre ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Investigador</div>
                <div style="font-weight:600">{{ $leyKarin->investigador->name ?? 'Sin asignar' }}</div>
            </div>
            @if($leyKarin->fecha_plazo_investigacion)
            <div>
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Plazo Legal</div>
                <div style="font-weight:600;color:{{ \Carbon\Carbon::parse($leyKarin->fecha_plazo_investigacion)->isPast() ? '#dc2626' : 'inherit' }}">
                    {{ \Carbon\Carbon::parse($leyKarin->fecha_plazo_investigacion)->format('d/m/Y') }}
                    @if(\Carbon\Carbon::parse($leyKarin->fecha_plazo_investigacion)->isPast())
                        <span class="badge badge-danger">Vencido</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                <i class="bi bi-person-fill" style="margin-right:.4rem"></i>Denunciante
            </h3>
            @if($leyKarin->anonima)
                <p style="color:var(--text-muted);font-style:italic;margin:0"><i class="bi bi-incognito"></i> Denuncia anónima</p>
            @else
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.4rem .75rem;margin:0">
                    <dt style="font-weight:600;color:var(--text-muted)">Nombre</dt>
                    <dd style="margin:0">{{ $leyKarin->denunciante->name ?? $leyKarin->nombre_denunciante ?? '—' }}</dd>
                </dl>
            @endif
        </div>

        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                <i class="bi bi-person-x-fill" style="margin-right:.4rem"></i>Denunciado
            </h3>
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.4rem .75rem;margin:0">
                <dt style="font-weight:600;color:var(--text-muted)">Nombre</dt>
                <dd style="margin:0">{{ $leyKarin->nombre_denunciado ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Cargo</dt>
                <dd style="margin:0">{{ $leyKarin->cargo_denunciado ?? '—' }}</dd>
            </dl>
        </div>

        <div class="glass-card" style="grid-column:span 2">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                Descripción de los Hechos
            </h3>
            <p style="margin:0;white-space:pre-line;line-height:1.7">{{ $leyKarin->descripcion_hechos }}</p>
        </div>

        @if($leyKarin->resultado_investigacion)
        <div class="glass-card" style="grid-column:span 2">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                Resultado de la Investigación
            </h3>
            <p style="margin:0;white-space:pre-line">{{ $leyKarin->resultado_investigacion }}</p>
        </div>
        @endif

        @if($leyKarin->medidas_adoptadas)
        <div class="glass-card" style="grid-column:span 2">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                Medidas Adoptadas
            </h3>
            <p style="margin:0;white-space:pre-line">{{ $leyKarin->medidas_adoptadas }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
