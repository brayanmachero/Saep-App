<?php

namespace App\Http\Controllers;

use App\Models\ProgramaSst;
use App\Models\Rol;
use App\Models\SstCategoria;
use App\Models\SstActividad;
use App\Models\SstNotificacionLog;
use App\Models\SstSeguimiento;
use App\Models\SstPlanAccion;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\SstActividadAlertaMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CartaGanttController extends Controller
{
    // =====================================================
    // PROGRAMA SST (CRUD)
    // =====================================================

    public function index(Request $request)
    {
        $query = ProgramaSst::with(['creador', 'centroCosto', 'responsable']);

        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->centro_costo_id);
        }

        $programas = $query->orderByDesc('anio')->orderByDesc('created_at')->get();

        $stats = [
            'total'   => ProgramaSst::count(),
            'activos' => ProgramaSst::where('estado', 'ACTIVO')->count(),
            'vencidas' => SstActividad::where('fecha_fin', '<', now())
                            ->where('estado', '!=', 'COMPLETADA')
                            ->where('estado', '!=', 'CANCELADA')->count(),
        ];

        $centros = CentroCosto::orderBy('nombre')->get();
        $anios   = ProgramaSst::distinct()->orderByDesc('anio')->pluck('anio');

        return view('carta_gantt.index', compact('programas', 'stats', 'centros', 'anios'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'anio'            => 'required|integer|min:2020|max:2099',
            'nombre'          => 'required|string|max:300',
            'descripcion'     => 'nullable|string',
            'estado'          => 'required|string|in:BORRADOR,ACTIVO,CERRADO',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
            'responsable_id'  => 'nullable|exists:users,id',
        ]);

        $programa = ProgramaSst::create([
            'anio'            => $request->anio,
            'titulo'          => $request->nombre,
            'descripcion'     => $request->descripcion,
            'estado'          => $request->estado,
            'centro_costo_id' => $request->centro_costo_id,
            'responsable_id'  => $request->responsable_id,
            'creado_por'      => auth()->id(),
        ]);

        return redirect()->route('carta-gantt.show', $programa)
            ->with('success', "Programa SST creado — Código: {$programa->codigo}");
    }

    public function show(ProgramaSst $cartaGantt)
    {
        $cartaGantt->load([
            'categorias.actividades.seguimiento',
            'categorias.actividades.responsableUser',
            'categorias.actividades.planesAccion',
            'centroCosto', 'responsable', 'creador',
        ]);
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.show', compact('cartaGantt', 'usuarios'));
    }

    public function edit(ProgramaSst $cartaGantt)
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.edit', compact('cartaGantt', 'centros', 'usuarios'));
    }

    public function update(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate([
            'nombre'          => 'required|string|max:300',
            'anio'            => 'required|integer|min:2020|max:2099',
            'estado'          => 'required|string|in:BORRADOR,ACTIVO,CERRADO',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
            'responsable_id'  => 'nullable|exists:users,id',
        ]);

        $cartaGantt->update([
            'titulo'          => $request->nombre,
            'anio'            => $request->anio,
            'descripcion'     => $request->descripcion,
            'estado'          => $request->estado,
            'centro_costo_id' => $request->centro_costo_id,
            'responsable_id'  => $request->responsable_id,
        ]);

        return redirect()->route('carta-gantt.show', $cartaGantt)
            ->with('success', 'Programa actualizado.');
    }

    public function destroy(ProgramaSst $cartaGantt)
    {
        $cartaGantt->update(['estado' => 'CERRADO']);
        return redirect()->route('carta-gantt.index')
            ->with('success', 'Programa cerrado correctamente.');
    }

    // =====================================================
    // CATEGORÍAS
    // =====================================================

    public function storeCategoria(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate(['nombre' => 'required|string|max:200']);
        $cartaGantt->categorias()->create([
            'nombre' => $request->nombre,
            'orden'  => $cartaGantt->categorias()->max('orden') + 1,
        ]);
        return back()->with('success', 'Categoría agregada.');
    }

    public function destroyCategoria(SstCategoria $categoria)
    {
        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    // =====================================================
    // ACTIVIDADES
    // =====================================================

    public function storeActividad(Request $request, SstCategoria $categoria)
    {
        $request->validate([
            'nombre'         => 'required|string|max:300',
            'responsable_id' => 'nullable|exists:users,id',
            'fecha_inicio'   => 'nullable|date',
            'fecha_fin'      => 'nullable|date|after_or_equal:fecha_inicio',
            'prioridad'      => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'periodicidad'   => 'nullable|string|in:' . implode(',', array_keys(SstActividad::periodicidadesMap())),
            'meses_prog'     => 'nullable|array',
            'meses_prog.*'   => 'integer|min:1|max:12',
        ]);

        $actividad = $categoria->actividades()->create([
            'nombre'         => $request->nombre,
            'descripcion'    => $request->descripcion,
            'responsable'    => $request->responsable_id
                ? User::find($request->responsable_id)?->nombre_completo
                : $request->responsable_nombre,
            'responsable_id' => $request->responsable_id,
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_fin'      => $request->fecha_fin,
            'prioridad'      => $request->prioridad ?? 'MEDIA',
            'periodicidad'   => $request->periodicidad,
            'orden'          => $categoria->actividades()->max('orden') + 1,
        ]);

        // Si hay periodicidad sin meses manuales: auto-calcular meses programados
        $mesesProg = $request->get('meses_prog', []);
        if (empty($mesesProg) && $request->periodicidad) {
            $mesesProg = SstActividad::mesesProgramadosPorPeriodicidad($request->periodicidad);
        }

        // Auto-asignar fecha_fin basada en el último mes programado si no se especificó
        if (!$request->fecha_fin && !empty($mesesProg)) {
            $anio = $categoria->programa->anio ?? date('Y');
            $ultimoMes = max($mesesProg);
            $actividad->update([
                'fecha_fin' => \Carbon\Carbon::create($anio, $ultimoMes)->endOfMonth()->toDateString(),
            ]);
        }

        // Auto-asignar fecha_inicio si no se especificó
        if (!$request->fecha_inicio && !empty($mesesProg)) {
            $anio = $categoria->programa->anio ?? date('Y');
            $primerMes = min($mesesProg);
            $actividad->update([
                'fecha_inicio' => \Carbon\Carbon::create($anio, $primerMes, 1)->toDateString(),
            ]);
        }

        // Crear seguimiento para meses programados
        foreach ($mesesProg as $mes) {
            $actividad->seguimiento()->updateOrCreate(
                ['mes' => (int) $mes],
                ['programado' => true]
            );
        }

        // Notificar al responsable + CC jefe del programa + superadmins
        $this->enviarNotificacionActividad($actividad, 'asignacion');

        return back()->with('success', 'Actividad agregada.');
    }

    public function updateActividad(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'nombre'         => 'required|string|max:300',
            'responsable_id' => 'nullable|exists:users,id',
            'fecha_inicio'   => 'nullable|date',
            'fecha_fin'      => 'nullable|date|after_or_equal:fecha_inicio',
            'prioridad'      => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'estado'         => 'nullable|string|in:' . implode(',', array_keys(SstActividad::estadosMap())),
            'periodicidad'   => 'nullable|string|in:' . implode(',', array_keys(SstActividad::periodicidadesMap())),
            'meses_prog'     => 'nullable|array',
            'meses_prog.*'   => 'integer|min:1|max:12',
        ]);

        $actividad->update([
            'nombre'         => $request->nombre,
            'descripcion'    => $request->descripcion,
            'responsable'    => $request->responsable_id
                ? User::find($request->responsable_id)?->nombre_completo
                : ($request->responsable_nombre ?? $actividad->responsable),
            'responsable_id' => $request->responsable_id,
            'fecha_inicio'   => $request->fecha_inicio,
            'fecha_fin'      => $request->fecha_fin,
            'prioridad'      => $request->prioridad ?? $actividad->prioridad,
            'estado'         => $request->estado ?? $actividad->estado,
            'periodicidad'   => $request->periodicidad,
        ]);

        // Actualizar meses programados si se enviaron checkboxes
        if ($request->has('meses_prog')) {
            $mesesSeleccionados = collect($request->get('meses_prog', []))->map(fn($m) => (int) $m);

            for ($m = 1; $m <= 12; $m++) {
                if ($mesesSeleccionados->contains($m)) {
                    $actividad->seguimiento()->updateOrCreate(
                        ['mes' => $m],
                        ['programado' => true]
                    );
                } else {
                    // Desprogramar (pero conservar si ya fue realizado)
                    $seg = $actividad->seguimiento()->where('mes', $m)->first();
                    if ($seg && !$seg->realizado) {
                        $seg->update(['programado' => false]);
                    }
                }
            }
        }

        $this->recalcularEstadoActividad($actividad);

        return back()->with('success', 'Actividad actualizada.');
    }

    public function destroyActividad(SstActividad $actividad)
    {
        $actividad->delete();
        return back()->with('success', 'Actividad eliminada.');
    }

    // =====================================================
    // SEGUIMIENTO (AJAX)
    // =====================================================

    public function updateSeguimiento(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'mes'         => 'required|integer|min:1|max:12',
            'realizado'   => 'required|boolean',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $actividad->seguimiento()->updateOrCreate(
            ['mes' => $request->mes],
            [
                'realizado'           => $request->realizado,
                'observacion'         => $request->observacion,
                'actualizado_por'     => auth()->id(),
                'fecha_actualizacion' => now(),
            ]
        );

        $this->recalcularEstadoActividad($actividad);

        return response()->json(['success' => true]);
    }

    // =====================================================
    // PLAN DE ACCIÓN
    // =====================================================

    public function storePlanAccion(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'accion'            => 'required|string|max:500',
            'responsable'       => 'nullable|string|max:200',
            'fecha_compromiso'  => 'nullable|date',
        ]);

        $actividad->planesAccion()->create([
            'accion'           => $request->accion,
            'responsable'      => $request->responsable,
            'fecha_compromiso' => $request->fecha_compromiso,
            'estado'           => 'PENDIENTE',
            'observacion'      => $request->observacion,
            'creado_por'       => auth()->id(),
        ]);

        return back()->with('success', 'Plan de acción creado.');
    }

    public function updatePlanAccion(Request $request, SstPlanAccion $plan)
    {
        $request->validate([
            'estado'      => 'required|string|in:' . implode(',', array_keys(SstPlanAccion::estadosMap())),
            'observacion' => 'nullable|string',
        ]);

        $plan->update([
            'estado'      => $request->estado,
            'observacion' => $request->observacion,
        ]);

        return back()->with('success', 'Plan de acción actualizado.');
    }

    public function destroyPlanAccion(SstPlanAccion $plan)
    {
        $plan->delete();
        return back()->with('success', 'Plan de acción eliminado.');
    }

    // =====================================================
    // HELPERS
    // =====================================================

    /**
     * Envía email de actividad al responsable con CC al jefe del programa y superadmins.
     */
    private function enviarNotificacionActividad(SstActividad $actividad, string $tipo, ?int $mes = null): void
    {
        $actividad->loadMissing(['responsableUser', 'categoria.programa.responsable']);
        $programa = $actividad->categoria?->programa;

        $responsable = $actividad->responsableUser;
        $responsableEmail = $responsable?->email;

        // Construir lista de CC: jefe del programa + superadmins
        $ccEmails = collect();

        // Jefe del programa (responsable del ProgramaSst)
        if ($programa?->responsable?->email) {
            $ccEmails->push($programa->responsable->email);
        }

        // Todos los superadmins activos
        $superAdmins = User::whereHas('rol', fn ($q) => $q->where('codigo', 'SUPER_ADMIN'))
            ->where('activo', true)
            ->pluck('email')
            ->filter();
        $ccEmails = $ccEmails->merge($superAdmins)->unique()->reject(fn ($e) => $e === $responsableEmail);

        // Si no hay responsable pero sí hay CCs, enviar al primer CC como destinatario principal
        $toEmail = $responsableEmail ?: $ccEmails->shift();
        if (!$toEmail) {
            return; // No hay a quién enviar
        }

        try {
            $mail = Mail::to($toEmail);
            if ($ccEmails->isNotEmpty()) {
                $mail->cc($ccEmails->all());
            }
            $mail->send(new SstActividadAlertaMail($actividad, $tipo));

            // Registrar en log
            $allRecipients = collect([$toEmail])->merge($ccEmails);
            foreach ($allRecipients as $email) {
                $user = User::where('email', $email)->first();
                $rolDest = 'responsable';
                if ($email !== $responsableEmail) {
                    $isSuperAdmin = $superAdmins->contains($email);
                    $rolDest = $isSuperAdmin ? 'superadmin' : 'jefe';
                }
                SstNotificacionLog::create([
                    'actividad_id'    => $actividad->id,
                    'user_id'         => $user?->id,
                    'email'           => $email,
                    'tipo'            => $tipo,
                    'mes'             => $mes,
                    'rol_destinatario' => $rolDest,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("SST Notificación ({$tipo}): no se pudo enviar para actividad #{$actividad->id}: {$e->getMessage()}");
        }
    }

    private function recalcularEstadoActividad(SstActividad $actividad): void
    {
        $actividad->load('seguimiento');
        $programados = $actividad->seguimiento->where('programado', true)->count();
        $realizados  = $actividad->seguimiento->where('realizado', true)->count();

        if ($programados > 0 && $realizados >= $programados) {
            $actividad->update(['estado' => 'COMPLETADA']);
        } elseif ($realizados > 0) {
            $actividad->update(['estado' => 'EN_PROGRESO']);
        }
    }

    // =====================================================
    // IMPORTACIÓN MASIVA CSV
    // =====================================================

    public function descargarPlantilla()
    {
        $headers = ['categoria', 'nombre', 'responsable_email', 'prioridad', 'periodicidad', 'fecha_inicio', 'fecha_fin', 'meses_programados'];
        $ejemplo = ['Capacitaciones', 'Inducción SST nuevos trabajadores', 'bmachero@saep.cl', 'ALTA', 'MENSUAL', '2026-01-15', '2026-12-31', '1,3,6,9,12'];

        $callback = function () use ($headers, $ejemplo) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
            fputcsv($f, $headers, ';');
            fputcsv($f, $ejemplo, ';');
            fclose($f);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_actividades_sst.csv"',
        ]);
    }

    public function importarActividades(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $request->file('archivo')->getRealPath();
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            // Detectar BOM
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
                rewind($handle);
            }
            $headers = fgetcsv($handle, 0, ';');
            if ($headers) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $headers);
                while (($line = fgetcsv($handle, 0, ';')) !== false) {
                    if (count($line) === count($headers)) {
                        $rows[] = array_combine($headers, $line);
                    }
                }
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return back()->with('error', 'El archivo está vacío o el formato no es válido. Use la plantilla CSV con separador punto y coma (;).');
        }

        $creadas = 0;
        $errores = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $fila = $i + 2; // Línea en el CSV (1=headers)

                $catNombre = trim($row['categoria'] ?? '');
                $nombre    = trim($row['nombre'] ?? '');

                if (!$catNombre || !$nombre) {
                    $errores[] = "Fila {$fila}: categoría y nombre son obligatorios.";
                    continue;
                }

                // Buscar o crear categoría
                $categoria = $cartaGantt->categorias()->firstOrCreate(
                    ['nombre' => $catNombre],
                    ['orden' => $cartaGantt->categorias()->max('orden') + 1]
                );

                // Buscar responsable por email
                $responsableId = null;
                $responsableNombre = null;
                $email = trim($row['responsable_email'] ?? '');
                if ($email) {
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        $responsableId = $user->id;
                        $responsableNombre = $user->nombre_completo;
                    } else {
                        $responsableNombre = $email;
                    }
                }

                $prioridad = strtoupper(trim($row['prioridad'] ?? 'MEDIA'));
                if (!in_array($prioridad, ['ALTA', 'MEDIA', 'BAJA'])) $prioridad = 'MEDIA';

                $periodicidad = strtoupper(trim($row['periodicidad'] ?? ''));
                if ($periodicidad && !array_key_exists($periodicidad, SstActividad::periodicidadesMap())) {
                    $periodicidad = null;
                }

                $fechaInicio = !empty($row['fecha_inicio']) ? date('Y-m-d', strtotime($row['fecha_inicio'])) : null;
                $fechaFin    = !empty($row['fecha_fin']) ? date('Y-m-d', strtotime($row['fecha_fin'])) : null;

                $actividad = $categoria->actividades()->create([
                    'nombre'         => $nombre,
                    'responsable'    => $responsableNombre,
                    'responsable_id' => $responsableId,
                    'prioridad'      => $prioridad,
                    'periodicidad'   => $periodicidad ?: null,
                    'fecha_inicio'   => $fechaInicio,
                    'fecha_fin'      => $fechaFin,
                    'orden'          => $categoria->actividades()->max('orden') + 1,
                ]);

                // Meses programados: "1,3,6,9,12" o auto-calcular desde periodicidad
                $mesesStr = trim($row['meses_programados'] ?? '');
                $mesesProg = [];
                if ($mesesStr) {
                    foreach (explode(',', $mesesStr) as $mes) {
                        $mes = (int) trim($mes);
                        if ($mes >= 1 && $mes <= 12) {
                            $mesesProg[] = $mes;
                        }
                    }
                }

                // Si no se especificaron meses pero hay periodicidad: auto-calcular
                if (empty($mesesProg) && $periodicidad) {
                    $mesesProg = SstActividad::mesesProgramadosPorPeriodicidad($periodicidad);
                }

                // Crear seguimiento para meses programados
                foreach ($mesesProg as $mes) {
                    $actividad->seguimiento()->create([
                        'mes' => $mes,
                        'programado' => true,
                    ]);
                }

                // Auto-asignar fechas si no se especificaron
                if (!$fechaInicio && !empty($mesesProg)) {
                    $anio = $cartaGantt->anio ?? date('Y');
                    $actividad->update(['fecha_inicio' => \Carbon\Carbon::create($anio, min($mesesProg), 1)->toDateString()]);
                }
                if (!$fechaFin && !empty($mesesProg)) {
                    $anio = $cartaGantt->anio ?? date('Y');
                    $actividad->update(['fecha_fin' => \Carbon\Carbon::create($anio, max($mesesProg))->endOfMonth()->toDateString()]);
                }

                $creadas++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error importando actividades SST: ' . $e->getMessage());
            return back()->with('error', 'Error al importar: por favor revise el formato del archivo.');
        }

        $msg = "{$creadas} actividades importadas correctamente.";
        if (!empty($errores)) {
            $msg .= ' Advertencias: ' . implode(' | ', array_slice($errores, 0, 5));
        }

        return back()->with('success', $msg);
    }
}
