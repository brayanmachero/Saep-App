@php
    $agendaPorDia = $agendaSemanal->groupBy(fn ($reserva) => $reserva->inicio->toDateString());
    $diasSemana = collect(range(0, 6))->map(fn (int $dia) => $agendaSemanalInicio->copy()->addDays($dia));
    $enlaceSemana = function ($fecha) use ($inicioInput, $terminoInput) {
        return route('reservas-vehiculos.inicio', array_filter([
            'inicio' => $inicioInput ?: null,
            'termino' => $terminoInput ?: null,
            'semana' => $fecha->format('Y-m-d'),
        ]));
    };
@endphp

<style>
    .vr-week-calendar{margin:0 0 18px;padding:18px}.vr-week-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:14px}.vr-week-head h2{margin:0;font-size:1rem}.vr-week-head p{margin:.35rem 0 0;color:var(--muted);font-size:.78rem;line-height:1.45}.vr-week-actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}.vr-week-scroll{overflow-x:auto;padding-bottom:3px}.vr-week-grid{display:grid;grid-template-columns:repeat(7,minmax(142px,1fr));min-width:994px;border:1px solid var(--line);border-radius:7px;overflow:hidden}.vr-week-day{min-height:176px;background:#fff;border-right:1px solid var(--line)}.vr-week-day:last-child{border-right:0}.vr-week-day.is-today{background:#fffaf6}.vr-week-day-head{padding:.68rem .7rem;background:var(--soft);border-bottom:1px solid var(--line)}.vr-week-day.is-today .vr-week-day-head{background:#fff2ea}.vr-week-day-name{display:block;color:var(--muted);font-size:.64rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.vr-week-day-date{display:block;margin-top:.15rem;font-size:.9rem;font-weight:900}.vr-week-event{margin:.55rem;padding:.55rem;border-left:3px solid var(--orange);border-radius:4px;background:#fff7ed}.vr-week-event time{display:block;color:#9a3412;font-size:.7rem;font-weight:900}.vr-week-event strong{display:block;margin-top:.24rem;color:var(--ink);font-size:.76rem}.vr-week-event small{display:block;margin-top:.18rem;color:var(--muted);font-size:.66rem;line-height:1.35}.vr-week-empty{padding:.82rem .55rem;color:#9aa4b5;font-size:.7rem;line-height:1.35;text-align:center}.vr-week-note{display:flex;align-items:flex-start;gap:.45rem;margin:.85rem 0 0;color:#7c3e1e;font-size:.74rem;line-height:1.45}.vr-week-note i{margin-top:.08rem;color:var(--orange)}@media(max-width:560px){.vr-week-calendar{padding:15px}.vr-week-head{flex-direction:column;gap:.6rem}.vr-week-actions{width:100%}.vr-week-actions .vr-button{flex:1}.vr-week-grid{min-width:882px}.vr-week-day{min-height:158px}}
</style>

<section id="agenda" class="vr-card vr-week-calendar">
    <div class="vr-week-head">
        <div>
            <h2><i class="bi bi-calendar-week"></i> Agenda semanal</h2>
            <p>Disponibilidad de la flota para la semana del {{ $agendaSemanalInicio->format('d/m') }} al {{ $agendaSemanalTermino->copy()->subDay()->format('d/m/Y') }}. No se exponen nombres ni motivos de otras solicitudes.</p>
        </div>
        <div class="vr-week-actions">
            <a class="vr-button secondary" href="{{ $enlaceSemana($agendaSemanalInicio->copy()->subWeek()) }}" title="Ver semana anterior"><i class="bi bi-chevron-left"></i></a>
            <a class="vr-button secondary" href="{{ $enlaceSemana(now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)) }}">Esta semana</a>
            <a class="vr-button secondary" href="{{ $enlaceSemana($agendaSemanalInicio->copy()->addWeek()) }}" title="Ver semana siguiente"><i class="bi bi-chevron-right"></i></a>
            <a class="vr-button secondary" href="https://outlook.office.com/calendar/view/week" target="_blank" rel="noopener" title="Abrir el calendario compartido en Outlook"><i class="bi bi-box-arrow-up-right"></i> Outlook</a>
        </div>
    </div>
    <div class="vr-week-scroll" aria-label="Agenda semanal de reservas">
        <div class="vr-week-grid">
            @foreach($diasSemana as $dia)
                @php($reservasDia = $agendaPorDia->get($dia->toDateString(), collect()))
                <article class="vr-week-day @if($dia->isToday()) is-today @endif">
                    <div class="vr-week-day-head">
                        <span class="vr-week-day-name">{{ $dia->locale('es')->isoFormat('ddd') }}</span>
                        <span class="vr-week-day-date">{{ $dia->format('d') }} {{ $dia->locale('es')->isoFormat('MMM') }}</span>
                    </div>
                    @forelse($reservasDia as $reserva)
                        <div class="vr-week-event">
                            <time>{{ $reserva->inicio->format('H:i') }} - {{ $reserva->termino->format('H:i') }}</time>
                            <strong>{{ $reserva->vehiculo?->patente }}</strong>
                            <small>{{ $reserva->vehiculo?->nombre_operativo }} · {{ \App\Models\ReservaVehiculo::ESTADOS[$reserva->estado] }}</small>
                        </div>
                    @empty
                        <div class="vr-week-empty">Sin reservas registradas.</div>
                    @endforelse
                </article>
            @endforeach
        </div>
    </div>
    <p class="vr-week-note"><i class="bi bi-clock-history"></i><span>Cada reserva bloquea el vehículo durante su rango y agrega {{ $margenReservaMinutos }} minutos de resguardo antes y después. Ese tiempo evita programaciones sin margen de devolución.</span></p>
</section>
