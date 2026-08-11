@php
    $reservaForm = $reservaForm ?? null;
    $formPrefix = $formPrefix ?? 'reserva';
    $motivoActual = $reservaForm ? trim((string) \Illuminate\Support\Str::before($reservaForm->motivo, ' —')) : '';
    $detalleActual = $reservaForm && str_contains($reservaForm->motivo, ' — ')
        ? \Illuminate\Support\Str::after($reservaForm->motivo, ' — ')
        : ($motivosReserva->contains('nombre', $motivoActual) ? '' : ($reservaForm?->motivo ?? ''));
@endphp
<div class="fleet-form-grid fleet-reservation-form" data-requester-picker>
    <div class="fleet-field wide">
        <label for="{{ $formPrefix }}-vehiculo">Vehiculo</label>
        <select id="{{ $formPrefix }}-vehiculo" class="form-control" name="vehiculo_id" required>
            <option value="">Selecciona un vehiculo</option>
            @foreach($vehiculosReservables as $vehiculoReservable)
                <option value="{{ $vehiculoReservable->id }}" @selected((string) old('vehiculo_id', $reservaForm?->vehiculo_id) === (string) $vehiculoReservable->id)>{{ $vehiculoReservable->patente }} · {{ $vehiculoReservable->nombre_operativo }}@if($vehiculoReservable->sede) · {{ $vehiculoReservable->sede }}@endif</option>
            @endforeach
        </select>
    </div>
    <div class="fleet-field">
        <label for="{{ $formPrefix }}-inicio">Inicio</label>
        <input id="{{ $formPrefix }}-inicio" class="form-control" name="inicio" type="datetime-local" required min="{{ now()->format('Y-m-d\\TH:i') }}" value="{{ old('inicio', $reservaForm?->inicio?->format('Y-m-d\\TH:i')) }}">
    </div>
    <div class="fleet-field">
        <label for="{{ $formPrefix }}-termino">Termino</label>
        <input id="{{ $formPrefix }}-termino" class="form-control" name="termino" type="datetime-local" required min="{{ now()->addMinute()->format('Y-m-d\\TH:i') }}" value="{{ old('termino', $reservaForm?->termino?->format('Y-m-d\\TH:i')) }}">
    </div>
    <div class="fleet-field wide">
        <label for="{{ $formPrefix }}-solicitante">Reservar a nombre de</label>
        <select id="{{ $formPrefix }}-solicitante" class="form-control" name="solicitante_id" data-requester-select>
            <option value="">Ingresar otra persona manualmente</option>
            @foreach($solicitantes as $solicitante)
                <option value="{{ $solicitante->id }}" data-name="{{ $solicitante->nombre }}" data-email="{{ $solicitante->email }}" @selected((string) old('solicitante_id') === (string) $solicitante->id)>{{ $solicitante->nombre }}{{ $solicitante->email ? ' · '.$solicitante->email : ' · correo pendiente' }}{{ $solicitante->activo ? '' : ' (inactivo)' }}</option>
            @endforeach
        </select>
        <small class="fleet-help">Al elegir una persona del catalogo se completan su nombre y correo; ambos pueden corregirse antes de guardar.</small>
    </div>
    <div class="fleet-field wide"><label for="{{ $formPrefix }}-nombre">Nombre del solicitante</label><input id="{{ $formPrefix }}-nombre" class="form-control" name="solicitante_nombre" maxlength="200" required data-requester-name value="{{ old('solicitante_nombre', $reservaForm?->solicitante_nombre) }}"></div>
    <div class="fleet-field"><label for="{{ $formPrefix }}-email">Correo corporativo</label><input id="{{ $formPrefix }}-email" class="form-control" type="email" name="solicitante_email" maxlength="200" required data-requester-email value="{{ old('solicitante_email', $reservaForm?->solicitante_email) }}" placeholder="nombre@saep.cl"></div>
    <div class="fleet-field"><label for="{{ $formPrefix }}-telefono">Telefono</label><input id="{{ $formPrefix }}-telefono" class="form-control" name="solicitante_telefono" maxlength="50" value="{{ old('solicitante_telefono', $reservaForm?->solicitante_telefono) }}"></div>
    <div class="fleet-field wide">
        <label>Centros de destino</label>
        <details class="fleet-multi-select" data-fleet-destination-select>
            <summary><span data-fleet-destination-label>Selecciona uno o mas centros</span></summary>
            <div class="fleet-multi-options">
                <input class="form-control" type="search" placeholder="Buscar centro..." aria-label="Buscar centro de destino" data-fleet-destination-search>
                <div class="fleet-multi-list">
                    @forelse($centrosDestino as $centro)
                        <label class="fleet-multi-option" data-fleet-destination-option><input type="checkbox" name="destinos[]" value="{{ $centro->id }}" @checked(in_array($centro->id, old('destinos', []))) data-fleet-destination-check><span>{{ $centro->nombre }}@if($centro->codigo)<small>{{ $centro->codigo }}</small>@endif</span></label>
                    @empty
                        <span class="fleet-help">No hay centros activos para seleccionar.</span>
                    @endforelse
                </div>
            </div>
        </details>
    </div>
    <div class="fleet-field wide"><label for="{{ $formPrefix }}-destino-otro">Ruta u otra referencia</label><input id="{{ $formPrefix }}-destino-otro" class="form-control" name="destino_otro" maxlength="300" value="{{ old('destino_otro', $reservaForm?->destino) }}" placeholder="Direccion, ruta o centro no incluido"></div>
    <div class="fleet-field"><label for="{{ $formPrefix }}-motivo">Motivo</label><select id="{{ $formPrefix }}-motivo" class="form-control" name="motivo_id" required><option value="">Selecciona un motivo</option>@foreach($motivosReserva as $motivo)<option value="{{ $motivo->id }}" @selected((string) old('motivo_id', optional($motivosReserva->firstWhere('nombre', $motivoActual))->id) === (string) $motivo->id)>{{ $motivo->nombre }}</option>@endforeach</select></div>
    <div class="fleet-field"><label for="{{ $formPrefix }}-motivo-detalle">Detalle del motivo</label><input id="{{ $formPrefix }}-motivo-detalle" class="form-control" name="motivo_detalle" maxlength="1000" value="{{ old('motivo_detalle', $detalleActual) }}" placeholder="Actividad, carga o referencia"></div>
    <div class="fleet-field"><label for="{{ $formPrefix }}-pasajeros">Pasajeros</label><input id="{{ $formPrefix }}-pasajeros" class="form-control" type="number" name="pasajeros" min="1" max="99" value="{{ old('pasajeros', $reservaForm?->pasajeros) }}"></div>
</div>
