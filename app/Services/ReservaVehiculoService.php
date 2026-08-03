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
    public function __construct(private readonly ReservaVehiculoCalendarService $calendar) {}

    public function vehiculosDisponibles(CarbonInterface $inicio, CarbonInterface $termino): Collection
    {
        return Vehiculo::query()
            ->where('estado', 'DISPONIBLE')
            ->where('reservas_habilitadas', true)
            ->whereDoesntHave('reservas', function ($query) use ($inicio, $termino) {
                $query->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                    ->where('inicio', '<', $termino)
                    ->where('termino', '>', $inicio);
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

    public function crearReserva(array $data, array $identidad, ?User $operador = null): ReservaVehiculo
    {
        $inicio = Carbon::parse($data['inicio']);
        $termino = Carbon::parse($data['termino']);

        if ($termino->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'termino' => 'El termino debe ser posterior al inicio de la reserva.',
            ]);
        }

        $reserva = DB::transaction(function () use ($data, $identidad, $operador, $inicio, $termino) {
            $vehiculo = Vehiculo::query()->lockForUpdate()->findOrFail($data['vehiculo_id']);

            if ($vehiculo->estado !== 'DISPONIBLE' || ! $vehiculo->reservas_habilitadas) {
                throw ValidationException::withMessages([
                    'vehiculo_id' => 'Este vehiculo no se encuentra habilitado para reservas.',
                ]);
            }

            $tieneCruce = $vehiculo->reservas()
                ->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                ->where('inicio', '<', $termino)
                ->where('termino', '>', $inicio)
                ->exists();

            if ($tieneCruce) {
                throw ValidationException::withMessages([
                    'vehiculo_id' => 'El vehiculo acaba de ser reservado en ese horario. Selecciona otro vehiculo o ajusta el rango.',
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

    public function enviarConfirmacion(ReservaVehiculo $reserva): void
    {
        $reserva->loadMissing('vehiculo');
        Mail::to($reserva->solicitante_email)->send(new ReservaVehiculoMail($reserva, 'confirmacion'));

        $admins = $this->destinatariosAdministracion()->reject(
            fn (User $user) => strtolower($user->email) === strtolower($reserva->solicitante_email),
        );

        if ($admins->isNotEmpty()) {
            Mail::to($admins->pluck('email')->all())->send(new ReservaVehiculoMail($reserva, 'administracion'));
        }
    }

    public function enviarActualizacion(ReservaVehiculo $reserva, string $tipo): void
    {
        $reserva->loadMissing('vehiculo');

        $destinatarios = $this->destinatariosAdministracion()
            ->pluck('email')
            ->push($reserva->solicitante_email)
            ->filter()
            ->map(fn (string $email) => strtolower($email))
            ->unique()
            ->values()
            ->all();

        if ($destinatarios !== []) {
            Mail::to($destinatarios)->send(new ReservaVehiculoMail($reserva, $tipo));
        }
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
            Mail::to($reserva->solicitante_email)->send(new ReservaVehiculoMail($reserva, 'recordatorio'));
            $reserva->update(['recordatorio_enviado_at' => now()]);
            $this->registrarEvento($reserva, 'RECORDATORIO_ENVIADO', 'Recordatorio de inicio enviado al solicitante.', [
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
                $destinatarios = $this->destinatariosAdministracion()->pluck('email')->push($reserva->solicitante_email)->unique()->values()->all();
                Mail::to($destinatarios)->send(new ReservaVehiculoMail($reserva, 'vencimiento'));
                $reserva->update(['vencimiento_notificado_at' => now()]);
            }
        }

        return [
            'recordatorios' => $recordatorios->count(),
            'vencidas' => $vencidas->count(),
        ];
    }

    public function destinatariosAdministracion(): Collection
    {
        return User::query()
            ->where('activo', true)
            ->whereNotNull('email')
            ->whereHas('rol', function ($query) {
                $query->where(function ($roles) {
                    $roles->where('codigo', 'SUPER_ADMIN')
                        ->orWhereHas('modulos', function ($modules) {
                            $modules->where('slug', 'gestion_vehiculos')
                                ->where('rol_modulo.puede_ver', true);
                        });
                });
            })
            ->get();
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
