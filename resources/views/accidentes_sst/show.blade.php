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

    {{-- Info del reportante --}}
    <div style="background:var(--bg-tertiary);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem">
        <i class="bi bi-person-badge" style="font-size:1.25rem;color:var(--accent-primary)"></i>
        <div>
            <small style="color:var(--text-muted)">Reportado por</small>
            <div style="font-weight:600">{{ $accidenteSst->registradoPor->name ?? '—' }} {{ $accidenteSst->registradoPor->apellido_paterno ?? '' }}</div>
        </div>
        <div style="margin-left:auto;color:var(--text-muted);font-size:.85rem">
            {{ $accidenteSst->created_at?->format('d/m/Y H:i') }}
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
                    <span class="{{ $accidenteSst->estadoBadge['class'] }}">
                        {{ $accidenteSst->estadoBadge['label'] }}
                    </span>
                </dd>
                <dt style="font-weight:600;color:var(--text-muted)">Días perdidos</dt>
                <dd style="margin:0">{{ $accidenteSst->dias_perdidos ?? 0 }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">DIAT</dt>
                <dd style="margin:0">{{ $accidenteSst->numero_diat ?? '—' }}</dd>
            </dl>
        </div>

        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">
                <i class="bi bi-person-fill"></i> Trabajador Afectado
            </h3>
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem .75rem;margin:0">
                <dt style="font-weight:600;color:var(--text-muted)">Nombre</dt>
                <dd style="margin:0">{{ $accidenteSst->trabajador_nombre ?? $accidenteSst->trabajador->name ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">RUT</dt>
                <dd style="margin:0">{{ $accidenteSst->trabajador_rut ?? '—' }}</dd>
                <dt style="font-weight:600;color:var(--text-muted)">Cargo</dt>
                <dd style="margin:0">{{ $accidenteSst->trabajador_cargo ?? '—' }}</dd>
            </dl>
            @if($accidenteSst->trabajador_kizeo_id)
            <div style="margin-top:.5rem">
                <small style="color:var(--text-muted)"><i class="bi bi-cloud-check"></i> Fuente: Kizeo - Personal Vigente</small>
            </div>
            @endif
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Lesiones / Diagnóstico</h3>
            @if($accidenteSst->lesionesOpciones->count())
            <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                @foreach($accidenteSst->lesionesOpciones as $op)
                <span class="badge badge-info">{{ $op->nombre }}</span>
                @endforeach
            </div>
            @else
            <p style="margin:0;color:var(--text-muted)">{{ $accidenteSst->lesiones ?? '—' }}</p>
            @endif
        </div>
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Causas</h3>
            @if($accidenteSst->causasOpciones->count())
            <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                @foreach($accidenteSst->causasOpciones as $op)
                <span class="badge badge-warning">{{ $op->nombre }}</span>
                @endforeach
            </div>
            @else
            <p style="margin:0;color:var(--text-muted)">{{ $accidenteSst->causas ?? '—' }}</p>
            @endif
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Descripción</h3>
            <p style="margin:0;white-space:pre-line">{{ $accidenteSst->descripcion }}</p>
        </div>
        <div class="glass-card">
            <h3 style="margin-top:0;font-size:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.75rem">Medidas Preventivas</h3>
            @if($accidenteSst->medidasOpciones->count())
            <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                @foreach($accidenteSst->medidasOpciones as $op)
                <span class="badge badge-success">{{ $op->nombre }}</span>
                @endforeach
            </div>
            @else
            <p style="margin:0;color:var(--text-muted)">{{ $accidenteSst->medidas_preventivas ?? '—' }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
