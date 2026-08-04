<?php

namespace App\Http\Controllers;

use App\Jobs\PrepararActaReservaVehiculoKizeo;
use App\Models\ReservaVehiculo;
use App\Services\ReservaVehiculoMicrosoftAuthService;
use App\Services\ReservaVehiculoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservaVehiculoPublicoController extends Controller
{
    public function __construct(
        private readonly ReservaVehiculoMicrosoftAuthService $microsoft,
        private readonly ReservaVehiculoService $reservas,
    ) {}

    public function inicio(Request $request)
    {
        $identidad = $this->microsoft->identidad($request);
        $periodo = $this->periodo($request);
        $agendaSemanalInicio = $this->semana($request, $periodo['inicio']);
        $agendaSemanalTermino = $agendaSemanalInicio->copy()->addWeek();

        return view('reservas_vehiculos.publico.inicio', [
            'identidad' => $identidad,
            'microsoftConfigurado' => $this->microsoft->estaConfigurado(),
            'inicio' => $periodo['inicio'],
            'termino' => $periodo['termino'],
            'inicioInput' => $periodo['inicio_input'],
            'terminoInput' => $periodo['termino_input'],
            'consultaDisponibilidad' => $periodo['consultada'],
            'errorPeriodo' => $periodo['error'],
            'vehiculos' => $identidad && $periodo['consultada']
                ? $this->reservas->vehiculosDisponibles($periodo['inicio'], $periodo['termino'])
                : collect(),
            'agenda' => $identidad && $periodo['consultada']
                ? $this->reservas->agenda($periodo['inicio'], $periodo['termino'])
                : collect(),
            'agendaSemanal' => $identidad
                ? $this->reservas->agenda($agendaSemanalInicio, $agendaSemanalTermino)
                : collect(),
            'agendaSemanalInicio' => $agendaSemanalInicio,
            'agendaSemanalTermino' => $agendaSemanalTermino,
            'margenReservaMinutos' => $this->reservas->margenReservaMinutos(),
            'calendarioPublicadoUrl' => config('services.reservas_vehiculos.public_calendar_url'),
            'misReservas' => $identidad
                ? ReservaVehiculo::query()->with('vehiculo')->where('solicitante_email', $identidad['email'])->latest('inicio')->take(8)->get()
                : collect(),
        ]);
    }

    public function redirectMicrosoft(Request $request)
    {
        try {
            return redirect()->away($this->microsoft->urlAutorizacion($request));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('reservas-vehiculos.inicio')
                ->with('error', 'El acceso corporativo no esta disponible aun. Solicita a Bodega que revise la configuracion Microsoft.');
        }
    }

    public function callbackMicrosoft(Request $request)
    {
        try {
            $this->microsoft->verificarCallback($request);

            return redirect()->route('reservas-vehiculos.inicio')
                ->with('success', 'Cuenta corporativa verificada. Ya puedes reservar un vehiculo.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('reservas-vehiculos.inicio')
                ->with('error', $exception->getMessage());
        }
    }

    public function guardar(Request $request)
    {
        $identidad = $this->microsoft->identidad($request);
        if (! $identidad) {
            return redirect()->route('reservas-vehiculos.inicio')
                ->with('error', 'Debes validar tu cuenta corporativa antes de reservar.');
        }

        $data = $request->validate([
            'vehiculo_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')],
            'inicio' => ['required', 'date', 'after_or_equal:now'],
            'termino' => ['required', 'date', 'after:inicio'],
            'solicitante_telefono' => ['nullable', 'string', 'max:50'],
            'destino' => ['nullable', 'string', 'max:300'],
            'motivo' => ['required', 'string', 'min:8', 'max:2000'],
            'pasajeros' => ['nullable', 'integer', 'min:1', 'max:99'],
        ], [
            'inicio.after_or_equal' => 'La reserva debe comenzar desde la hora actual en adelante.',
            'motivo.min' => 'Indica un motivo de al menos 8 caracteres.',
        ]);

        $reserva = $this->reservas->crearReserva($data, $identidad);

        PrepararActaReservaVehiculoKizeo::dispatch($reserva->id);

        try {
            $this->reservas->enviarConfirmacion($reserva);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()->route('reservas-vehiculos.inicio', [
            'inicio' => $reserva->inicio->format('Y-m-d\\TH:i'),
            'termino' => $reserva->termino->format('Y-m-d\\TH:i'),
        ])->with('success', 'Reserva '.$reserva->codigo.' confirmada. Bodega recibira la ficha de entrega en Kizeo y recibiras un correo de respaldo.');
    }

    public function cancelar(Request $request, ReservaVehiculo $reserva)
    {
        $identidad = $this->microsoft->identidad($request);
        if (! $identidad || strtolower($reserva->solicitante_email) !== strtolower($identidad['email'])) {
            abort(403, 'No puedes cancelar una reserva de otra persona.');
        }

        $data = $request->validate([
            'motivo_cancelacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $reserva = $this->reservas->cancelarReserva($reserva, $identidad, $data['motivo_cancelacion'] ?? null);

        try {
            $this->reservas->enviarActualizacion($reserva, 'cancelacion');
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'La reserva '.$reserva->codigo.' fue cancelada.');
    }

    public function logout(Request $request)
    {
        $this->microsoft->cerrarSesion($request);

        return redirect()->route('reservas-vehiculos.inicio')
            ->with('success', 'Sesion corporativa cerrada.');
    }

    private function periodo(Request $request): array
    {
        $inicioInput = trim((string) $request->input('inicio', ''));
        $terminoInput = trim((string) $request->input('termino', ''));

        if ($inicioInput === '' && $terminoInput === '') {
            return [
                'inicio' => null,
                'termino' => null,
                'inicio_input' => '',
                'termino_input' => '',
                'consultada' => false,
                'error' => null,
            ];
        }

        if ($inicioInput === '' || $terminoInput === '') {
            return [
                'inicio' => null,
                'termino' => null,
                'inicio_input' => $inicioInput,
                'termino_input' => $terminoInput,
                'consultada' => false,
                'error' => 'Selecciona las fechas y horas de inicio y termino para consultar la disponibilidad.',
            ];
        }

        try {
            $inicio = Carbon::parse($inicioInput);
            $termino = Carbon::parse($terminoInput);
        } catch (\Throwable) {
            return [
                'inicio' => null,
                'termino' => null,
                'inicio_input' => $inicioInput,
                'termino_input' => $terminoInput,
                'consultada' => false,
                'error' => 'El rango indicado no tiene un formato de fecha y hora valido.',
            ];
        }

        if ($termino->lessThanOrEqualTo($inicio)) {
            return [
                'inicio' => null,
                'termino' => null,
                'inicio_input' => $inicioInput,
                'termino_input' => $terminoInput,
                'consultada' => false,
                'error' => 'La fecha y hora de termino debe ser posterior al inicio.',
            ];
        }

        return [
            'inicio' => $inicio,
            'termino' => $termino,
            'inicio_input' => $inicio->format('Y-m-d\\TH:i'),
            'termino_input' => $termino->format('Y-m-d\\TH:i'),
            'consultada' => true,
            'error' => null,
        ];
    }

    private function semana(Request $request, ?Carbon $referencia): Carbon
    {
        $valor = trim((string) $request->input('semana', ''));

        try {
            $fecha = $valor !== '' ? Carbon::parse($valor) : ($referencia ?: now());
        } catch (\Throwable) {
            $fecha = $referencia ?: now();
        }

        return $fecha->copy()->startOfWeek(Carbon::MONDAY);
    }
}
