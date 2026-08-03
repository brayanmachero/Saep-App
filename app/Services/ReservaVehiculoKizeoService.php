<?php

namespace App\Services;

use App\Models\ReservaVehiculo;
use App\Models\ReservaVehiculoEvento;
use App\Models\TalanaTrabajador;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReservaVehiculoKizeoService
{
    public function __construct(
        private readonly KizeoService $kizeo,
        private readonly ReservaVehiculoCalendarService $calendar,
    ) {}

    public function estaConfigurado(): bool
    {
        return filled($this->formId())
            && filled($this->recipientUserId())
            && filled($this->reservationCodeField());
    }

    /**
     * Prepara una ficha sin guardar para Bodega. La reserva no se entrega
     * todavia: el cambio de estado ocurre solo al volver el acta firmada.
     */
    public function prepararActa(ReservaVehiculo $reserva, User $operador): ReservaVehiculo
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('La preparacion Kizeo aun no esta configurada para Bodega.');
        }

        $reserva->loadMissing(['vehiculo', 'user']);

        if ($reserva->estado !== 'CONFIRMADA') {
            throw new RuntimeException('Solo se puede preparar el acta Kizeo de una reserva confirmada.');
        }

        if ($reserva->kizeo_pushed_at || $reserva->kizeo_data_id) {
            throw new RuntimeException('Esta reserva ya tiene una ficha Kizeo preparada o recibida.');
        }

        try {
            $payload = $this->pushPayload($reserva);
            $response = $this->kizeo->rawPost('forms/'.$this->formId().'/push', $payload);
            $status = strtolower((string) ($response['status'] ?? 'ok'));

            if (! in_array($status, ['ok', 'success'], true)) {
                throw new RuntimeException((string) ($response['message'] ?? 'Kizeo no pudo preparar la ficha.'));
            }

            $dataId = $this->responseDataId($response);
            $reserva->forceFill([
                'kizeo_form_id' => $this->formId(),
                'kizeo_data_id' => $dataId ?: $reserva->kizeo_data_id,
                'kizeo_pushed_at' => now(),
                'kizeo_last_error' => null,
            ])->save();

            $this->registrarEvento(
                $reserva,
                'KIZEO_ACTA_PREPARADA',
                'Ficha de entrega preparada en Kizeo para Bodega.',
                $operador,
                [
                    'form_id' => $this->formId(),
                    'data_id' => $dataId,
                    'conductor_rut' => $payload['fields']['conductor']['value'] ?? null,
                ],
            );

            return $reserva->fresh('vehiculo');
        } catch (\Throwable $exception) {
            $reserva->forceFill([
                'kizeo_last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            $this->registrarEvento(
                $reserva,
                'KIZEO_ACTA_ERROR',
                'No fue posible preparar la ficha Kizeo: '.mb_substr($exception->getMessage(), 0, 320),
                $operador,
            );

            throw $exception;
        }
    }

    /**
     * Vincula el acta terminada al codigo de reserva recibido desde Kizeo.
     * El codigo evita relacionar registros por patente u horario.
     *
     * @return array{reserva: ?ReservaVehiculo, estado: string}
     */
    public function registrarActaRecibida(
        string $formId,
        string $dataId,
        ?string $codigoReserva,
        string $tipoActa,
        ?string $fechaActa,
        ?string $sharePointPath,
    ): array {
        $codigoReserva = trim((string) $codigoReserva);
        if ($codigoReserva === '' || $codigoReserva === '-') {
            return ['reserva' => null, 'estado' => 'sin_codigo'];
        }

        $reserva = ReservaVehiculo::query()
            ->with('vehiculo')
            ->where('codigo', $codigoReserva)
            ->first();

        if (! $reserva) {
            Log::warning('Acta Kizeo de vehiculo sin reserva SAEP asociada.', [
                'form_id' => $formId,
                'data_id' => $dataId,
                'codigo_reserva' => $codigoReserva,
            ]);

            return ['reserva' => null, 'estado' => 'reserva_no_encontrada'];
        }

        $esDevolucion = str_contains(mb_strtolower($tipoActa), 'devoluci');
        $estadoAnterior = $reserva->estado;
        $nuevoEstado = $this->estadoDesdeActa($reserva, $esDevolucion);
        $fecha = $this->parseFecha($fechaActa) ?: now();
        $estadoCambio = $nuevoEstado !== $estadoAnterior;

        DB::transaction(function () use ($reserva, $formId, $dataId, $sharePointPath, $fecha, $esDevolucion, $nuevoEstado, $estadoCambio, $estadoAnterior) {
            $cambios = [
                'kizeo_form_id' => $formId,
                'kizeo_data_id' => $dataId,
                'kizeo_synced_at' => now(),
                'kizeo_last_error' => null,
            ];

            if ($esDevolucion) {
                $cambios['kizeo_devolucion_sharepoint_path'] = $sharePointPath;
                $cambios['devuelta_at'] = $fecha;
            } else {
                $cambios['kizeo_entrega_sharepoint_path'] = $sharePointPath;
                $cambios['entregada_at'] = $fecha;
            }

            if ($estadoCambio) {
                $cambios['estado'] = $nuevoEstado;
            }

            $reserva->forceFill($cambios)->save();

            $accion = $esDevolucion ? 'KIZEO_DEVOLUCION_REGISTRADA' : 'KIZEO_ENTREGA_REGISTRADA';
            if (! $this->eventoExiste($reserva, $accion, $dataId)) {
                ReservaVehiculoEvento::create([
                    'reserva_vehiculo_id' => $reserva->id,
                    'actor_email' => 'kizeo@saep.cl',
                    'actor_nombre' => 'Kizeo Forms',
                    'accion' => $accion,
                    'resumen' => ($esDevolucion ? 'Devolucion' : 'Entrega').' recibida desde Kizeo. Registro '.$dataId.'.',
                    'cambios' => [
                        'form_id' => $formId,
                        'data_id' => $dataId,
                        'estado_anterior' => $estadoAnterior,
                        'estado_actual' => $nuevoEstado,
                        'sharepoint_path' => $sharePointPath,
                    ],
                ]);
            }
        });

        $reserva = $reserva->fresh('vehiculo');
        if ($estadoCambio) {
            $this->calendar->sincronizar($reserva);
        }

        return ['reserva' => $reserva, 'estado' => $estadoCambio ? 'estado_actualizado' : 'registrada'];
    }

    private function pushPayload(ReservaVehiculo $reserva): array
    {
        $fields = [
            $this->reservationCodeField() => ['value' => $reserva->codigo],
            'gestion' => ['value' => 'Entrega a Conductor'],
            'fecha_y_hora' => ['value' => $reserva->inicio->format('Y-m-d H:i')],
        ];

        if ($marcaModelo = $this->marcaModeloKizeo($reserva)) {
            $fields['marca_modelo'] = ['value' => $marcaModelo];
        }

        // La lista "Personal Vigente" usa el RUT sin puntos como clave. Solo
        // se prellena si el solicitante puede verificarse tambien en Kizeo.
        if ($rutConductor = $this->rutConductorKizeo($reserva)) {
            $fields['conductor'] = ['value' => $rutConductor];
        }

        return [
            'recipient_user_id' => (int) $this->recipientUserId(),
            'planningStart' => $reserva->inicio->format('Y-m-d H:i'),
            'planningEnd' => $reserva->termino->format('Y-m-d H:i'),
            'fields' => $fields,
        ];
    }

    private function marcaModeloKizeo(ReservaVehiculo $reserva): ?string
    {
        $marca = mb_strtolower((string) $reserva->vehiculo?->marca);
        $modelo = mb_strtolower((string) $reserva->vehiculo?->modelo);

        return match (true) {
            str_contains($marca, 'fiat') && str_contains($modelo, 'fiorino') => 'Fiat - Fiorino',
            str_contains($modelo, 'n400') => 'Chevrolt - N400',
            str_contains($modelo, 'sail') => 'Chevrolet SAIL',
            default => null,
        };
    }

    private function rutConductorKizeo(ReservaVehiculo $reserva): ?string
    {
        $email = mb_strtolower(trim((string) $reserva->solicitante_email));
        $candidatos = [$reserva->user?->rut];

        if ($email !== '') {
            $candidatos[] = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->value('rut');

            $candidatos[] = TalanaTrabajador::query()
                ->where('activo', true)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->value('rut');
        }

        $ruts = collect($candidatos)
            ->map(fn ($rut) => $this->normalizarRutKizeo($rut))
            ->filter()
            ->unique()
            ->values();

        if ($ruts->isEmpty()) {
            return null;
        }

        try {
            $personalKizeo = collect($this->kizeo->getPersonalVigente())
                ->mapWithKeys(function (array $trabajador) {
                    $rut = $this->normalizarRutKizeo($trabajador['rut'] ?? $trabajador['id'] ?? null);

                    return $rut ? [$rut => $rut] : [];
                });
        } catch (\Throwable $exception) {
            Log::warning('No fue posible validar al conductor en la lista Kizeo.', [
                'reserva_id' => $reserva->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        foreach ($ruts as $rut) {
            if ($personalKizeo->has($rut)) {
                return $rut;
            }
        }

        return null;
    }

    private function normalizarRutKizeo(mixed $rut): ?string
    {
        if (! is_scalar($rut)) {
            return null;
        }

        $limpio = strtoupper((string) preg_replace('/[^0-9K]/', '', (string) $rut));
        if (strlen($limpio) < 2) {
            return null;
        }

        return substr($limpio, 0, -1).'-'.substr($limpio, -1);
    }

    private function estadoDesdeActa(ReservaVehiculo $reserva, bool $esDevolucion): string
    {
        if ($reserva->estado === 'CANCELADA') {
            return 'CANCELADA';
        }

        if ($esDevolucion) {
            return in_array($reserva->estado, ['CONFIRMADA', 'EN_USO', 'VENCIDA'], true)
                ? 'DEVUELTA'
                : $reserva->estado;
        }

        return in_array($reserva->estado, ['CONFIRMADA', 'VENCIDA'], true)
            ? 'EN_USO'
            : $reserva->estado;
    }

    private function parseFecha(?string $fecha): ?CarbonInterface
    {
        if (! filled($fecha) || $fecha === '-') {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable) {
            return null;
        }
    }

    private function responseDataId(array $response): ?string
    {
        $candidatos = [
            $response['data']['id'] ?? null,
            $response['data']['data_id'] ?? null,
            $response['data_id'] ?? null,
            $response['id'] ?? null,
        ];

        foreach ($candidatos as $candidate) {
            if (is_scalar($candidate) && filled((string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function eventoExiste(ReservaVehiculo $reserva, string $accion, string $dataId): bool
    {
        return $reserva->eventos()
            ->where('accion', $accion)
            ->where('resumen', 'like', '%'.$dataId.'%')
            ->exists();
    }

    private function registrarEvento(
        ReservaVehiculo $reserva,
        string $accion,
        string $resumen,
        User $operador,
        ?array $cambios = null,
    ): void {
        ReservaVehiculoEvento::create([
            'reserva_vehiculo_id' => $reserva->id,
            'user_id' => $operador->id,
            'actor_email' => $operador->email,
            'actor_nombre' => $operador->nombre_completo ?: $operador->name,
            'accion' => $accion,
            'resumen' => $resumen,
            'cambios' => $cambios,
        ]);
    }

    private function formId(): string
    {
        return (string) config('services.kizeo.vehicle_form_id', '');
    }

    private function recipientUserId(): string
    {
        return (string) config('services.kizeo.vehicle_recipient_user_id', '');
    }

    private function reservationCodeField(): string
    {
        return (string) config('services.kizeo.vehicle_reservation_code_field', 'codigo_de_reserva_saep');
    }
}
