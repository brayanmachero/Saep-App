@extends('layouts.app')

@section('content')
<div style="max-width: 640px; margin: 2rem auto; padding: 0 1rem;">
    <div class="card-glass" style="padding: 2.5rem; text-align: center;">
        <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #0f1b4c, #1e3a8a); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
            <i class="bi bi-shield-lock" style="font-size: 2rem; color: #fff;"></i>
        </div>

        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
            Protección de Datos Personales
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem; max-width: 480px; margin-left: auto; margin-right: auto;">
            Conforme a la <strong>Ley N° 21.719</strong>, necesitamos su consentimiento para el tratamiento de sus datos personales antes de continuar.
        </p>

        <div style="background: var(--bg-color); border-radius: 10px; padding: 1.5rem; text-align: left; margin-bottom: 2rem; max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color);">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 1rem;">Resumen de la Política</h3>

            <div style="margin-bottom: 1rem;">
                <div style="display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <i class="bi bi-check-circle-fill" style="color: #059669; font-size: 0.9rem; margin-top: 2px;"></i>
                    <span style="font-size: 0.85rem; color: var(--text-main);">
                        <strong>Responsable:</strong> SAEP SpA es responsable del tratamiento de sus datos personales.
                    </span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <i class="bi bi-check-circle-fill" style="color: #059669; font-size: 0.9rem; margin-top: 2px;"></i>
                    <span style="font-size: 0.85rem; color: var(--text-main);">
                        <strong>Finalidad:</strong> Gestión de seguridad y salud en el trabajo, administración de personal, y cumplimiento normativo.
                    </span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <i class="bi bi-check-circle-fill" style="color: #059669; font-size: 0.9rem; margin-top: 2px;"></i>
                    <span style="font-size: 0.85rem; color: var(--text-main);">
                        <strong>Datos:</strong> Datos de identificación, laborales y técnicos. Los datos sensibles (salud, Ley Karin) tienen protección reforzada.
                    </span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <i class="bi bi-check-circle-fill" style="color: #059669; font-size: 0.9rem; margin-top: 2px;"></i>
                    <span style="font-size: 0.85rem; color: var(--text-main);">
                        <strong>Derechos:</strong> Usted tiene derecho de acceso, rectificación, supresión, oposición y portabilidad (derechos ARCO).
                    </span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <i class="bi bi-check-circle-fill" style="color: #059669; font-size: 0.9rem; margin-top: 2px;"></i>
                    <span style="font-size: 0.85rem; color: var(--text-main);">
                        <strong>Revocación:</strong> Puede retirar su consentimiento en cualquier momento desde la sección "Protección de Datos".
                    </span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.6rem;">
                    <i class="bi bi-check-circle-fill" style="color: #059669; font-size: 0.9rem; margin-top: 2px;"></i>
                    <span style="font-size: 0.85rem; color: var(--text-main);">
                        <strong>Contacto:</strong> protecciondatos@saep.cl
                    </span>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <a href="{{ route('proteccion-datos.politica-privacidad') }}" target="_blank"
               style="color: var(--primary-color); font-weight: 600; text-decoration: none; font-size: 0.9rem;">
                <i class="bi bi-file-earmark-text"></i> Leer política completa <i class="bi bi-box-arrow-up-right" style="font-size: 0.7rem;"></i>
            </a>
        </div>

        <form action="{{ route('proteccion-datos.aceptar-politica') }}" method="POST" id="consent-form">
            @csrf
            <button type="submit" id="btn-accept"
                style="width: 100%; padding: 0.9rem 2rem; background: linear-gradient(135deg, #0f1b4c, #1e3a8a); color: #fff; border: none; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: opacity 0.2s; margin-bottom: 1rem;"
                onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                <i class="bi bi-check-lg"></i> Acepto la Política de Datos Personales
            </button>
        </form>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit"
                style="background: none; border: none; color: var(--text-muted); font-size: 0.85rem; cursor: pointer; text-decoration: underline;">
                Prefiero cerrar sesión
            </button>
        </form>

        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 1.5rem;">
            Al aceptar, otorga su consentimiento libre, informado, específico e inequívoco para el tratamiento
            de sus datos personales conforme a la Ley N° 21.719 y la Ley N° 19.628 reformada.
        </p>
    </div>
</div>

<script>
document.getElementById('consent-form').addEventListener('submit', function() {
    var btn = document.getElementById('btn-accept');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
    btn.style.opacity = '0.6';
    btn.style.cursor = 'not-allowed';
});
</script>
@endsection
