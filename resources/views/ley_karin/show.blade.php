@extends('layouts.app')
@section('title', 'Expediente ' . $leyKarin->folio)

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-folder2-open" style="color:var(--primary-color)"></i> Expediente {{ $leyKarin->folio }}</h2>
            <p class="page-subheading">
                {{ $leyKarin->tipo_label }} · {{ $leyKarin->fecha_denuncia->format('d/m/Y') }}
                @if($leyKarin->confidencial)
                    <span class="badge danger" style="margin-left:.5rem;"><i class="bi bi-lock-fill"></i> Confidencial</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:.5rem;">
            <a href="{{ route('ley-karin.edit', $leyKarin) }}" class="btn-premium">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('ley-karin.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    {{-- Estado y info clave --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(15,27,76,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-flag-fill" style="font-size:1.15rem;color:var(--primary-color);"></i>
            </div>
            <div>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;text-transform:uppercase;letter-spacing:.03em;">Estado</p>
                <span class="{{ $leyKarin->estadoBadge['class'] }}" style="font-size:.85rem;">{{ $leyKarin->estadoBadge['label'] }}</span>
            </div>
        </div>
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-geo-alt-fill" style="font-size:1.15rem;color:#d97706;"></i>
            </div>
            <div>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;text-transform:uppercase;letter-spacing:.03em;">Centro</p>
                <p style="font-size:.9rem;font-weight:600;margin:0;">{{ $leyKarin->centroCosto->nombre ?? '—' }}</p>
            </div>
        </div>
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-person-badge-fill" style="font-size:1.15rem;color:#6366f1;"></i>
            </div>
            <div>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;text-transform:uppercase;letter-spacing:.03em;">Investigador</p>
                <p style="font-size:.9rem;font-weight:600;margin:0;">{{ $leyKarin->investigador->name ?? 'Sin asignar' }}</p>
            </div>
        </div>
        @if($leyKarin->fecha_plazo_investigacion)
        <div class="glass-card" style="padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:{{ $leyKarin->plazo_vencido ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-calendar-check-fill" style="font-size:1.15rem;color:{{ $leyKarin->plazo_vencido ? '#dc2626' : '#059669' }};"></i>
            </div>
            <div>
                <p style="font-size:0.72rem;color:var(--text-muted);margin:0;text-transform:uppercase;letter-spacing:.03em;">Plazo Legal</p>
                <p style="font-size:.9rem;font-weight:600;margin:0;color:{{ $leyKarin->plazo_vencido ? '#dc2626' : 'var(--text-main)' }};">
                    {{ $leyKarin->fecha_plazo_investigacion->format('d/m/Y') }}
                    @if($leyKarin->plazo_vencido)
                        <span class="badge danger" style="font-size:.7rem;margin-left:.25rem;">Vencido</span>
                    @endif
                </p>
            </div>
        </div>
        @endif
    </div>

    {{-- Denunciante y Denunciado --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
        <div class="glass-card">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
                <i class="bi bi-person-fill"></i> Denunciante
            </h3>
            @if($leyKarin->anonima)
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--text-muted);font-style:italic;">
                    <i class="bi bi-incognito" style="font-size:1.25rem;"></i> Denuncia anónima
                </div>
            @else
                <div style="display:grid;grid-template-columns:auto 1fr;gap:.4rem .75rem;">
                    <span style="font-size:.85rem;color:var(--text-muted);font-weight:500;">Nombre</span>
                    <span style="font-size:.9rem;">{{ $leyKarin->denunciante->name ?? $leyKarin->denunciante_nombre ?? '—' }}</span>
                    @if($leyKarin->denunciante_rut)
                    <span style="font-size:.85rem;color:var(--text-muted);font-weight:500;">RUT</span>
                    <span style="font-size:.9rem;">{{ $leyKarin->denunciante_rut }}</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="glass-card">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
                <i class="bi bi-person-x-fill"></i> Denunciado
            </h3>
            <div style="display:grid;grid-template-columns:auto 1fr;gap:.4rem .75rem;">
                <span style="font-size:.85rem;color:var(--text-muted);font-weight:500;">Nombre</span>
                <span style="font-size:.9rem;">{{ $leyKarin->denunciado_nombre ?? '—' }}</span>
                <span style="font-size:.85rem;color:var(--text-muted);font-weight:500;">Cargo</span>
                <span style="font-size:.9rem;">{{ $leyKarin->denunciado_cargo ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Descripción de los hechos --}}
    <div class="glass-card" style="margin-bottom:1.25rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-chat-left-text-fill"></i> Descripción de los Hechos
        </h3>
        <p style="margin:0;white-space:pre-line;line-height:1.7;font-size:.9rem;">{{ $leyKarin->descripcion_hechos }}</p>
    </div>

    {{-- Medidas Cautelares --}}
    @if($leyKarin->medidas_cautelares)
    <div class="glass-card" style="margin-bottom:1.25rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-shield-fill-check"></i> Medidas Cautelares
        </h3>
        <p style="margin:0;white-space:pre-line;line-height:1.7;font-size:.9rem;">{{ $leyKarin->medidas_cautelares }}</p>
    </div>
    @endif

    {{-- Resultado de la Investigación --}}
    @if($leyKarin->resultado_investigacion)
    <div class="glass-card" style="margin-bottom:1.25rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-clipboard-check-fill"></i> Resultado de la Investigación
        </h3>
        <p style="margin:0;white-space:pre-line;line-height:1.7;font-size:.9rem;">{{ $leyKarin->resultado_investigacion }}</p>
    </div>
    @endif

    {{-- Medidas Adoptadas --}}
    @if($leyKarin->medidas_adoptadas)
    <div class="glass-card" style="margin-bottom:1.25rem;">
        <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
            <i class="bi bi-check2-all"></i> Medidas Adoptadas
        </h3>
        <p style="margin:0;white-space:pre-line;line-height:1.7;font-size:.9rem;">{{ $leyKarin->medidas_adoptadas }}</p>
    </div>
    @endif

    {{-- Info adicional --}}
    <div class="glass-card" style="background:var(--surface-bg);border:1px solid var(--surface-border);">
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;font-size:.85rem;color:var(--text-muted);">
            @if($leyKarin->canal)
            <div>
                <span style="font-weight:600;">Canal:</span> {{ $leyKarin->canal_label }}
            </div>
            @endif
            @if($leyKarin->fecha_resolucion)
            <div>
                <span style="font-weight:600;">Resolución:</span> {{ $leyKarin->fecha_resolucion->format('d/m/Y') }}
            </div>
            @endif
            <div>
                <span style="font-weight:600;">Creado:</span> {{ $leyKarin->created_at->format('d/m/Y H:i') }}
            </div>
            <div>
                <span style="font-weight:600;">Actualizado:</span> {{ $leyKarin->updated_at->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>
</div>
@endsection
