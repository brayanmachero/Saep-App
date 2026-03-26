@extends('layouts.app')
@section('title','Detalle Visita SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Visita N° {{ $visitaSst->numero_visita ?? $visitaSst->id }}</h2>
            <p style="color:var(--text-muted);margin:0">
                {{ \Carbon\Carbon::parse($visitaSst->fecha_visita)->format('d/m/Y') }}
                · {{ $visitaSst->centroCosto->nombre ?? '—' }}
            </p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('visitas-sst.edit', $visitaSst) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('visitas-sst.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                Información General
            </h3>
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem .75rem;margin:0">
                <dt style="font-weight:600;color:var(--text-muted);white-space:nowrap">Tipo</dt>
                <dd style="margin:0"><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$visitaSst->tipo_visita)) }}</span></dd>
                <dt style="font-weight:600;color:var(--text-muted)">Estado</dt>
                <dd style="margin:0"><span class="{{ $visitaSst->estadoBadge['class'] }}">{{ $visitaSst->estadoBadge['label'] }}</span></dd>
                <dt style="font-weight:600;color:var(--text-muted)">Inspector</dt>
                <dd style="margin:0">{{ $visitaSst->inspector->name ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Área</dt>
                <dd style="margin:0">{{ $visitaSst->area_inspeccionada ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Cierre esperado</dt>
                <dd style="margin:0">{{ $visitaSst->fecha_cierre ? \Carbon\Carbon::parse($visitaSst->fecha_cierre)->format('d/m/Y') : '—' }}</dd>
            </dl>
        </div>

        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                Observaciones
            </h3>
            <p style="margin:0;white-space:pre-line;color:var(--text-color)">{{ $visitaSst->observaciones ?? '—' }}</p>
        </div>

        @if($visitaSst->medidas_correctivas)
        <div class="glass-card" style="grid-column:span 2">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                Medidas Correctivas
            </h3>
            <p style="margin:0;white-space:pre-line">{{ $visitaSst->medidas_correctivas }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
