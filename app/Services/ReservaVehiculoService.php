<?php

namespace App\Services;

use App\Mail\ReservaVehiculoMail;
use App\Models\ReservaVehiculo;
use App\Models\ReservaVehiculoEvento;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReservaVehiculoService
{
    public function __construct(
        private readonly ReservaVehiculoCalendarService $calendar,
        private readonly ReservaVehiculoTeamsService $teams,
    ) {}

    public function vehiculosDisponibles(CarbonInterface $inicio, CarbonInterface $termino): Collection
    {
        [$inicioBloqueo, $terminoBloqueo] = $this->periodoConMargen($inicio, $termino);

        return Vehiculo::query()
            ->where('estado', 'DISPONIBLE')
            ->where('reservas_habilitadas', true)
            ->whereDoesntHave('reservas', function ($query) use ($inicioBloqueo, $terminoBloqueo) {
                $query->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                    ->where('inicio', '<', $terminoBloqueo)
                    ->where('termino', '>', $inicioBloqueo);
            })
            ->orderBy('sede')
            ->orderBy('patente')
            ->get();
    }

    /**
     * Agenda interna del rango consultado. No expone solicitante ni motivo en el portal.
     */
    public function agenda(CarbonInterface $inicio, CarbonInterface $termino): Collection
    {
        return ReservaVehiculo::query()
            ->with('vehiculo')
            ->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
            ->where('inicio', '<', $termino)
            ->where('termino', '>', $inicio)
            ->orderBy('inicio')
            ->orderBy('vehiculo_id')
            ->get();
    }

    /**
     * Historial semanal de la flota. Incluye reservas devueltas para que la
     * agenda refleje las ocupaciones ya finalizadas sin afectar disponibilidad.
     */
    public function agendaSemanal(CarbonInterface $inicio, CarbonInterface $termino): Collection
    {
        return ReservaVehiculo::query()
            ->with('vehiculo')
            ->whereIn('estado', ReservaVehiculo::ESTADOS_VISIBLES_EN_AGENDA)
            ->where('inicio', '<', $termino)
            ->where('termino', '>', $inicio)
            ->orderBy('inicio')
            ->orderBy('vehiculo_id')
            ->get();
    }

    public function margenReservaMinutos(): int
    {
        return max(0, min(240, (int) config('services.reservas_vehiculos.buffer_minutes', 60)));
    }

    public function duracionReservaPredeterminadaMinutos(): int
    {
        return max(30, min(480, (int) config('services.reservas_vehiculos.default_duration_minutes', 60)));
    }

    /**
     * El portal respeta el corte operativo diario sin limitar las reservas que
     * Bodega debe registrar o corregir desde la administracion interna.
     */
    public function minimoInicioPortal(?CarbonInterface $referencia = null): Carbon
    {
        $ahora = $referencia ? Carbon::instance($referencia) : now();
        $horaCorte = max(0, min(23, (int) config('services.reservas_vehiculos.same_day_cutoff_hour', 16)));

        return $ahora->hour >= $horaCorte
            ? $ahora->copy()->addDay()->startOfDay()
            : $ahora->copy();
    }

    public function validarInicioPortal(CarbonInterface|string $inicio): void
    {
        $fecha = $inicio instanceof CarbonInterface ? Carbon::instance($inicio) : Carbon::parse($inicio);
        $minimo = $this->minimoInicioPortal();

        if ($fecha->lessThan($minimo)) {
            throw ValidationException::withMessages([
                'inicio' => $this->mensajeCortePortal(),
            ]);
        }
    }

    public function mensajeCortePortal(): string
    {
        return 'Despues de las '.str_pad((string) max(0, min(23, (int) config('services.reservas_vehiculos.same_day_cutoff_hour', 16))), 2, '0', STR_PAD_LEFT).':00, las reservas deben comenzar desde el dia siguiente.';
    }

    public function crearReserva(array $data, array $identidad, ?User $operador = null): ReservaVehiculo
    {
        $inicio = Carbon::parse($data['inicio']);
        $termino = Carbon::parse($data['termino']);

        if ($inicio->lessThan(now())) {
            throw ValidationException::withMessages([
                'inicio' => 'La reserva debe comenzar desde la hora actual en adelante.',
            ]);
        }

        if ($termino->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'termino' => 'El termino debe ser posterior al inicio de la reserva.',
            ]);
        }

        [$inicioBloqueo, $terminoBloqueo] = $this->periodoConMargen($inicio, $termino);

        $reserva = DB::transaction(function () use ($data, $identidad, $operador, $inicio, $termino, $inicioBloqueo, $terminoBloqueo) {
            $vehiculo = Vehiculo::query()->lockForUpdate()->findOrFail($data['vehiculo_id']);

            if ($vehiculo->estado !== 'DISPONIBLE' || ! $vehiculo->reservas_habilitadas) {
                throw ValidationException::withMessages([
                    'vehiculo_id' => 'Este vehiculo no se encuentra habilitado para reservas.',
                ]);
            }

            $tieneCruce = $vehiculo->reservas()
                ->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                ->where('inicio', '<', $terminoBloqueo)
                ->where('termino', '>', $inicioBloqueo)
                ->exists();

            if ($tieneCruce) {
                throw ValidationException::withMessages([
                    'vehiculo_id' => 'El vehiculo no esta disponible: se requiere un margen operativo de '.$this->margenReservaMinutos().' minutos entre reservas. Selecciona otro vehiculo o ajusta el rango.',
                ]);
            }

            $reserva = ReservaVehiculo::create([
                'vehiculo_id' => $vehiculo->id,
                'user_id' => $operador?->id,
                'solicitante_oid' => $identidad['oid'] ?? null,
                'solicitante_email' => strtolower((string) $identidad['email']),
                'solicitante_nombre' => $identidad['name'],
                'solicitante_telefono' => $data['solicitante_telefono'] ?? null,
                'inicio' => $inicio,
                'termino' => $termino,
                'destino' => $data['destino'] ?? null,
                'motivo' => $data['motivo'],
                'pasajeros' => $data['pasajeros'] ?? null,
                'estado' => 'CONFIRMADA',
            ]);

            $reserva->update([
                'codigo' => 'RV-'.$inicio->format('Y').'-'.str_pad((string) $reserva->id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->registrarEvento($reserva, 'RESERVA_CREADA', 'Reserva confirmada desde el portal.', $identidad, $operador, [
                'inicio' => $inicio->toDateTimeString(),
                'termino' => $termino->toDateTimeString(),
                'vehiculo' => $vehiculo->patente,
            ]);

            return $reserva->fresh('vehiculo');
        });

        $this->sincronizarCalendario($reserva, 'Reserva creada o actualizada en el calendario compartido.');

        if ($this->teams->notificarNuevaReserva($reserva)) {
            $this->registrarEvento($reserva, 'TEAMS_NOTIFICADO', 'Nueva reserva notificada al canal de Bodega en Teams.', [
                'email' => 'sistema@saep.cl',
                'name' => 'Sistema SAEP',
            ]);
        }

        return $reserva->fresh('vehiculo');
    }

    public function cancelarReserva(ReservaVehiculo $reserva, array $identidad, ?string $motivo = null, ?User $operador = null): ReservaVehiculo
    {
        if (! in_array($reserva->estado, ['CONFIRMADA', 'EN_USO'], true)) {
            throw ValidationException::withMessages([
                'reserva' => 'Solo se pueden cancelar reservas confirmadas o que estan en uso.',
            ]);
        }

        $reserva->update([
            'estado' => 'CANCELADA',
            'cancelada_at' => now(),
            'cancelada_por_email' => $identidad['email'] ?? $operador?->email,
            'motivo_cancelacion' => $motivo,
        ]);

        $this->registrarEvento($reserva, 'RESERVA_CANCELADA', 'Reserva cancelada.', $identidad, $operador, [
            'motivo' => $motivo,
        ]);

        $reserva = $reserva->fresh('vehiculo');
        $this->sincronizarCalendario($reserva, 'Cancelacion informada al calendario compartido.');

        return $reserva;
    }

    /**
     * Registra un aviso del solicitante mientras mantiene la custodia del movil.
     * La reserva no se modifica: el aviso queda disponible para Bodega y se
     * notifica por correo de inmediato.
     */
    public function reportarEventualidad(ReservaVehiculo $reserva, array $data, array $identidad): ReservaVehiculo
    {
        if (! in_array($reserva->estado, ['EN_USO', 'VENCIDA'], true)) {
            throw ValidationException::withMessages([
                'reserva' => 'Solo puedes reportar eventualidades de una reserva que esta en uso o vencida.',
            ]);
        }

        $tipo = (string) $data['tipo'];
        if (! array_key_exists($tipo, ReservaVehiculo::TIPOS_EVENTUALIDAD)) {
            throw ValidationException::withMessages(['tipo' => 'Selecciona un tipo de eventualidad valido.']);
        }

        $devolucionEstimada = filled($data['fecha_estimada_devolucion'] ?? null)
            ? Carbon::parse($data['fecha_estimada_devolucion'])
            : null;

        $this->registrarEvento(
            $reserva,
            'EVENTUALIDAD_REPORTADA',
            'El solicitante reporto: '.ReservaVehiculo::TIPOS_EVENTUALIDAD[$tipo].'.',
            $identidad,
            null,
            [
                'tipo' => $tipo,
                'tipo_label' => ReservaVehiculo::TIPOS_EVENTUALIDAD[$tipo],
                'descripcion' => trim((string) $data['descripcion']),
                'fecha_estimada_devolucion' => $devolucionEstimada?->toDateTimeString(),
            ],
        );

        return $reserva->fresh('vehiculo');
    }

    /**
     * Amplia una reserva en curso solo cuando el nuevo tramo, incluido el
     * margen operativo, no afecta otra reserva de la misma unidad.
     */
    public function ampliarReserva(ReservaVehiculo $reserva, array $data, array $identidad): ReservaVehiculo
    {
        $nuevoTermino = Carbon::parse($data['termino']);

        $reserva = DB::transaction(function () use ($reserva, $nuevoTermino, $data, $identidad) {
            $bloqueada = ReservaVehiculo::query()
                ->with('vehiculo')
                ->lockForUpdate()
                ->findOrFail($reserva->id);

            if (! in_array($bloqueada->estado, ['EN_USO', 'VENCIDA'], true)) {
                throw ValidationException::withMessages([
                    'reserva' => 'Solo puedes ampliar una reserva que esta en uso o vencida.',
                ]);
            }

            $terminoAnterior = $bloqueada->termino->copy();
            if ($nuevoTermino->lessThanOrEqualTo($terminoAnterior)) {
                throw ValidationException::withMessages([
                    'termino' => 'La nueva hora de termino debe ser posterior al horario reservado actualmente.',
                ]);
            }

            $vehiculo = Vehiculo::query()->lockForUpdate()->findOrFail($bloqueada->vehiculo_id);
            [, $terminoBloqueo] = $this->periodoConMargen($bloqueada->inicio, $nuevoTermino);
            $inicioBloqueo = $bloqueada->inicio->copy()->subMinutes($this->margenReservaMinutos());

            $tieneCruce = $vehiculo->reservas()
                ->whereKeyNot($bloqueada->id)
                ->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                ->where('inicio', '<', $terminoBloqueo)
                ->where('termino', '>', $inicioBloqueo)
                ->exists();

            if ($tieneCruce) {
                throw ValidationException::withMessages([
                    'termino' => 'No es posible ampliar este horario porque afectaria otra reserva o su margen operativo de '.$this->margenReservaMinutos().' minutos. Reporta la eventualidad para que Bodega pueda coordinar contigo.',
                ]);
            }

            $estadoAnterior = $bloqueada->estado;
            $bloqueada->update([
                'termino' => $nuevoTermino,
                // Una extension vigente regulariza una reserva que el proceso
                // automatico pudo marcar como vencida antes del aviso.
                'estado' => $estadoAnterior === 'VENCIDA' ? 'EN_USO' : $estadoAnterior,
            ]);

            $this->registrarEvento(
                $bloqueada,
                'RESERVA_AMPLIADA',
                'Reserva ampliada por el solicitante desde el portal.',
                $identidad,
                null,
                [
                    'termino_anterior' => $terminoAnterior->toDateTimeString(),
                    'termino_nuevo' => $nuevoTermino->toDateTimeString(),
                    'motivo' => trim((string) $data['motivo']),
                    'estado_anterior' => $estadoAnterior,
                ],
            );

            return $bloqueada->fresh('vehiculo');
        });

        $this->sincronizarCalendario($reserva, 'Ampliacion registrada desde el portal de reservas.');

        return $reserva;
    }

    public function actualizarEstado(ReservaVehiculo $reserva, string $estado, User $operador): ReservaVehiculo
    {
        if (! array_key_exists($estado, ReservaVehiculo::ESTADOS)) {
            throw ValidationException::withMessages(['estado' => 'El estado indicado no es valido.']);
        }

        $anterior = $reserva->estado;
        $reserva->update(['estado' => $estado]);

        $this->registrarEvento($reserva, 'ESTADO_ACTUALIZADO', 'Estado actualizado a '.ReservaVehiculo::ESTADOS[$estado].'.', [
            'email' => $operador->email,
            'name' => $operador->nombre_completo ?: $operador->name,
        ], $operador, ['anterior' => $anterior, 'actual' => $estado]);

        $reserva = $reserva->fresh('vehiculo');
        $this->sincronizarCalendario($reserva, 'Estado actualizado en el calendario compartido.');

        return $reserva;
    }

    /**
     * Bodega puede reprogramar una reserva confirmada mientras la ficha Kizeo
     * siga pendiente. El bloqueo aplica solo cuando Kizeo devolvio un acta
     * firmada de entrega o devolución, para mantener la trazabilidad real.
     */
    public function reprogramarReserva(ReservaVehiculo $reserva, array $data, array $identidad, User $operador): ReservaVehiculo
    {
        $inicio = Carbon::parse($data['inicio']);
        $termino = Carbon::parse($data['termino']);

        if ($inicio->lessThan(now())) {
            throw ValidationException::withMessages([
                'inicio' => 'La nueva hora de inicio debe ser igual o posterior a la hora actual.',
            ]);
        }

        if ($termino->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'termino' => 'El termino debe ser posterior al inicio de la reserva.',
            ]);
        }

        $reserva = DB::transaction(function () use ($reserva, $data, $identidad, $operador, $inicio, $termino) {
            $bloqueada = ReservaVehiculo::query()->lockForUpdate()->findOrFail($reserva->id);

            if ($bloqueada->estado !== 'CONFIRMADA') {
                throw ValidationException::withMessages([
                    'reserva' => 'Solo se puede reprogramar una reserva confirmada. Las reservas en uso o cerradas se mantienen como trazabilidad operativa.',
                ]);
            }

            if ($bloqueada->tieneActaKizeoCompletada()) {
                throw ValidationException::withMessages([
                    'reserva' => 'Esta reserva ya tiene un acta Kizeo de entrega o devolución registrada. No se puede cambiar vehículo, fecha u horario para mantener la trazabilidad de la entrega.',
                ]);
            }

            $vehiculoIds = collect([$bloqueada->vehiculo_id, (int) $data['vehiculo_id']])->unique()->sort()->values();
            $vehiculos = Vehiculo::query()->whereIn('id', $vehiculoIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $vehiculo = $vehiculos->get((int) $data['vehiculo_id']);

            if (! $vehiculo || $vehiculo->estado !== 'DISPONIBLE' || ! $vehiculo->reservas_habilitadas) {
                throw ValidationException::withMessages([
                    'vehiculo_id' => 'El vehiculo seleccionado no se encuentra habilitado para reservas.',
                ]);
            }

            [$inicioBloqueo, $terminoBloqueo] = $this->periodoConMargen($inicio, $termino);
            $tieneCruce = $vehiculo->reservas()
                ->whereKeyNot($bloqueada->id)
                ->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                ->where('inicio', '<', $terminoBloqueo)
                ->where('termino', '>', $inicioBloqueo)
                ->exists();

            if ($tieneCruce) {
                throw ValidationException::withMessages([
                    'vehiculo_id' => 'El vehiculo no esta disponible en este rango: se requiere un margen operativo de '.$this->margenReservaMinutos().' minutos entre reservas.',
                ]);
            }

            $anterior = [
                'vehiculo_id' => $bloqueada->vehiculo_id,
                'inicio' => $bloqueada->inicio->toDateTimeString(),
                'termino' => $bloqueada->termino->toDateTimeString(),
                'solicitante_nombre' => $bloqueada->solicitante_nombre,
                'solicitante_email' => $bloqueada->solicitante_email,
                'destino' => $bloqueada->destino,
                'motivo' => $bloqueada->motivo,
            ];

            $bloqueada->update([
                'vehiculo_id' => $vehiculo->id,
                'solicitante_oid' => $identidad['oid'] ?? null,
                'solicitante_email' => strtolower((string) $identidad['email']),
                'solicitante_nombre' => $identidad['name'],
                'solicitante_telefono' => $data['solicitante_telefono'] ?? null,
                'inicio' => $inicio,
                'termino' => $termino,
                'destino' => $data['destino'] ?? null,
                'motivo' => $data['motivo'],
                'pasajeros' => $data['pasajeros'] ?? null,
            ]);

            $this->registrarEvento(
                $bloqueada,
                'RESERVA_REPROGRAMADA_BODEGA',
                'Reserva reprogramada por Bodega.',
                $identidad,
                $operador,
                [
                    'anterior' => $anterior,
                    'actual' => [
                        'vehiculo_id' => $vehiculo->id,
                        'inicio' => $inicio->toDateTimeString(),
                        'termino' => $termino->toDateTimeString(),
                        'solicitante_nombre' => $identidad['name'],
                        'solicitante_email' => strtolower((string) $identidad['email']),
                        'destino' => $data['destino'] ?? null,
                        'motivo' => $data['motivo'],
                    ],
                ],
            );

            return $bloqueada->fresh('vehiculo');
        });

        $this->sincronizarCalendario($reserva, 'Reserva reprogramada por Bodega en el calendario compartido.');

        return $reserva;
    }

    public function enviarConfirmacion(ReservaVehiculo $reserva): void
    {
        $this->enviarAlSolicitanteConCopiaBodega($reserva, 'confirmacion');
    }

    public function enviarActualizacion(ReservaVehiculo $reserva, string $tipo, ?array $contexto = null): void
    {
        $this->enviarAlSolicitanteConCopiaBodega($reserva, $tipo, null, $contexto);
    }

    public function enviarReprogramacion(ReservaVehiculo $reserva, User $operador): void
    {
        $this->enviarAlSolicitanteConCopiaBodega($reserva, 'reprogramacion', $operador);
    }

    public function enviarEliminacion(ReservaVehiculo $reserva, User $operador): void
    {
        $this->enviarAlSolicitanteConCopiaBodega($reserva, 'eliminacion', $operador);
    }

    public function procesarNotificaciones(): array
    {
        $ahora = now();
        $recordatorios = ReservaVehiculo::query()
            ->with('vehiculo')
            ->where('estado', 'CONFIRMADA')
            ->whereNull('recordatorio_enviado_at')
            ->whereBetween('inicio', [$ahora, $ahora->copy()->addMinutes(75)])
            ->get();

        foreach ($recordatorios as $reserva) {
            $this->enviarAlSolicitanteConCopiaBodega($reserva, 'recordatorio');
            $reserva->update(['recordatorio_enviado_at' => now()]);
            $this->registrarEvento($reserva, 'RECORDATORIO_ENVIADO', 'Recordatorio de inicio enviado al solicitante con copia a gestores de Bodega.', [
                'email' => 'sistema@saep.cl',
                'name' => 'Sistema SAEP',
            ]);
        }

        $vencidas = ReservaVehiculo::query()
            ->with('vehiculo')
            ->whereIn('estado', ['CONFIRMADA', 'EN_USO'])
            ->where('termino', '<=', $ahora)
            ->get();

        foreach ($vencidas as $reserva) {
            $reserva->update(['estado' => 'VENCIDA']);
            $this->registrarEvento($reserva, 'RESERVA_VENCIDA', 'La reserva supero su hora de termino.', [
                'email' => 'sistema@saep.cl',
                'name' => 'Sistema SAEP',
            ]);
            $this->sincronizarCalendario($reserva->fresh('vehiculo'), 'Vencimiento actualizado en el calendario compartido.');

            if (! $reserva->vencimiento_notificado_at) {
                $this->enviarAlSolicitanteConCopiaBodega($reserva, 'vencimiento');
                $reserva->update(['vencimiento_notificado_at' => now()]);
            }
        }

        return [
            'recordatorios' => $recordatorios->count(),
            'vencidas' => $vencidas->count(),
        ];
    }

    public function destinatariosGestionBodega(): Collection
    {
        return User::query()
            ->where('activo', true)
            ->whereNotNull('email')
            ->whereHas('rol', fn ($query) => $query->whereIn('codigo', [
                'BODEGA_ENTREGAS',
                'BODEGA_VEHICULOS',
            ]))
            ->get();
    }

    /**
     * El solicitante es el destinatario principal. Los gestores de Bodega reciben
     * el mismo aviso en copia, sin incluir superadministradores por defecto.
     */
    private function enviarAlSolicitanteConCopiaBodega(ReservaVehiculo $reserva, string $tipo, ?User $actor = null, ?array $contexto = null): void
    {
        $reserva->loadMissing('vehiculo');

        $solicitante = strtolower(trim((string) $reserva->solicitante_email));
        if ($solicitante === '') {
            return;
        }

        $gestores = $this->destinatariosGestionBodega()
            ->pluck('email')
            ->filter()
            ->map(fn (string $email) => strtolower(trim($email)))
            ->reject(fn (string $email) => $email === $solicitante)
            ->unique()
            ->values();

        $correo = Mail::to($solicitante);
        if ($gestores->isNotEmpty()) {
            $correo->cc($gestores->all());
        }

        $correo->send(new ReservaVehiculoMail($reserva, $tipo, $actor, $contexto));
    }

    private function sincronizarCalendario(ReservaVehiculo $reserva, string $resumen): void
    {
        $resultado = $this->calendar->sincronizar($reserva);

        if ($resultado['estado'] !== 'sincronizado') {
            return;
        }

        $this->registrarEvento($reserva, 'CALENDARIO_SINCRONIZADO', $resumen, [
            'email' => 'sistema@saep.cl',
            'name' => 'Sistema SAEP',
        ], null, ['calendar_event_id' => $reserva->calendar_event_id]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodoConMargen(CarbonInterface $inicio, CarbonInterface $termino): array
    {
        $margen = $this->margenReservaMinutos();

        return [
            Carbon::instance($inicio)->subMinutes($margen),
            Carbon::instance($termino)->addMinutes($margen),
        ];
    }

    private function registrarEvento(
        ReservaVehiculo $reserva,
        string $accion,
        string $resumen,
        array $identidad = [],
        ?User $operador = null,
        ?array $cambios = null,
    ): void {
        ReservaVehiculoEvento::create([
            'reserva_vehiculo_id' => $reserva->id,
            'user_id' => $operador?->id,
            'actor_oid' => $identidad['oid'] ?? null,
            'actor_email' => $identidad['email'] ?? $operador?->email,
            'actor_nombre' => $identidad['name'] ?? ($operador?->nombre_completo ?: $operador?->name),
            'accion' => $accion,
            'resumen' => $resumen,
            'cambios' => $cambios,
        ]);
    }
}
