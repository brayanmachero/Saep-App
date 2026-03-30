<?php

namespace App\Http\Controllers;

use App\Mail\LeyKarinDenunciaMail;
use App\Mail\LeyKarinResolucionMail;
use App\Models\LeyKarin;
use App\Models\CentroCosto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeyKarinController extends Controller
{
    // =====================================================
    // ADMIN / PREVENCIONISTA: GESTIÓN COMPLETA
    // =====================================================

    public function index(Request $request)
    {
        $query = LeyKarin::with(['centroCosto', 'investigador', 'denunciante']);

        if ($request->filled('buscar')) {
            $b = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where(function ($q) use ($b) {
                $q->where('folio', 'like', "%{$b}%")
                  ->orWhere('denunciante_nombre', 'like', "%{$b}%")
                  ->orWhere('denunciado_nombre', 'like', "%{$b}%");
            });
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->centro_costo_id);
        }

        $casos = $query->orderByDesc('fecha_denuncia')->paginate(20)->withQueryString();

        $stats = [
            'total'             => LeyKarin::count(),
            'recibidas'         => LeyKarin::where('estado', 'RECIBIDA')->count(),
            'en_investigacion'  => LeyKarin::where('estado', 'EN_INVESTIGACION')->count(),
            'resueltas'         => LeyKarin::where('estado', 'RESUELTA')->count(),
        ];

        $centros = CentroCosto::orderBy('nombre')->get();

        return view('ley_karin.index', compact('casos', 'stats', 'centros'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('ley_karin.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo'                     => 'required|string|in:' . implode(',', array_keys(LeyKarin::tiposMap())),
            'fecha_denuncia'           => 'required|date',
            'descripcion_hechos'       => 'required|string',
            'centro_costo_id'          => 'required|exists:centros_costo,id',
            'canal'                    => 'nullable|string',
            'denunciante_nombre'       => 'nullable|string|max:200',
            'denunciante_id'           => 'nullable|exists:users,id',
            'denunciante_rut'          => 'nullable|string|max:20',
            'denunciado_nombre'        => 'nullable|string|max:200',
            'denunciado_cargo'         => 'nullable|string|max:200',
            'investigador_id'          => 'nullable|exists:users,id',
            'fecha_plazo_investigacion' => 'nullable|date',
            'medidas_cautelares'       => 'nullable|string',
            'anonima'                  => 'nullable|boolean',
            'confidencial'             => 'nullable|boolean',
        ]);

        $data['anonima']      = $request->boolean('anonima');
        $data['confidencial'] = $request->boolean('confidencial');

        // Si es anónima, limpiar TODOS los datos del denunciante
        if ($data['anonima']) {
            $data['denunciante_id']     = null;
            $data['denunciante_nombre'] = null;
            $data['denunciante_rut']    = null;
        }

        $caso = LeyKarin::create($data);
        $caso->load('centroCosto');

        $this->notificarAdmins($caso);

        return redirect()->route('ley-karin.index')
            ->with('success', 'Denuncia registrada correctamente.')
            ->with('folio_generado', $caso->folio);
    }

    public function show(LeyKarin $leyKarin)
    {
        $leyKarin->load(['centroCosto', 'investigador', 'denunciante']);
        return view('ley_karin.show', compact('leyKarin'));
    }

    public function edit(LeyKarin $leyKarin)
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('ley_karin.edit', compact('leyKarin', 'centros', 'usuarios'));
    }

    public function update(Request $request, LeyKarin $leyKarin)
    {
        $data = $request->validate([
            'tipo'                      => 'required|string|in:' . implode(',', array_keys(LeyKarin::tiposMap())),
            'fecha_denuncia'            => 'required|date',
            'descripcion_hechos'        => 'required|string',
            'centro_costo_id'           => 'required|exists:centros_costo,id',
            'canal'                     => 'nullable|string',
            'estado'                    => 'required|string|in:' . implode(',', array_keys(LeyKarin::estadosMap())),
            'denunciado_nombre'         => 'nullable|string|max:200',
            'denunciado_cargo'          => 'nullable|string|max:200',
            'investigador_id'           => 'nullable|exists:users,id',
            'fecha_plazo_investigacion' => 'nullable|date',
            'fecha_resolucion'          => 'nullable|date',
            'resultado_investigacion'   => 'nullable|string',
            'medidas_adoptadas'         => 'nullable|string',
            'medidas_cautelares'        => 'nullable|string',
            'confidencial'              => 'nullable|boolean',
        ]);

        $data['confidencial'] = $request->boolean('confidencial');

        $estadoAnterior = $leyKarin->estado;
        $leyKarin->update($data);

        // Si el estado cambió a RESUELTA, notificar al denunciante (si no es anónimo)
        if ($estadoAnterior !== 'RESUELTA' && $data['estado'] === 'RESUELTA') {
            $this->notificarResolucion($leyKarin);
        }

        return redirect()->route('ley-karin.show', $leyKarin)
            ->with('success', 'Expediente actualizado correctamente.');
    }

    public function destroy(LeyKarin $leyKarin)
    {
        $leyKarin->update(['estado' => 'ARCHIVADA']);
        return redirect()->route('ley-karin.index')
            ->with('success', 'Caso archivado correctamente.');
    }

    // =====================================================
    // TRABAJADOR: FORMULARIO SIMPLIFICADO DE DENUNCIA
    // =====================================================

    public function createTrabajador()
    {
        $user    = auth()->user();
        $centros = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        return view('ley_karin.denuncia_trabajador', compact('user', 'centros'));
    }

    public function storeTrabajador(Request $request)
    {
        $data = $request->validate([
            'tipo'              => 'required|string|in:' . implode(',', array_keys(LeyKarin::tiposMap())),
            'descripcion_hechos' => 'required|string',
            'centro_costo_id'   => 'required|exists:centros_costo,id',
            'denunciado_nombre' => 'nullable|string|max:200',
            'denunciado_cargo'  => 'nullable|string|max:200',
            'anonima'           => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $data['fecha_denuncia'] = now()->toDateString();
        $data['canal']          = 'FORMULARIO_WEB';
        $data['confidencial']   = true;
        $data['anonima']        = $request->boolean('anonima');

        // Si NO es anónima, autocompletar datos del trabajador
        if (!$data['anonima']) {
            $data['denunciante_id']     = $user->id;
            $data['denunciante_nombre'] = $user->nombre_completo;
            $data['denunciante_rut']    = $user->rut;
        } else {
            // Anónima: NO guardar absolutamente nada del denunciante
            $data['denunciante_id']     = null;
            $data['denunciante_nombre'] = null;
            $data['denunciante_rut']    = null;
        }

        $caso = LeyKarin::create($data);
        $caso->load('centroCosto');

        $this->notificarAdmins($caso);

        return redirect()->route('ley-karin.confirmacion', $caso);
    }

    public function confirmacion(LeyKarin $leyKarin)
    {
        // Si no es anónima, solo el denunciante o admins pueden ver la confirmación
        $user = auth()->user();
        $esAdmin = in_array($user->rol->codigo ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA']);

        if (!$esAdmin && !$leyKarin->anonima && $leyKarin->denunciante_id !== $user->id) {
            abort(403);
        }

        return view('ley_karin.confirmacion', compact('leyKarin'));
    }

    // =====================================================
    // NOTIFICACIONES EMAIL
    // =====================================================

    private function notificarAdmins(LeyKarin $caso): void
    {
        $destinatarios = User::whereHas('rol', fn($q) => $q->whereIn('nombre', ['SUPER_ADMIN', 'PREVENCIONISTA']))
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($destinatarios as $email) {
            Mail::to($email)->send(new LeyKarinDenunciaMail($caso));
        }
    }

    private function notificarResolucion(LeyKarin $leyKarin): void
    {
        if ($leyKarin->anonima) {
            return; // No hay a quién notificar
        }

        $email = null;
        if ($leyKarin->denunciante_id) {
            $email = $leyKarin->denunciante?->email;
        }

        if ($email) {
            $leyKarin->load('centroCosto');
            Mail::to($email)->send(new LeyKarinResolucionMail($leyKarin));
        }
    }
}
