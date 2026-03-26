@extends('layouts.app')
@section('title', 'Denuncia Registrada — Ley Karin')

@section('content')
<div class="page-container">

    <div style="max-width:680px;margin:2rem auto;">
        {{-- Icono de éxito --}}
        <div style="text-align:center;margin-bottom:2rem;">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#22c55e);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                <i class="bi bi-check-lg" style="font-size:2.5rem;color:#fff;"></i>
            </div>
            <h2 class="page-heading" style="margin-bottom:.25rem;">Denuncia registrada correctamente</h2>
            <p class="page-subheading">Tu denuncia ha sido recibida y será procesada de forma confidencial.</p>
        </div>

        {{-- Folio --}}
        <div class="glass-card" style="text-align:center;margin-bottom:1.25rem;border-left:4px solid #16a34a;">
            <p style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;font-weight:700;">
                Número de Folio
            </p>
            <p style="font-size:1.8rem;font-weight:800;color:var(--primary-color,#0f1b4c);margin:0;letter-spacing:.05em;">
                {{ $leyKarin->folio }}
            </p>
            <p style="font-size:.85rem;color:var(--text-muted);margin-top:.5rem;">
                Guarda este número para hacer seguimiento de tu caso.
            </p>
        </div>

        {{-- Resumen --}}
        <div class="glass-card" style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;font-weight:700;">
                <i class="bi bi-file-earmark-text"></i> Resumen
            </h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div>
                    <span style="font-size:.8rem;color:var(--text-muted);">Tipo de denuncia</span>
                    <p style="margin:.15rem 0 0;font-weight:600;">{{ $leyKarin->tipo_label }}</p>
                </div>
                <div>
                    <span style="font-size:.8rem;color:var(--text-muted);">Fecha</span>
                    <p style="margin:.15rem 0 0;font-weight:600;">{{ \Carbon\Carbon::parse($leyKarin->fecha_denuncia)->format('d/m/Y') }}</p>
                </div>
                <div>
                    <span style="font-size:.8rem;color:var(--text-muted);">Estado</span>
                    <p style="margin:.15rem 0 0;"><span class="badge info">Recibida</span></p>
                </div>
                <div>
                    <span style="font-size:.8rem;color:var(--text-muted);">Modalidad</span>
                    <p style="margin:.15rem 0 0;">
                        @if($leyKarin->anonima)
                            <span class="badge secondary"><i class="bi bi-eye-slash"></i> Anónima</span>
                        @else
                            <span class="badge success"><i class="bi bi-person-check"></i> Identificada</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Qué sigue --}}
        <div class="glass-card" style="margin-bottom:1.25rem;border-left:4px solid var(--primary-color,#0f1b4c);">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:.75rem;font-weight:700;">
                <i class="bi bi-arrow-right-circle-fill"></i> ¿Qué sucede ahora?
            </h3>
            <ol style="font-size:.88rem;color:var(--text-muted);line-height:1.8;margin:0;padding-left:1.2rem;">
                <li>El Departamento de Prevención <strong>recibirá tu denuncia</strong> de forma inmediata.</li>
                <li>Se designará un <strong>investigador</strong> para analizar los hechos.</li>
                <li>Se evaluarán y aplicarán <strong>medidas cautelares</strong> para tu protección.</li>
                <li>La investigación se completará en un plazo máximo de <strong>30 días hábiles</strong>.</li>
                @if(!$leyKarin->anonima)
                <li>Serás <strong>notificado/a por correo</strong> cuando el caso sea resuelto.</li>
                @endif
            </ol>
        </div>

        {{-- Protecciones --}}
        <div class="glass-card" style="margin-bottom:1.5rem;border-left:4px solid #dc2626;">
            <h3 style="font-size:0.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:.75rem;font-weight:700;">
                <i class="bi bi-shield-fill-check" style="color:#dc2626;"></i> Tus Protecciones Legales
            </h3>
            <ul style="font-size:.88rem;color:var(--text-muted);line-height:1.7;margin:0;padding-left:1.2rem;">
                <li>Está <strong>prohibida toda represalia</strong> contra el denunciante (Art. 211-B CT).</li>
                <li>Tu identidad será tratada con <strong>estricta confidencialidad</strong>.</li>
                <li>Si consideras que hay represalias, puedes denunciarlo ante la <strong>Dirección del Trabajo</strong>.</li>
            </ul>
        </div>

        {{-- Acción --}}
        <div style="text-align:center;">
            <a href="{{ url('/') }}" class="btn-premium">
                <i class="bi bi-house"></i> Volver al Inicio
            </a>
        </div>
    </div>
</div>
@endsection
