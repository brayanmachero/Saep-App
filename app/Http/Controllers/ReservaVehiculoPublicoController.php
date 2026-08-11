<?php

namespace App\Http\Controllers;

use App\Jobs\PrepararActaReservaVehiculoKizeo;
use App\Models\CentroCosto;
use App\Models\ReservaVehiculo;
use App\Models\ReservaVehiculoMotivo;
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
            'minimoInicioInput' => $this->reservas->minimoInicioPortal()->format('Y-m-d\\TH:i'),
            'mensajeCortePortal' => $this->reservas->mensajeCortePortal(),
            'duracionReservaPredeterminadaMinutos' => $this->reservas->duracionReservaPredeterminadaMinutos(),
            'rangoPredeterminado' => $periodo['predeterminado'],
            'consultaDisponibilidad' => $periodo['consultada'],
            'errorPeriodo' => $periodo['error'],
            'vehiculos' => $identidad && $periodo['consultada']
                ? $this->reservas->vehiculosDisponibles($periodo['inicio'], $periodo['termino'])
                : collect(),
            'agenda' => $identidad && $periodo['consultada']
                ? $this->reservas->agenda($periodo['inicio'], $periodo['termino'])
                : collect(),
            'agendaSemanal' => $identidad
                ? $this->reservas->agendaSemanal($agendaSemanalInicio, $agendaSemanalTermino)
                : collect(),
            'agendaSemanalInicio' => $agendaSemanalInicio,
            'agendaSemanalTermino' => $agendaSemanalTermino,
            'margenReservaMinutos' => $this->reservas->margenReservaMinutos(),
            'calendarioPublicadoUrl' => config('services.reservas_vehiculos.public_calendar_url'),
            'centrosDestino' => CentroCosto::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'motivosReserva' => ReservaVehiculoMotivo::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(),
            'misReservas' => $identidad
                ? ReservaVehiculo::query()->with('vehiculo')->where('solicitante_email', $identidad['email'])->latest('inicio')->take(8)->get()
                : collect(),
            'reservasEnCurso' => $identidad
                ? ReservaVehiculo::query()
                    ->with(['vehiculo', 'eventos' => fn ($query) => $query->latest()->take(4)])
                    ->whereRaw('LOWER(solicitante_email) = ?', [strtolower($identidad['email'])])
                    ->whereIn('estado', ['EN_USO', 'VENCIDA'])
                    ->orderByRaw("CASE WHEN estado = 'VENCIDA' THEN 0 ELSE 1 END")
                    ->orderBy('termino')
                    ->get()
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
            'destinos' => ['nullable', 'array', 'max:12'],
            'destinos.*' => ['integer', Rule::exists('centros_costo', 'id')->where('activo', true)],
            'destino_otro' => ['nullable', 'string', 'max:300'],
            'motivo_id' => ['required', 'integer', Rule::exists('reserva_vehiculo_motivos', 'id')->where('activo', true)],
            'motivo_detalle' => ['nullable', 'string', 'max:1000'],
            'pasajeros' => ['nullable', 'integer', 'min:1', 'max:99'],
        ], [
            'inicio.after_or_equal' => 'La reserva debe comenzar desde la hora actual en adelante.',
        ]);

        $this->reservas->validarInicioPortal($data['inicio']);
        $data = $this->prepararDatosReserva($data);

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

    public function reportarEventualidad(Request $request, ReservaVehiculo $reserva)
    {
        $identidad = $this->validarSolicitante($request, $reserva);

        $data = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(ReservaVehiculo::TIPOS_EVENTUALIDAD))],
            'descripcion' => ['required', 'string', 'min:8', 'max:2000'],
            'fecha_estimada_devolucion' => ['nullable', 'date', 'after:now'],
        ], [
            'descripcion.min' => 'Describe la eventualidad en al menos 8 caracteres.',
            'fecha_estimada_devolucion.after' => 'La hora estimada de devolucion debe ser posterior a la hora actual.',
        ]);

        if ($data['tipo'] === 'RETRASO_DEVOLUCION' && empty($data['fecha_estimada_devolucion'])) {
            return back()->withErrors([
                'fecha_estimada_devolucion' => 'Indica la hora estimada de devolucion para informar el retraso.',
            ])->withInput();
        }

        $reserva = $this->reservas->reportarEventualidad($reserva, $data, $identidad);

        try {
            $this->reservas->enviarActualizacion($reserva, 'eventualidad', [
                'tipo_label' => ReservaVehiculo::TIPOS_EVENTUALIDAD[$data['tipo']],
                'descripcion' => $data['descripcion'],
                'fecha_estimada_devolucion' => $data['fecha_estimada_devolucion'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Eventualidad informada a Bodega. El aviso quedo registrado en tu reserva.');
    }

    public function ampliar(Request $request, ReservaVehiculo $reserva)
    {
        $identidad = $this->validarSolicitante($request, $reserva);

        $data = $request->validate([
            'termino' => ['required', 'date'],
            'motivo' => ['required', 'string', 'min:8', 'max:1000'],
        ], [
            'motivo.min' => 'Indica el motivo de la ampliacion en al menos 8 caracteres.',
        ]);

        $reserva = $this->reservas->ampliarReserva($reserva, $data, $identidad);

        try {
            $this->reservas->enviarActualizacion($reserva, 'ampliacion', [
                'motivo' => $data['motivo'],
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Horario ampliado hasta el '.$reserva->termino->format('d/m/Y H:i').'. Bodega fue notificada.');
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
        $minimoInicio = $this->reservas->minimoInicioPortal();

        if ($inicioInput === '' && $terminoInput === '') {
            $inicio = $minimoInicio->copy()->isSameDay(now())
                ? $minimoInicio->copy()->startOfHour()->addHour()
                : $minimoInicio->copy();
            $termino = $inicio->copy()->addMinutes($this->reservas->duracionReservaPredeterminadaMinutos());

            return [
                'inicio' => $inicio,
                'termino' => $termino,
                'inicio_input' => $inicio->format('Y-m-d\\TH:i'),
                'termino_input' => $termino->format('Y-m-d\\TH:i'),
                'consultada' => true,
                'predeterminado' => true,
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
                'predeterminado' => false,
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
                'predeterminado' => false,
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
                'predeterminado' => false,
                'error' => 'La fecha y hora de termino debe ser posterior al inicio.',
            ];
        }

        if ($inicio->lessThan($minimoInicio)) {
            return [
                'inicio' => null,
                'termino' => null,
                'inicio_input' => $inicioInput,
                'termino_input' => $terminoInput,
                'consultada' => false,
                'predeterminado' => false,
                'error' => $this->reservas->mensajeCortePortal(),
            ];
        }

        return [
            'inicio' => $inicio,
            'termino' => $termino,
            'inicio_input' => $inicio->format('Y-m-d\\TH:i'),
            'termino_input' => $termino->format('Y-m-d\\TH:i'),
            'consultada' => true,
            'predeterminado' => false,
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

    private function validarSolicitante(Request $request, ReservaVehiculo $reserva): array
    {
        $identidad = $this->microsoft->identidad($request);
        if (! $identidad || strtolower($reserva->solicitante_email) !== strtolower($identidad['email'])) {
            abort(403, 'No puedes gestionar una reserva de otra persona.');
        }

        return $identidad;
    }

    private function prepararDatosReserva(array $data): array
    {
        $centros = CentroCosto::query()
            ->where('activo', true)
            ->whereIn('id', $data['destinos'] ?? [])
            ->orderBy('nombre')
            ->pluck('nombre');
        $destino = $centros
            ->push(trim((string) ($data['destino_otro'] ?? '')))
            ->filter()
            ->unique()
            ->implode(' · ');
        $motivo = ReservaVehiculoMotivo::query()->where('activo', true)->findOrFail($data['motivo_id']);
        $detalle = trim((string) ($data['motivo_detalle'] ?? ''));

        $data['destino'] = $destino !== '' ? $destino : null;
        $data['motivo'] = $motivo->nombre.($detalle !== '' ? ' — '.$detalle : '');

        return $data;
    }
}
