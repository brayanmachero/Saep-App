@extends('layouts.app')
@section('title','Detalle Accidente SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Caso N° {{ $accidenteSst->numero_caso ?? $accidenteSst->id }}</h2>
            <p style="color:var(--text-muted);margin:0">
                {{ \Carbon\Carbon::parse($accidenteSst->fecha_accidente)->format('d/m/Y') }}
                @if($accidenteSst->hora_accidente) — {{ $accidenteSst->hora_accidente }} @endif
                · {{ $accidenteSst->centroCosto->nombre ?? '—' }}
            </p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('accidentes-sst.edit', $accidenteSst) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('accidentes-sst.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Datos del Evento</h3>
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem .75rem;margin:0">
                <dt style="font-weight:600;color:var(--text-muted)">Tipo</dt>
                <dd style="margin:0"><span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$accidenteSst->tipo)) }}</span></dd>
                <dt style="font-weight:600;color:var(--text-muted)">Gravedad</dt>
                <dd style="margin:0"><span class="{{ $accidenteSst->gravedadBadge['class'] }}">{{ $accidenteSst->gravedadBadge['label'] }}</span></dd>
                <dt style="font-weight:600;color:var(--text-muted)">Estado</dt>
                <dd style="margin:0">
                    <span class="badge badge-{{ $accidenteSst->estado === 'cerrado' ? 'success' : ($accidenteSst->estado === 'investigacion' ? 'warning' : 'info') }}">
                        {{ ucfirst($accidenteSst->estado) }}
                    </span>
                </dd>
                <dt style="font-weight:600;color:var(--text-muted)">Trabajador</dt>
                <dd style="margin:0">{{ $accidenteSst->trabajador->name ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Días perdidos</dt>
                <dd style="margin:0">{{ $accidenteSst->dias_perdidos ?? 0 }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">DIAT</dt>
                <dd style="margin:0">{{ $accidenteSst->numero_diat ?? '—' }}</dd>
            </dl>
        </div>

        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Lesiones / Diagnóstico</h3>
            <p style="margin:0;white-space:pre-line">{{ $accidenteSst->lesiones ?? '—' }}</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Descripción</h3>
            <p style="margin:0;white-space:pre-line">{{ $accidenteSst->descripcion }}</p>
        </div>
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Causas</h3>
            <p style="margin:0;white-space:pre-line">{{ $accidenteSst->causas ?? '—' }}</p>
        </div>
        @if($accidenteSst->medidas_preventivas)
        <div class="glass-card" style="grid-column:span 2">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Medidas Preventivas</h3>
            <p style="margin:0;white-space:pre-line">{{ $accidenteSst->medidas_preventivas }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
