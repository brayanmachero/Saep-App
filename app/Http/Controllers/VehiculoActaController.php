<?php

namespace App\Http\Controllers;

use App\Jobs\NotificarVehiculoActa;
use App\Models\ReservaVehiculo;
use App\Models\VehiculoActa;
use App\Services\ReservaVehiculoMicrosoftAuthService;
use App\Services\VehiculoActaService;
use App\Services\VehiculoActaDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehiculoActaController extends Controller
{
    public function __construct(private VehiculoActaService $actas, private VehiculoActaDocumentService $documentos) {}

    private function habilitado(): void
    {
        abort_unless(config('vehiculos_actas.enabled'), 404);
    }

    public function editar(Request $request, ReservaVehiculo $reserva, string $tipo)
    {
        $this->habilitado();
        abort_unless(in_array($tipo, ['entrega', 'devolucion'], true), 404);
        $acta = $reserva->actasSaep()->where('tipo', $tipo)->first();
        if ($acta && $acta->estado !== 'BORRADOR') return redirect()->route('vehiculos-actas.show', $acta);
        $this->actas->validarOperacion($reserva, $tipo);
        return view('vehiculos_actas.editar', [
            'reserva' => $reserva->load('vehiculo'), 'tipo' => $tipo, 'acta' => $acta,
            'entrega' => $reserva->actasSaep()->where('tipo', 'entrega')->where('estado', 'FIRMADA')->first(),
            'partes' => VehiculoActaService::PARTES, 'kit' => VehiculoActaService::KIT,
        ]);
    }

    public function guardar(Request $request, ReservaVehiculo $reserva, string $tipo)
    {
        $this->habilitado();
        abort_unless(in_array($tipo, ['entrega', 'devolucion'], true), 404);
        $rules = [
            'accion' => ['required', Rule::in(['borrador', 'preparar'])], 'revision' => ['required', 'integer', 'min:0'],
            'conductor_nombre' => ['required', 'string', 'max:200'], 'conductor_email' => ['required', 'email', 'max:200'],
            'fecha' => ['required', 'date', 'before_or_equal:now'], 'kilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
            'combustible' => ['required', Rule::in(['Reserva', '1/4', '1/2', '3/4', 'Lleno'])],
            'observaciones' => ['nullable', 'string', 'max:4000'],
            'danos_nuevos' => [$tipo === 'devolucion' ? 'required' : 'nullable', Rule::in(['SI', 'NO'])],
            'condiciones' => ['required', 'array:'.implode(',', array_keys(VehiculoActaService::PARTES))],
            'kit' => ['required', 'array:'.implode(',', array_keys(VehiculoActaService::KIT))],
            'fotos' => ['nullable', 'array', 'max:4'], 'fotos.*' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'firma_encargado' => ['nullable', 'string', 'max:700000'],
            'croquis' => ['nullable', 'string', 'max:700000'],
            'quitar_croquis' => ['nullable', 'boolean'],
            'ubicacion' => ['nullable', 'array:latitud,longitud,precision,capturada_at'],
            'ubicacion.latitud' => ['required_with:ubicacion', 'numeric', 'between:-90,90'],
            'ubicacion.longitud' => ['required_with:ubicacion', 'numeric', 'between:-180,180'],
            'ubicacion.precision' => ['required_with:ubicacion', 'numeric', 'min:0', 'max:100000'],
            'ubicacion.capturada_at' => ['required_with:ubicacion', 'date', 'before_or_equal:now'],
            'ubicacion_motivo' => ['nullable', 'string', 'max:300'],
        ];
        foreach (VehiculoActaService::PARTES as $key => $label) $rules['condiciones.'.$key] = ['required', Rule::in(['Bueno', 'Con observaciones'])];
        foreach (VehiculoActaService::KIT as $key => $label) $rules['kit.'.$key] = ['required', Rule::in(['Presente', 'Faltante', 'No aplica'])];
        $data = $request->validate($rules);
        if ($data['accion'] === 'preparar' && empty($data['ubicacion']) && blank($data['ubicacion_motivo'] ?? null)) {
            return back()->withInput()->withErrors(['ubicacion' => 'Captura la ubicación o indica por qué no está disponible.']);
        }
        if ((in_array('Con observaciones', $data['condiciones'], true) || ($data['danos_nuevos'] ?? 'NO') === 'SI') && blank($data['observaciones'] ?? null)) {
            return back()->withInput()->withErrors(['observaciones' => 'Describe los daños u observaciones detectados.']);
        }
        $acta = $this->actas->guardar($reserva, $tipo, $data, $request);
        return redirect()->route($acta->estado === 'BORRADOR' ? 'vehiculos-actas.editar' : 'vehiculos-actas.show',
            $acta->estado === 'BORRADOR' ? [$reserva, $tipo] : [$acta])
            ->with('success', $acta->estado === 'BORRADOR' ? 'Borrador guardado. Puedes continuar más tarde.' : 'Inspección lista. Entrega el dispositivo al conductor para revisar y firmar.');
    }

    private function acceso(Request $request, VehiculoActa $acta): bool
    {
        $this->habilitado();
        $interno = !$request->routeIs('portal-actas.*');
        if ($interno) {
            abort_unless($request->user()?->tieneAcceso('gestion_vehiculos'), 403);
        } else {
            $identidad = app(ReservaVehiculoMicrosoftAuthService::class)->identidad($request);
            abort_unless($identidad && strtolower($identidad['email']) === strtolower($acta->datos['conductor_email']) && $acta->estado !== 'BORRADOR', 403);
        }
        return $interno;
    }

    public function show(Request $request, VehiculoActa $acta)
    {
        $interno = $this->acceso($request, $acta);
        $entrega = $acta->reserva->actasSaep()->where('tipo', 'entrega')->where('estado', 'FIRMADA')->first();
        return response()->view('vehiculos_actas.show', compact('acta', 'interno', 'entrega'), 200, ['Cache-Control' => 'no-store, private']);
    }

    public function firmar(Request $request, VehiculoActa $acta)
    {
        $interno = $this->acceso($request, $acta);
        if ($interno) abort_unless($request->user()->tieneAcceso('gestion_vehiculos', 'puede_editar'), 403);
        $data = $request->validate([
            'contenido_hash' => ['required', 'string', 'size:64'], 'aceptacion' => ['accepted'],
            'nombre_firmante' => ['required', 'string', 'max:200'], 'firma_conductor' => ['required', 'string', 'max:700000'],
            'presencial' => $interno ? ['accepted'] : ['nullable'],
        ]);
        if (mb_strtolower(trim($data['nombre_firmante'])) !== mb_strtolower(trim($acta->datos['conductor_nombre']))) {
            return back()->withErrors(['nombre_firmante' => 'Escribe el nombre del conductor tal como aparece en el acta.']);
        }
        $identidad = $interno
            ? ['modalidad' => 'PRESENCIAL_ATESTIGUADA_BODEGA', 'email' => $acta->datos['conductor_email']]
            : ['modalidad' => 'MICROSOFT_CORPORATIVO', 'email' => app(ReservaVehiculoMicrosoftAuthService::class)->identidad($request)['email']];
        $acta = $this->actas->firmar($acta, $data, $request, $identidad);
        if (config('vehiculos_actas.mail_enabled') || config('vehiculos_actas.calendar_enabled')) {
            try { NotificarVehiculoActa::dispatch($acta->id); }
            catch (\Throwable $e) { report($e); $acta->update(['email_estado' => 'ERROR', 'email_error' => 'No se pudo iniciar el envío. El acta quedó guardada.']); }
        }
        return redirect()->route($interno ? 'vehiculos-actas.show' : 'portal-actas.show', $acta)->with('success', 'Acta firmada y guardada. La reserva fue actualizada.');
    }

    public function reabrir(Request $request, VehiculoActa $acta)
    {
        $this->habilitado();
        $this->actas->reabrir($acta, $request->user());
        return redirect()->route('vehiculos-actas.editar', [$acta->reserva_vehiculo_id, $acta->tipo])->with('success', 'Borrador reabierto. El encargado deberá volver a firmar.');
    }

    public function media(Request $request, VehiculoActa $acta, string $archivo)
    {
        $this->acceso($request, $acta);
        $path = match ($archivo) {
            'croquis' => $acta->datos['croquis']['path'] ?? null,
            'encargado' => $acta->firma_encargado_path, 'conductor' => $acta->firma_conductor_path,
            default => ctype_digit($archivo) ? ($acta->evidencias[(int) $archivo]['path'] ?? null) : null,
        };
        abort_unless($path && Storage::disk(config('vehiculos_actas.disk'))->exists($path), 404);
        return response(Storage::disk(config('vehiculos_actas.disk'))->get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function pdf(Request $request, VehiculoActa $acta)
    {
        $this->acceso($request, $acta);
        abort_unless($acta->estado === 'FIRMADA', 409);
        return response($this->documentos->pdf($acta), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="acta-'.$acta->uuid.'.pdf"', 'Cache-Control' => 'no-store, private']);
    }

    public function correo(Request $request, VehiculoActa $acta)
    {
        $this->acceso($request, $acta);
        abort_unless($acta->estado === 'FIRMADA', 409);
        return response()->view('emails.vehiculo_acta_saep', ['acta' => $acta], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function notificar(Request $request, VehiculoActa $acta)
    {
        $this->acceso($request, $acta);
        abort_unless(config('vehiculos_actas.mail_enabled') && $acta->estado === 'FIRMADA', 409);
        NotificarVehiculoActa::dispatch($acta->id);
        return back()->with('success', 'Notificación solicitada. Consulta el estado del correo en esta acta.');
    }

    public function paquete(Request $request, VehiculoActa $acta)
    {
        $this->acceso($request, $acta);
        abort_unless($acta->estado === 'FIRMADA', 409);
        return response()->streamDownload(function () use ($acta) {
            echo json_encode($this->documentos->paqueteKizeo($acta), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 'integracion-kizeo-'.$acta->uuid.'.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store, private']);
    }
}
