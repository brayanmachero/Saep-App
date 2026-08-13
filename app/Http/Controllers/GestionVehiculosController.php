<?php

namespace App\Http\Controllers;

use App\Jobs\PrepararActaReservaVehiculoKizeo;
use App\Models\CentroCosto;
use App\Models\ReservaVehiculo;
use App\Models\ReservaVehiculoMotivo;
use App\Models\SolicitanteReservaVehiculo;
use App\Models\Vehiculo;
use App\Services\ReservaVehiculoCalendarService;
use App\Services\ReservaVehiculoKizeoService;
use App\Services\ReservaVehiculoService;
use App\Services\OneDriveService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GestionVehiculosController extends Controller
{
    public function __construct(
        private readonly ReservaVehiculoService $reservas,
        private readonly ReservaVehiculoCalendarService $calendar,
        private readonly ReservaVehiculoKizeoService $kizeo,
        private readonly OneDriveService $oneDrive,
    ) {}

    public function index(Request $request)
    {
        $estado = $request->input('estado');
        $buscar = trim((string) $request->input('buscar'));

        $vehiculos = Vehiculo::query()
            ->withCount(['reservas as reservas_vigentes_count' => function ($query) {
                $query->whereIn('estado', ReservaVehiculo::ESTADOS_BLOQUEANTES)
                    ->where('termino', '>=', now());
            }])
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($vehicles) use ($buscar) {
                    $vehicles->where('patente', 'like', '%'.$buscar.'%')
                        ->orWhere('nombre', 'like', '%'.$buscar.'%')
                        ->orWhere('marca', 'like', '%'.$buscar.'%')
                        ->orWhere('modelo', 'like', '%'.$buscar.'%')
                        ->orWhere('sede', 'like', '%'.$buscar.'%');
                });
            })
            ->orderBy('sede')
            ->orderBy('patente')
            ->get();

        $reservasEnCurso = ReservaVehiculo::query()
            ->with(['vehiculo', 'eventos' => fn ($query) => $query->take(1)])
            ->whereIn('estado', ['CONFIRMADA', 'EN_USO', 'VENCIDA'])
            ->orderBy('inicio')
            ->take(30)
            ->get();

        $reservasGestionadas = ReservaVehiculo::query()
            ->with('vehiculo')
            ->whereIn('estado', ['EN_USO', 'VENCIDA', 'DEVUELTA', 'CANCELADA'])
            ->latest('updated_at')
            ->take(50)
            ->get();

        // Mantiene la bandeja operativa existente mientras la trazabilidad se muestra aparte.
        $proximasReservas = $reservasEnCurso;

        $solicitantes = SolicitanteReservaVehiculo::query()->orderBy('nombre')->get();
        $semanaCalendario = $this->semanaCalendario($request);
        $agendaCalendario = $this->reservas->agendaSemanal(
            $semanaCalendario,
            $semanaCalendario->copy()->addWeek(),
        );
        $vehiculosReservables = Vehiculo::query()
            ->where('estado', 'DISPONIBLE')
            ->where('reservas_habilitadas', true)
            ->orderBy('sede')
            ->orderBy('patente')
            ->get();
        $centrosDestino = CentroCosto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
        $motivosReserva = ReservaVehiculoMotivo::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
        $motivosGestion = ReservaVehiculoMotivo::query()
            ->orderBy('activo', 'desc')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('gestion_vehiculos.index', compact(
            'vehiculos',
            'proximasReservas',
            'reservasEnCurso',
            'reservasGestionadas',
            'solicitantes',
            'semanaCalendario',
            'agendaCalendario',
            'vehiculosReservables',
            'centrosDestino',
            'motivosReserva',
            'motivosGestion',
            'estado',
            'buscar',
        ) + [
            'calendarConfigurado' => $this->calendar->estaConfigurado(),
            'kizeoConfigurado' => $this->kizeo->estaConfigurado(),
        ]);
    }

    public function prepararActaKizeo(Request $request, ReservaVehiculo $reserva)
    {
        try {
            $this->kizeo->prepararActa($reserva, $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible preparar el acta en Kizeo: '.$exception->getMessage());
        }

        return back()
            ->with('success', 'Ficha Kizeo preparada para Bodega. La reserva cambiara a En uso solo cuando llegue el acta de entrega firmada. La ficha asignada se completa desde la aplicacion movil Kizeo Forms, en Recepcion.');
    }

    public function verActa(ReservaVehiculo $reserva, string $tipo)
    {
        abort_unless(in_array($tipo, ['entrega', 'devolucion'], true), 404);

        $path = $tipo === 'entrega'
            ? $reserva->kizeo_entrega_sharepoint_path
            : $reserva->kizeo_devolucion_sharepoint_path;

        abort_unless(filled($path), 404, 'El acta solicitada aun no esta disponible.');

        try {
            $pdf = $this->oneDrive->downloadFile($path);
        } catch (\Throwable $exception) {
            report($exception);
            $pdf = null;
        }

        if (! $pdf) {
            return response()->view('gestion_vehiculos.acta_no_disponible', [
                'tipo' => $tipo,
                'codigo' => $reserva->codigo,
            ], 503, ['Cache-Control' => 'no-store, private']);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$reserva->codigo.'-'.$tipo.'.pdf"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validarVehiculo($request);
        $data['patente'] = $this->normalizarPatente($data['patente']);
        Vehiculo::create($data);

        return redirect()->route('gestion-vehiculos.index')->with('success', 'Vehiculo incorporado a la flota. Ya puedes habilitarlo para reservas.');
    }

    public function update(Request $request, Vehiculo $vehiculo)
    {
        $data = $this->validarVehiculo($request, $vehiculo);
        $data['patente'] = $this->normalizarPatente($data['patente']);
        $vehiculo->update($data);

        return redirect()->route('gestion-vehiculos.index')->with('success', 'Datos del vehiculo actualizados.');
    }

    public function actualizarReserva(Request $request, ReservaVehiculo $reserva)
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(array_keys(ReservaVehiculo::ESTADOS))],
        ]);

        $reserva = $this->reservas->actualizarEstado($reserva, $data['estado'], $request->user());

        try {
            $this->reservas->enviarActualizacion($reserva, 'actualizacion');
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Estado de la reserva '.$reserva->codigo.' actualizado.');
    }

    public function storeReservaBodega(Request $request)
    {
        $data = $this->validarReservaBodega($request);
        $identidad = $this->identidadSolicitante($data);
        $reserva = $this->reservas->crearReserva($data, $identidad, $request->user());

        if ($this->kizeo->estaConfigurado()) {
            try {
                PrepararActaReservaVehiculoKizeo::dispatch($reserva->id);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        try {
            $this->reservas->enviarConfirmacion($reserva);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()->route('gestion-vehiculos.index')
            ->with('success', 'Reserva '.$reserva->codigo.' creada a nombre de '.$reserva->solicitante_nombre.'.');
    }

    public function reprogramarReserva(Request $request, ReservaVehiculo $reserva)
    {
        try {
            $data = $this->validarReservaBodega($request);
            $reserva = $this->reservas->reprogramarReserva(
                $reserva,
                $data,
                $this->identidadSolicitante($data),
                $request->user(),
            );
        } catch (ValidationException $exception) {
            $motivo = collect($exception->errors())->flatten()->first() ?: 'Revisa los datos ingresados e inténtalo nuevamente.';

            return back()
                ->withInput()
                ->withErrors($exception->errors())
                ->with('fleet_drawer', 'reserva-'.$reserva->id)
                ->with('error', 'La reserva '.$reserva->codigo.' no fue modificada. '.$motivo);
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('fleet_drawer', 'reserva-'.$reserva->id)
                ->with('error', 'La reserva '.$reserva->codigo.' no fue modificada por un problema inesperado. Inténtalo nuevamente o contacta a Soporte.');
        }

        try {
            $this->reservas->enviarReprogramacion($reserva, $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('warning', 'Reserva '.$reserva->codigo.' actualizada, pero no fue posible enviar el correo al solicitante.');
        }

        return back()->with('success', 'Reserva '.$reserva->codigo.' actualizada. Se notificó al solicitante.');
    }

    public function eliminarReserva(Request $request, ReservaVehiculo $reserva)
    {
        $reserva->loadMissing('vehiculo');
        $codigo = $reserva->codigo;

        if ($reserva->kizeo_data_id) {
            return back()->with('error', 'No se elimino '.$codigo.' porque ya tiene una ficha creada en Kizeo. Elimina primero esa ficha de prueba desde Kizeo y luego vuelve a eliminar la reserva en SAEP; asi no queda un acta huerfana.');
        }

        $calendar = $this->calendar->eliminar($reserva);

        if ($calendar['estado'] === 'error') {
            return back()->with('error', 'No se elimino '.$codigo.' porque el evento de Outlook no pudo eliminarse. Intenta nuevamente o revisa la sincronizacion.');
        }

        $notificacionPendiente = false;
        try {
            $this->reservas->enviarEliminacion($reserva, $request->user());
        } catch (\Throwable $exception) {
            report($exception);
            $notificacionPendiente = true;
        }

        $reserva->delete();

        if ($notificacionPendiente) {
            return back()->with('warning', 'Reserva '.$codigo.' eliminada permanentemente. No fue posible enviar el correo de aviso.');
        }

        return back()->with('success', 'Reserva '.$codigo.' eliminada permanentemente.');
    }

    public function storeSolicitante(Request $request)
    {
        $data = $this->validarSolicitante($request);
        SolicitanteReservaVehiculo::create($data);

        return back()->with('success', 'Solicitante incorporado al catalogo.');
    }

    public function updateSolicitante(Request $request, SolicitanteReservaVehiculo $solicitante)
    {
        $data = $this->validarSolicitante($request, $solicitante);
        $solicitante->update($data);

        return back()->with('success', 'Solicitante actualizado.');
    }

    public function storeMotivo(Request $request)
    {
        ReservaVehiculoMotivo::create($this->validarMotivo($request));

        return back()->with('success', 'Motivo de reserva incorporado al formulario.');
    }

    public function updateMotivo(Request $request, ReservaVehiculoMotivo $motivo)
    {
        $motivo->update($this->validarMotivo($request, $motivo));

        return back()->with('success', 'Motivo de reserva actualizado.');
    }

    private function validarVehiculo(Request $request, ?Vehiculo $vehiculo = null): array
    {
        return $request->validate([
            'codigo_interno' => [
                'nullable', 'string', 'max:30',
                Rule::unique('vehiculos', 'codigo_interno')->ignore($vehiculo?->id),
            ],
            'patente' => [
                'required', 'string', 'max:16',
                Rule::unique('vehiculos', 'patente')->ignore($vehiculo?->id),
            ],
            'nombre' => ['nullable', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'tipo' => ['required', 'string', 'max:40'],
            'capacidad' => ['nullable', 'integer', 'min:1', 'max:99'],
            'sede' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:60'],
            'estado' => ['required', Rule::in(array_keys(Vehiculo::ESTADOS))],
            'reservas_habilitadas' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]) + ['reservas_habilitadas' => $request->boolean('reservas_habilitadas')];
    }

    private function normalizarPatente(string $patente): string
    {
        $normalizada = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $patente));

        return preg_match('/^([A-Z]{4})([0-9]{2})$/', $normalizada, $partes)
            ? $partes[1].'-'.$partes[2]
            : $normalizada;
    }

    private function validarSolicitante(Request $request, ?SolicitanteReservaVehiculo $solicitante = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:200',
                Rule::unique('solicitantes_reserva_vehiculo', 'nombre')->ignore($solicitante?->id),
            ],
            'email' => [
                'nullable', 'email', 'max:200',
                Rule::unique('solicitantes_reserva_vehiculo', 'email')->ignore($solicitante?->id),
            ],
            'activo' => ['nullable', 'boolean'],
        ]) + ['activo' => $request->boolean('activo')];
    }

    private function validarReservaBodega(Request $request): array
    {
        $data = $request->validate([
            'vehiculo_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')],
            'inicio' => ['required', 'date', 'after_or_equal:now'],
            'termino' => ['required', 'date', 'after:inicio'],
            'solicitante_id' => ['nullable', 'integer', Rule::exists('solicitantes_reserva_vehiculo', 'id')],
            'solicitante_nombre' => ['required', 'string', 'max:200'],
            'solicitante_email' => ['required', 'email', 'max:200'],
            'solicitante_telefono' => ['nullable', 'string', 'max:50'],
            'destinos' => ['nullable', 'array', 'max:12'],
            'destinos.*' => ['integer', Rule::exists('centros_costo', 'id')->where('activo', true)],
            'destino_otro' => ['nullable', 'string', 'max:300'],
            'motivo_id' => ['required', 'integer', Rule::exists('reserva_vehiculo_motivos', 'id')->where('activo', true)],
            'motivo_detalle' => ['nullable', 'string', 'max:1000'],
            'pasajeros' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

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

    private function identidadSolicitante(array $data): array
    {
        return [
            'email' => strtolower(trim((string) $data['solicitante_email'])),
            'name' => trim((string) $data['solicitante_nombre']),
        ];
    }

    private function validarMotivo(Request $request, ?ReservaVehiculoMotivo $motivo = null): array
    {
        $data = $request->validate([
            'nombre' => [
                'required', 'string', 'max:200',
                Rule::unique('reserva_vehiculo_motivos', 'nombre')->ignore($motivo?->id),
            ],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['orden'] = (int) $request->input('orden', 100);
        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    private function semanaCalendario(Request $request): Carbon
    {
        try {
            $fecha = filled($request->input('semana'))
                ? Carbon::parse($request->input('semana'))
                : now();
        } catch (\Throwable) {
            $fecha = now();
        }

        return $fecha->copy()->startOfWeek(Carbon::MONDAY);
    }
}
