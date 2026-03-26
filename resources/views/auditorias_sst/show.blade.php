@extends('layouts.app')
@section('title','Detalle Auditoría SST')
@section('content')
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Auditoría N° {{ $auditoriaSst->numero_auditoria ?? $auditoriaSst->id }}</h2>
            <p style="color:var(--text-muted);margin:0">
                {{ \Carbon\Carbon::parse($auditoriaSst->fecha_auditoria)->format('d/m/Y') }}
                · {{ $auditoriaSst->centroCosto->nombre ?? '—' }}
            </p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('auditorias-sst.edit', $auditoriaSst) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('auditorias-sst.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Datos Generales</h3>
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem .75rem;margin:0">
                <dt style="font-weight:600;color:var(--text-muted)">Tipo</dt>
                <dd style="margin:0"><span class="badge badge-secondary">{{ ucfirst($auditoriaSst->tipo_auditoria) }}</span></dd>
                <dt style="font-weight:600;color:var(--text-muted)">Estado</dt>
                <dd style="margin:0"><span class="{{ $auditoriaSst->estadoBadge['class'] }}">{{ $auditoriaSst->estadoBadge['label'] }}</span></dd>
                <dt style="font-weight:600;color:var(--text-muted)">Auditor</dt>
                <dd style="margin:0">{{ $auditoriaSst->auditor->name ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Norma / Alcance</dt>
                <dd style="margin:0">{{ $auditoriaSst->norma_alcance ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Puntaje</dt>
                <dd style="margin:0">
                    @if($auditoriaSst->puntaje !== null)
                        <strong>{{ $auditoriaSst->puntaje }}%</strong>
                    @else —
                    @endif
                </dd>
                <dt style="font-weight:600;color:var(--text-muted)">Cierre</dt>
                <dd style="margin:0">{{ $auditoriaSst->fecha_cierre ? \Carbon\Carbon::parse($auditoriaSst->fecha_cierre)->format('d/m/Y') : '—' }}</dd>
            </dl>
        </div>

        @if($auditoriaSst->puntaje !== null)
        <div class="glass-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
            <div style="font-size:.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Resultado</div>
            <div style="font-size:3.5rem;font-weight:800;color:{{ $auditoriaSst->puntaje >= 80 ? '#16a34a' : ($auditoriaSst->puntaje >= 60 ? '#d97706' : '#dc2626') }}">
                {{ $auditoriaSst->puntaje }}%
            </div>
            <div style="margin-top:.5rem;color:var(--text-muted)">
                {{ $auditoriaSst->puntaje >= 80 ? 'Satisfactorio' : ($auditoriaSst->puntaje >= 60 ? 'Requiere mejoras' : 'Deficiente') }}
            </div>
        </div>
        @endif

        @if($auditoriaSst->hallazgos)
        <div class="glass-card" style="{{ $auditoriaSst->puntaje === null ? 'grid-column:span 2' : '' }}">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Hallazgos</h3>
            <p style="margin:0;white-space:pre-line">{{ $auditoriaSst->hallazgos }}</p>
        </div>
        @endif

        @if($auditoriaSst->plan_accion)
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Plan de Acción Correctiva</h3>
            <p style="margin:0;white-space:pre-line">{{ $auditoriaSst->plan_accion }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
