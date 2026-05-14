<?php

namespace App\Http\Controllers;

use App\Mail\LeyKarinDenunciaMail;
use App\Mail\LeyKarinResolucionMail;
use App\Models\LeyKarin;
use App\Models\CentroCosto;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeyKarinController extends Controller
{
    // =====================================================
    // ADMIN / PREVENCIONISTA: GESTIÓN COMPLETA
    // =====================================================

    public function dashboard(Request $request)
    {
        $q = LeyKarin::query();

        // --- Filtros dinámicos ---
        if ($request->filled('desde'))                    $q->where('fecha_denuncia', '>=', $request->desde);
        if ($request->filled('hasta'))                    $q->where('fecha_denuncia', '<=', $request->hasta);
        if ($request->filled('tipo'))                     $q->whereIn('tipo', (array)$request->tipo);
        if ($request->filled('estado'))                   $q->whereIn('estado', (array)$request->estado);
        if ($request->filled('canal'))                    $q->whereIn('canal', (array)$request->canal);
        if ($request->filled('centro_costo_id'))          $q->whereIn('centro_costo_id', (array)$request->centro_costo_id);
        if ($request->filled('denunciante_empresa'))      $q->whereIn('denunciante_empresa', (array)$request->denunciante_empresa);
        if ($request->filled('denunciante_sexo'))         $q->whereIn('denunciante_sexo', (array)$request->denunciante_sexo);
        if ($request->filled('denunciante_rango_etario')) $q->whereIn('denunciante_rango_etario', (array)$request->denunciante_rango_etario);
        if ($request->filled('denunciado_empresa'))       $q->whereIn('denunciado_empresa', (array)$request->denunciado_empresa);
        if ($request->filled('denunciado_sexo'))          $q->whereIn('denunciado_sexo', (array)$request->denunciado_sexo);
        if ($request->filled('denunciado_rango_etario'))  $q->whereIn('denunciado_rango_etario', (array)$request->denunciado_rango_etario);
        if ($request->filled('anonima') && $request->anonima !== '') {
            $q->where('anonima', (bool)(int)$request->anonima);
        }

        // --- KPIs ---
        $kpis = [
            'total'            => (clone $q)->count(),
            'recibidas'        => (clone $q)->where('estado', 'RECIBIDA')->count(),
            'en_investigacion' => (clone $q)->where('estado', 'EN_INVESTIGACION')->count(),
            'resueltas'        => (clone $q)->where('estado', 'RESUELTA')->count(),
            'derivadas'        => (clone $q)->where('estado', 'DERIVADA_DT')->count(),
            'archivadas'       => (clone $q)->where('estado', 'ARCHIVADA')->count(),
            'anonimas'         => (clone $q)->where('anonima', true)->count(),
            'terceros'         => (clone $q)->where('es_tercero', true)->count(),
            'con_medidas'      => (clone $q)->whereNotNull('medidas_cautelares')
                                             ->where('medidas_cautelares', '!=', '')->count(),
        ];
        $dias = (clone $q)->whereNotNull('fecha_resolucion')
            ->selectRaw('AVG(DATEDIFF(fecha_resolucion, fecha_denuncia)) as avg_dias')
            ->value('avg_dias');
        $kpis['promedio_dias'] = $dias ? round($dias, 1) : null;

        // --- Tendencia mensual ---
        $trend = (clone $q)
            ->selectRaw("DATE_FORMAT(fecha_denuncia, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')->orderBy('mes')
            ->pluck('total', 'mes');

        // --- Distribuciones generales ---
        $byTipo = (clone $q)->selectRaw('tipo as k, COUNT(*) as total')
            ->whereNotNull('tipo')->groupBy('k')->pluck('total', 'k');

        $byEstado = (clone $q)->selectRaw('estado as k, COUNT(*) as total')
            ->groupBy('k')->pluck('total', 'k');

        $byCanal = (clone $q)->selectRaw('canal as k, COUNT(*) as total')
            ->whereNotNull('canal')->groupBy('k')->pluck('total', 'k');

        $byCentro = (clone $q)
            ->join('centros_costo', 'ley_karin.centro_costo_id', '=', 'centros_costo.id')
            ->selectRaw('centros_costo.nombre as k, COUNT(*) as total')
            ->groupBy('k')->orderByDesc('total')->limit(10)
            ->pluck('total', 'k');

        // --- Denunciante ---
        $byDenuncianteSexo     = (clone $q)->selectRaw('denunciante_sexo as k, COUNT(*) as total')
            ->whereNotNull('denunciante_sexo')->groupBy('k')->pluck('total', 'k');
        $byDenuncianteRango    = (clone $q)->selectRaw('denunciante_rango_etario as k, COUNT(*) as total')
            ->whereNotNull('denunciante_rango_etario')->groupBy('k')->pluck('total', 'k');
        $byDenuncianteCargo    = (clone $q)->selectRaw('denunciante_cargo_tipo as k, COUNT(*) as total')
            ->whereNotNull('denunciante_cargo_tipo')->groupBy('k')->pluck('total', 'k');
        $byDenuncianteEmpresa  = (clone $q)->selectRaw('denunciante_empresa as k, COUNT(*) as total')
            ->whereNotNull('denunciante_empresa')->groupBy('k')->pluck('total', 'k');
        $byDenuncianteJerarquia = (clone $q)->selectRaw('denunciante_jerarquia as k, COUNT(*) as total')
            ->whereNotNull('denunciante_jerarquia')->groupBy('k')->pluck('total', 'k');

        // --- Denunciado ---
        $byDenunciadoSexo    = (clone $q)->selectRaw('denunciado_sexo as k, COUNT(*) as total')
            ->whereNotNull('denunciado_sexo')->groupBy('k')->pluck('total', 'k');
        $byDenunciadoRango   = (clone $q)->selectRaw('denunciado_rango_etario as k, COUNT(*) as total')
            ->whereNotNull('denunciado_rango_etario')->groupBy('k')->pluck('total', 'k');
        $byDenunciadoCargo   = (clone $q)->selectRaw('denunciado_cargo_tipo as k, COUNT(*) as total')
            ->whereNotNull('denunciado_cargo_tipo')->groupBy('k')->pluck('total', 'k');
        $byDenunciadoEmpresa = (clone $q)->selectRaw('denunciado_empresa as k, COUNT(*) as total')
            ->whereNotNull('denunciado_empresa')->groupBy('k')->pluck('total', 'k');

        $centros = CentroCosto::orderBy('nombre')->get();

        return view('ley_karin.dashboard', compact(
            'kpis', 'trend',
            'byTipo', 'byEstado', 'byCanal', 'byCentro',
            'byDenuncianteSexo', 'byDenuncianteRango', 'byDenuncianteCargo',
            'byDenuncianteEmpresa', 'byDenuncianteJerarquia',
            'byDenunciadoSexo', 'byDenunciadoRango', 'byDenunciadoCargo',
            'byDenunciadoEmpresa', 'centros'
        ));
    }

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
            'denunciado_nombre'           => 'nullable|string|max:200',
            'denunciado_cargo'            => 'nullable|string|max:200',
            'denunciante_rango_etario'    => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::rangosEtariosMap())),
            'denunciante_sexo'            => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::sexosMap())),
            'denunciante_cargo_tipo'      => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::cargosTipoMap())),
            'denunciante_cargo_otro'      => 'nullable|string|max:200',
            'denunciante_empresa'         => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::empresasDenuncianteMap())),
            'denunciante_jerarquia'       => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::jerarquiasMap())),
            'denunciado_rango_etario'     => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::rangosEtariosMap())),
            'denunciado_sexo'             => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::sexosMap())),
            'denunciado_cargo_tipo'       => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::cargosTipoMap())),
            'denunciado_cargo_otro'       => 'nullable|string|max:200',
            'denunciado_empresa'          => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::empresasDenunciadoMap())),
            'investigador_id'             => 'nullable|exists:users,id',
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
            'anonima'           => 'nullable|boolean',
            'denunciante_rango_etario' => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::rangosEtariosMap())),
            'denunciante_sexo'         => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::sexosMap())),
            'denunciante_cargo_tipo'   => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::cargosTipoMap())),
            'denunciante_cargo_otro'   => 'nullable|string|max:200',
            'denunciante_empresa'      => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::empresasDenuncianteMap())),
            'denunciante_jerarquia'    => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::jerarquiasMap())),
            'denunciado_rango_etario'  => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::rangosEtariosMap())),
            'denunciado_sexo'          => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::sexosMap())),
            'denunciado_cargo_tipo'    => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::cargosTipoMap())),
            'denunciado_cargo_otro'    => 'nullable|string|max:200',
            'denunciado_empresa'       => 'nullable|string|in:' . implode(',', array_keys(LeyKarin::empresasDenunciadoMap())),
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
        $admins = User::whereHas('rol', fn($q) => $q->whereIn('nombre', ['SUPER_ADMIN', 'PREVENCIONISTA']))
            ->whereNotNull('email')
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new LeyKarinDenunciaMail($caso));
            $admin->notify(new AppNotification(
                'Nueva denuncia Ley Karin',
                'Folio ' . $caso->folio,
                'danger',
                route('ley-karin.show', $caso)
            ));
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
            $leyKarin->denunciante?->notify(new AppNotification(
                'Resolución de denuncia',
                'Tu denuncia ' . $leyKarin->folio . ' tiene resolución',
                'info',
                route('ley-karin.index')
            ));
        }
    }
}
