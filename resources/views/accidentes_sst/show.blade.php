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

    <div class="detail-layout">
        {{-- ─── COLUMNA PRINCIPAL ─── --}}
        <div class="detail-main">
            {{-- Info del reportante --}}
            <div style="background:var(--bg-tertiary);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem">
                <i class="bi bi-person-badge" style="font-size:1.25rem;color:var(--accent-primary)"></i>
                <div>
                    <small style="color:var(--text-muted)">Reportado por</small>
                    <div style="font-weight:600">{{ $accidenteSst->registradoPor->name ?? '—' }} {{ $accidenteSst->registradoPor->apellido_paterno ?? '' }}</div>
                </div>
                <div style="margin-left:auto;color:var(--text-muted);font-size:.82rem">
                    {{ $accidenteSst->created_at?->format('d/m/Y H:i') }}
                </div>
            </div>

            {{-- Descripción --}}
            <div class="detail-card">
                <h3 class="detail-card-title"><i class="bi bi-file-text"></i> Descripción del Accidente</h3>
                <p style="margin:0;white-space:pre-line;line-height:1.6">{{ $accidenteSst->descripcion }}</p>
            </div>

            {{-- Clasificación --}}
            <div class="detail-card">
                <h3 class="detail-card-title"><i class="bi bi-tags"></i> Clasificación del Evento</h3>
                <div class="detail-tags-grid">
                    <div>
                        <label class="detail-label">Lesiones / Diagnóstico</label>
                        @if($accidenteSst->lesionesOpciones->count())
                        <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                            @foreach($accidenteSst->lesionesOpciones as $op)
                            <span class="badge badge-info">{{ $op->nombre }}</span>
                            @endforeach
                        </div>
                        @else
                        <span style="color:var(--text-muted)">{{ $accidenteSst->lesiones ?? '—' }}</span>
                        @endif
                    </div>
                    <div>
                        <label class="detail-label">Causas del Accidente</label>
                        @if($accidenteSst->causasOpciones->count())
                        <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                            @foreach($accidenteSst->causasOpciones as $op)
                            <span class="badge badge-warning">{{ $op->nombre }}</span>
                            @endforeach
                        </div>
                        @else
                        <span style="color:var(--text-muted)">{{ $accidenteSst->causas ?? '—' }}</span>
                        @endif
                    </div>
                    <div>
                        <label class="detail-label">Medidas Preventivas</label>
                        @if($accidenteSst->medidasOpciones->count())
                        <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                            @foreach($accidenteSst->medidasOpciones as $op)
                            <span class="badge badge-success">{{ $op->nombre }}</span>
                            @endforeach
                        </div>
                        @else
                        <span style="color:var(--text-muted)">{{ $accidenteSst->medidas_preventivas ?? '—' }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── SIDEBAR ─── --}}
        <div class="detail-sidebar">
            {{-- Estado --}}
            <div class="detail-card" style="text-align:center">
                <span class="{{ $accidenteSst->estadoBadge['class'] }}" style="font-size:.9rem;padding:.4rem 1.2rem">
                    {{ $accidenteSst->estadoBadge['label'] }}
                </span>
            </div>

            {{-- Datos del Evento --}}
            <div class="detail-card">
                <h3 class="detail-card-title"><i class="bi bi-clipboard-data"></i> Datos del Evento</h3>
                <div class="detail-field">
                    <span class="detail-label">Tipo</span>
                    <span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$accidenteSst->tipo)) }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-label">Gravedad</span>
                    <span class="{{ $accidenteSst->gravedadBadge['class'] }}">{{ $accidenteSst->gravedadBadge['label'] }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-label">Días perdidos</span>
                    <span style="font-weight:600">{{ $accidenteSst->dias_perdidos ?? 0 }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-label">DIAT</span>
                    <span>{{ $accidenteSst->numero_diat ?? '—' }}</span>
                </div>
            </div>

            {{-- Trabajador Afectado --}}
            <div class="detail-card">
                <h3 class="detail-card-title"><i class="bi bi-person-fill"></i> Trabajador Afectado</h3>
                <div class="detail-field">
                    <span class="detail-label">Nombre</span>
                    <span style="font-weight:600">{{ $accidenteSst->trabajador_nombre ?? $accidenteSst->trabajador->name ?? '—' }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-label">RUT</span>
                    <span>{{ $accidenteSst->trabajador_rut ?? '—' }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-label">Cargo</span>
                    <span>{{ $accidenteSst->trabajador_cargo ?? '—' }}</span>
                </div>
                @if($accidenteSst->trabajador_kizeo_id)
                <div style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid var(--surface-border, #e5e7eb)">
                    <small style="color:var(--text-muted)"><i class="bi bi-cloud-check"></i> Fuente: Kizeo</small>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.detail-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.25rem;
    max-width: 1100px;
}
.detail-main { display:flex; flex-direction:column; gap:1.25rem; }
.detail-sidebar { display:flex; flex-direction:column; gap:1.25rem; }

.detail-card {
    background: var(--surface-color);
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.detail-card-title {
    margin: 0 0 1rem 0;
    font-size: .95rem;
    font-weight: 600;
    padding-bottom: .65rem;
    border-bottom: 1px solid var(--surface-border, #e5e7eb);
    display: flex;
    align-items: center;
    gap: .5rem;
}
.detail-card-title i { color: var(--accent-primary, #6366f1); font-size: .9rem; }

.detail-field {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .45rem 0;
    border-bottom: 1px solid rgba(0,0,0,.04);
}
.detail-field:last-child { border-bottom: none; }

.detail-label {
    font-size: .82rem;
    color: var(--text-muted);
    font-weight: 500;
    display: block;
    margin-bottom: .25rem;
}

.detail-tags-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.25rem;
}

@media (max-width: 860px) {
    .detail-layout { grid-template-columns: 1fr; }
    .detail-tags-grid { grid-template-columns: 1fr; gap: .75rem; }
}
@media (max-width: 640px) {
    .detail-layout { gap: .75rem; }
    .detail-card { padding: .85rem; border-radius: 10px; }
    .detail-card-title { font-size: .85rem; margin-bottom: .65rem; padding-bottom: .5rem; }
    .detail-field { flex-direction: column; align-items: flex-start; gap: .15rem; padding: .35rem 0; }
    .detail-field .detail-label { margin-bottom: .1rem; }
    .detail-label { font-size: .75rem; }
}
</style>
@endsection
