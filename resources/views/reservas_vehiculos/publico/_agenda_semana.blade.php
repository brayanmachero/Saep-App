<style>
    .vr-outlook-calendar{margin:0 0 18px;padding:18px}.vr-outlook-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:14px}.vr-outlook-head h2{margin:0;font-size:1rem}.vr-outlook-head p{margin:.35rem 0 0;color:var(--muted);font-size:.78rem;line-height:1.45}.vr-outlook-frame{overflow:hidden;border:1px solid var(--line);border-radius:7px;background:var(--soft);min-height:640px}.vr-outlook-frame iframe{display:block;width:100%;height:640px;border:0;background:#fff}.vr-outlook-note{display:flex;align-items:flex-start;gap:.45rem;margin:.85rem 0 0;color:#7c3e1e;font-size:.74rem;line-height:1.45}.vr-outlook-note i{margin-top:.08rem;color:var(--orange)}@media(max-width:560px){.vr-outlook-calendar{padding:15px}.vr-outlook-head{flex-direction:column;gap:.6rem}.vr-outlook-head .vr-button{width:100%}.vr-outlook-frame,.vr-outlook-frame iframe{min-height:520px;height:520px}}
</style>

<section id="agenda" class="vr-card vr-outlook-calendar">
    <div class="vr-outlook-head">
        <div>
            <h2><i class="bi bi-calendar-week"></i> Calendario de reservas</h2>
            <p>Visualización pública de Outlook. Muestra únicamente los horarios ocupados de la flota; no permite crear, editar ni cancelar reservas.</p>
        </div>
        @if(filled($calendarioPublicadoUrl))
            <a class="vr-button secondary" href="{{ $calendarioPublicadoUrl }}" target="_blank" rel="noopener noreferrer" title="Abrir el calendario de reservas en Outlook"><i class="bi bi-box-arrow-up-right"></i> Abrir Outlook</a>
        @endif
    </div>

    @if(filled($calendarioPublicadoUrl))
        <div class="vr-outlook-frame">
            <iframe
                src="{{ $calendarioPublicadoUrl }}"
                title="Calendario público de reservas de vehículos SAEP"
                loading="lazy"
                referrerpolicy="no-referrer"
            ></iframe>
        </div>
        <p class="vr-outlook-note"><i class="bi bi-eye"></i><span>Este calendario es de consulta. La disponibilidad final se valida al confirmar cada solicitud y considera el margen operativo de {{ $margenReservaMinutos }} minutos entre reservas.</span></p>
    @else
        <div class="vr-calendar-empty"><i class="bi bi-calendar-x"></i> El calendario publicado de Outlook aún no está configurado.</div>
    @endif
</section>
