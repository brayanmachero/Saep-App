<?php

namespace App\Http\Controllers;

use App\Models\ProgramaSst;
use App\Models\Rol;
use App\Models\Configuracion;
use App\Models\SstCategoria;
use App\Models\SstActividad;
use App\Models\SstNotificacionLog;
use App\Models\SstSeguimiento;
use App\Models\SstPlanAccion;
use App\Models\SstReprogramacion;
use App\Models\SstActividadComentario;
use App\Models\SstActividadLog;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\SstActividadAlertaMail;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CartaGanttController extends Controller
{
    // =====================================================
    // PROGRAMA SST (CRUD)
    // =====================================================

    public function index(Request $request)
    {
        $user = $request->user();
        $query = ProgramaSst::with(['creador', 'centroCosto', 'responsable', 'asignados']);
        $this->scopeVisibleProgramas($query, $user);

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

        $programasVisibles = ProgramaSst::query();
        $this->scopeVisibleProgramas($programasVisibles, $user);

        $actividadesVisibles = SstActividad::whereHas('categoria.programa', function ($q) use ($user) {
            $this->scopeVisibleProgramas($q, $user);
        });

        $stats = [
            'total'   => (clone $programasVisibles)->count(),
            'activos' => (clone $programasVisibles)->where('estado', 'ACTIVO')->count(),
            'vencidas' => (clone $actividadesVisibles)->where('fecha_fin', '<', now())
                            ->where('estado', '!=', 'COMPLETADA')
                            ->where('estado', '!=', 'CANCELADA')->count(),
        ];

        $centros = CentroCosto::orderBy('nombre')->get();
        $aniosQuery = ProgramaSst::query();
        $this->scopeVisibleProgramas($aniosQuery, $user);
        $anios = $aniosQuery->distinct()->orderByDesc('anio')->pluck('anio');
        $puedeAccesoGlobal = $this->canAccessAllProgramas($user);

        return view('carta_gantt.index', compact('programas', 'stats', 'centros', 'anios', 'puedeAccesoGlobal'));
    }

    public function create()
    {
        abort_unless(auth()->user()?->tieneAcceso('carta_gantt', 'puede_crear'), 403);

        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->tieneAcceso('carta_gantt', 'puede_crear'), 403);

        $request->validate([
            'anio'            => 'required|integer|min:2020|max:2099',
            'nombre'          => 'required|string|max:300',
            'descripcion'     => 'nullable|string',
            'estado'          => 'required|string|in:BORRADOR,ACTIVO,CERRADO',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
            'responsable_id'  => 'nullable|exists:users,id',
            'asignados'       => 'nullable|array',
            'asignados.*'     => 'integer|exists:users,id',
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

        $asignacion = $this->syncProgramaAsignados($programa, $request);
        $this->registrarProgramaLog($programa, 'programa_creado', 'Programa creado.', [
            'programa' => $this->programaSnapshot($programa->fresh('asignados')),
            'equipo_asignado' => $asignacion['despues'] ?? [],
        ], $request);

        return redirect()->route('carta-gantt.show', $programa)
            ->with('success', "Programa SST creado — Código: {$programa->codigo}");
    }

    public function show(ProgramaSst $cartaGantt)
    {
        $this->abortUnlessCanViewPrograma($cartaGantt);

        $cartaGantt->load([
            'categorias.actividades.seguimiento',
            'categorias.actividades.responsableUser',
            'categorias.actividades.planesAccion',
            'categorias.actividades.reprogramaciones.usuario',
            'categorias.actividades.comentarios.usuario',
            'categorias.actividades.logs.usuario',
            'centroCosto', 'responsable', 'creador', 'asignados', 'logs.usuario',
        ]);
        $usuarios = User::orderBy('name')->get();

        $puedeCrear = $this->canAdministratePrograma($cartaGantt, 'puede_crear');
        $puedeEditar = $this->canManageProgramaStructure($cartaGantt, 'puede_editar');
        $puedeEliminar = $this->canManageProgramaStructure($cartaGantt, 'puede_eliminar');
        $puedeAdministrarPrograma = $this->canAdministratePrograma($cartaGantt, 'puede_editar');
        $puedeGestionarActividades = $puedeEditar;
        $puedeEliminarEstructura = $puedeEliminar;

        return view('carta_gantt.show', compact(
            'cartaGantt',
            'usuarios',
            'puedeCrear',
            'puedeEditar',
            'puedeEliminar',
            'puedeAdministrarPrograma',
            'puedeGestionarActividades',
            'puedeEliminarEstructura'
        ));
    }

    public function exportPdf(ProgramaSst $cartaGantt)
    {
        $this->abortUnlessCanViewPrograma($cartaGantt);

        $cartaGantt->load([
            'categorias.actividades.seguimiento',
            'categorias.actividades.responsableUser',
            'categorias.actividades.planesAccion',
            'categorias.actividades.reprogramaciones.usuario',
            'centroCosto', 'responsable', 'creador', 'asignados',
        ]);

        $mesActual   = (int) date('n');
        $mesesNombres = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        // Collect all activities
        $todasActividades = $cartaGantt->categorias->flatMap->actividades;
        $totalAct = $todasActividades->count();

        // States
        $completadas = $todasActividades->where('estado', 'COMPLETADA')->count();
        $enProgreso  = $todasActividades->where('estado', 'EN_PROGRESO')->count();
        $canceladas  = $todasActividades->where('estado', 'CANCELADA')->count();
        $pendientes  = $totalAct - $completadas - $enProgreso - $canceladas;

        // Global advance
        $pct = $cartaGantt->porcentajeRealizado;

        // Monthly progress (programado vs realizado per month)
        $mesesData = [];
        for ($m = 1; $m <= 12; $m++) {
            $prog = 0; $real = 0;
            foreach ($todasActividades as $act) {
                $cantProg = max(1, (int) $act->cantidad_programada);
                foreach ($act->seguimiento as $seg) {
                    if ($seg->mes === $m && $seg->programado) {
                        $prog += $cantProg;
                        $real += $seg->realizado ? $cantProg : ((int) $seg->cantidad_realizada > 0 ? (int) $seg->cantidad_realizada : 0);
                    }
                }
            }
            $mesesData[$m] = ['prog' => $prog, 'real' => $real, 'pct' => $prog > 0 ? round(($real / $prog) * 100) : 0];
        }

        // Activities with issues
        $vencidas   = $todasActividades->filter(fn ($a) => $a->estaVencida)->values();

        // Reprogramaciones
        $reprogramaciones = SstReprogramacion::whereIn('actividad_id', $todasActividades->pluck('id'))
            ->with(['actividad', 'usuario'])
            ->orderByDesc('created_at')
            ->get();

        // Priority distribution
        $prioridades = [
            'ALTA'  => $todasActividades->where('prioridad', 'ALTA')->count(),
            'MEDIA' => $todasActividades->where('prioridad', 'MEDIA')->count(),
            'BAJA'  => $todasActividades->where('prioridad', 'BAJA')->count(),
        ];

        $pdf = Pdf::loadView('pdf.carta_gantt_reporte', compact(
            'cartaGantt', 'mesActual', 'mesesNombres', 'totalAct',
            'completadas', 'enProgreso', 'pendientes', 'canceladas', 'pct',
            'mesesData', 'vencidas', 'reprogramaciones', 'prioridades'
        ))->setPaper('a4', 'landscape')->setOptions([
            'isRemoteEnabled'      => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled'         => true,
            'defaultFont'          => 'DejaVu Sans',
            'dpi'                  => 130,
        ]);

        $filename = "Reporte_{$cartaGantt->codigo}_" . date('Ymd') . '.pdf';
        return $pdf->download($filename);
    }

    public function edit(ProgramaSst $cartaGantt)
    {
        $this->abortUnlessCanAdministratePrograma($cartaGantt);

        $cartaGantt->load('asignados');
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.edit', compact('cartaGantt', 'centros', 'usuarios'));
    }

    public function update(Request $request, ProgramaSst $cartaGantt)
    {
        $this->abortUnlessCanAdministratePrograma($cartaGantt);
        $cartaGantt->loadMissing('asignados');
        $antes = $this->programaSnapshot($cartaGantt);

        $request->validate([
            'nombre'          => 'required|string|max:300',
            'anio'            => 'required|integer|min:2020|max:2099',
            'estado'          => 'required|string|in:BORRADOR,ACTIVO,CERRADO',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
            'responsable_id'  => 'nullable|exists:users,id',
            'asignados'       => 'nullable|array',
            'asignados.*'     => 'integer|exists:users,id',
        ]);

        $cartaGantt->update([
            'titulo'          => $request->nombre,
            'anio'            => $request->anio,
            'descripcion'     => $request->descripcion,
            'estado'          => $request->estado,
            'centro_costo_id' => $request->centro_costo_id,
            'responsable_id'  => $request->responsable_id,
        ]);

        $asignacion = $this->syncProgramaAsignados($cartaGantt, $request);
        $cartaGantt->refresh()->load('asignados');
        $this->registrarProgramaLog($cartaGantt, 'programa_actualizado', 'Programa actualizado.', [
            'campos' => $this->diffCambios($antes, $this->programaSnapshot($cartaGantt)),
            'equipo_asignado' => $asignacion['cambio'] ? $asignacion : null,
        ], $request);

        return redirect()->route('carta-gantt.show', $cartaGantt)
            ->with('success', 'Programa actualizado.');
    }

    public function destroy(ProgramaSst $cartaGantt)
    {
        $this->abortUnlessCanAdministratePrograma($cartaGantt, 'puede_eliminar');

        $cartaGantt->update(['estado' => 'CERRADO']);
        $this->registrarProgramaLog($cartaGantt, 'programa_cerrado', 'Programa cerrado.', [
            'estado' => 'CERRADO',
        ], request());
        return redirect()->route('carta-gantt.index')
            ->with('success', 'Programa cerrado correctamente.');
    }

    // =====================================================
    // CATEGORÍAS
    // =====================================================

    public function storeCategoria(Request $request, ProgramaSst $cartaGantt)
    {
        $this->abortUnlessCanAdministratePrograma($cartaGantt, 'puede_crear');

        $request->validate([
            'nombre' => 'required|string|max:200',
            'orden'  => 'nullable|integer|min:1',
        ]);
        $categoria = $cartaGantt->categorias()->create([
            'nombre' => $request->nombre,
            'orden'  => $request->orden ?? ($cartaGantt->categorias()->max('orden') + 1),
        ]);
        $this->registrarProgramaLog($cartaGantt, 'categoria_creada', 'Categoria creada.', [
            'categoria_id' => $categoria->id,
            'nombre' => $categoria->nombre,
            'orden' => $categoria->orden,
        ], $request);
        return back()->with('success', 'Categoría agregada.');
    }

    public function destroyCategoria(SstCategoria $categoria)
    {
        $categoria->loadMissing('programa');
        $this->abortUnlessCanManageProgramaStructure($categoria->programa, 'puede_eliminar');

        $this->registrarProgramaLog($categoria->programa, 'categoria_eliminada', 'Categoria eliminada.', [
            'categoria_id' => $categoria->id,
            'nombre' => $categoria->nombre,
            'orden' => $categoria->orden,
        ], request());

        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    // =====================================================
    // ACTIVIDADES
    // =====================================================

    public function storeActividad(Request $request, SstCategoria $categoria)
    {
        $categoria->loadMissing('programa');
        $this->abortUnlessCanAdministratePrograma($categoria->programa, 'puede_crear');

        $request->validate([
            'nombre'              => 'required|string|max:300',
            'responsable_id'      => 'nullable|exists:users,id',
            'fecha_inicio'        => 'nullable|date',
            'fecha_fin'           => 'nullable|date|after_or_equal:fecha_inicio',
            'prioridad'           => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'periodicidad'        => 'nullable|string|in:' . implode(',', array_keys(SstActividad::periodicidadesMap())),
            'meses_prog'          => 'nullable|array',
            'meses_prog.*'        => 'integer|min:1|max:12',
            'cantidad_programada' => 'nullable|integer|min:1|max:999',
        ]);

        $actividad = $categoria->actividades()->create([
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'responsable'         => $request->responsable_id
                ? User::find($request->responsable_id)?->nombre_completo
                : $request->responsable_nombre,
            'responsable_id'      => $request->responsable_id,
            'fecha_inicio'        => $request->fecha_inicio,
            'fecha_fin'           => $request->fecha_fin,
            'prioridad'           => $request->prioridad ?? 'MEDIA',
            'periodicidad'        => $request->periodicidad,
            'cantidad_programada' => $request->cantidad_programada ?? 1,
            'orden'               => $categoria->actividades()->max('orden') + 1,
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
        $this->registrarActividadLog($actividad, 'actividad_creada', 'Actividad creada.', [
            'actividad' => $this->actividadSnapshot($actividad->fresh(['seguimiento'])),
            'meses_programados' => collect($mesesProg)->map(fn ($mes) => (int) $mes)->values()->all(),
        ], $request);

        return back()->with('success', 'Actividad agregada.');
    }

    public function updateActividad(Request $request, SstActividad $actividad)
    {
        $this->abortUnlessCanManageActividadStructure($actividad);
        $actividad->loadMissing('seguimiento');
        $antes = $this->actividadSnapshot($actividad);
        $mesesAntes = $actividad->seguimiento
            ->where('programado', true)
            ->pluck('mes')
            ->map(fn ($mes) => (int) $mes)
            ->values()
            ->all();

        $request->validate([
            'nombre'              => 'required|string|max:300',
            'responsable_id'      => 'nullable|exists:users,id',
            'fecha_inicio'        => 'nullable|date',
            'fecha_fin'           => 'nullable|date|after_or_equal:fecha_inicio',
            'prioridad'           => 'nullable|string|in:ALTA,MEDIA,BAJA',
            'estado'              => 'nullable|string|in:' . implode(',', array_keys(SstActividad::estadosMap())),
            'periodicidad'        => 'nullable|string|in:' . implode(',', array_keys(SstActividad::periodicidadesMap())),
            'meses_prog'          => 'nullable|array',
            'meses_prog.*'        => 'integer|min:1|max:12',
            'cantidad_programada' => 'nullable|integer|min:1|max:999',
        ]);

        $actividad->update([
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'responsable'         => $request->responsable_id
                ? User::find($request->responsable_id)?->nombre_completo
                : ($request->responsable_nombre ?? $actividad->responsable),
            'responsable_id'      => $request->responsable_id,
            'fecha_inicio'        => $request->fecha_inicio,
            'fecha_fin'           => $request->fecha_fin,
            'prioridad'           => $request->prioridad ?? $actividad->prioridad,
            'estado'              => $request->estado ?? $actividad->estado,
            'periodicidad'        => $request->periodicidad,
            'cantidad_programada' => $request->cantidad_programada ?? $actividad->cantidad_programada,
        ]);

        // Actualizar meses programados si se enviaron checkboxes
        if ($request->has('has_meses_prog')) {
            $mesesSeleccionados = collect($request->get('meses_prog', []))->map(fn($m) => (int) $m)->filter(fn($m) => $m >= 1 && $m <= 12);

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
        $actividad->refresh()->load('seguimiento');
        $despues = $this->actividadSnapshot($actividad);
        $mesesDespues = $actividad->seguimiento
            ->where('programado', true)
            ->pluck('mes')
            ->map(fn ($mes) => (int) $mes)
            ->values()
            ->all();

        $this->registrarActividadLog($actividad, 'actividad_actualizada', 'Actividad actualizada.', [
            'campos' => $this->diffCambios($antes, $despues),
            'meses_programados' => $this->diffCambios(['meses' => $mesesAntes], ['meses' => $mesesDespues])['meses'] ?? null,
        ], $request);

        return back()->with('success', 'Actividad actualizada.');
    }

    public function destroyActividad(SstActividad $actividad)
    {
        $this->abortUnlessCanManageActividadStructure($actividad, 'puede_eliminar');
        $actividad->loadMissing('seguimiento');
        $this->registrarActividadLog($actividad, 'actividad_eliminada', 'Actividad eliminada.', [
            'actividad' => $this->actividadSnapshot($actividad),
        ], request());

        $actividad->delete();
        return back()->with('success', 'Actividad eliminada.');
    }

    // =====================================================
    // SEGUIMIENTO (AJAX)
    // =====================================================

    public function updateSeguimiento(Request $request, SstActividad $actividad)
    {
        if (!$this->canManageActividadStructure($actividad)) {
            return response()->json(['error' => 'No tiene permiso para editar seguimiento.'], 403);
        }

        $request->validate([
            'mes'         => 'required|integer|min:1|max:12',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $cantProg = max(1, (int) $actividad->cantidad_programada);
        $seg = $actividad->seguimiento()->where('mes', $request->mes)->first();
        $antes = $seg ? $seg->only(['programado', 'realizado', 'cantidad_realizada', 'observacion']) : null;

        if ($cantProg <= 1) {
            // Comportamiento original: toggle binario
            $nuevoRealizado = $seg ? !$seg->realizado : true;
            $nuevaCantReal  = $nuevoRealizado ? 1 : 0;
        } else {
            // Contador: incrementar, si llega al máximo → resetear a 0
            $cantActual    = $seg ? (int) $seg->cantidad_realizada : 0;
            $nuevaCantReal = $cantActual >= $cantProg ? 0 : $cantActual + 1;
            $nuevoRealizado = $nuevaCantReal >= $cantProg;
        }

        $segActualizado = $actividad->seguimiento()->updateOrCreate(
            ['mes' => $request->mes],
            [
                'programado'          => true,
                'realizado'           => $nuevoRealizado,
                'cantidad_realizada'  => $nuevaCantReal,
                'observacion'         => $request->observacion,
                'actualizado_por'     => auth()->id(),
                'fecha_actualizacion' => now(),
            ]
        );

        $this->recalcularEstadoActividad($actividad);
        $this->registrarActividadLog($actividad, 'seguimiento_actualizado', "Seguimiento actualizado para mes {$request->mes}.", [
            'mes' => (int) $request->mes,
            'antes' => $antes,
            'despues' => $segActualizado->only(['programado', 'realizado', 'cantidad_realizada', 'observacion']),
        ], $request);

        return response()->json([
            'success'            => true,
            'realizado'          => $nuevoRealizado,
            'cantidad_realizada' => $nuevaCantReal,
            'cantidad_programada'=> $cantProg,
            'estado'             => $actividad->fresh()->estado,
        ]);
    }

    // =====================================================
    // PLAN DE ACCIÓN
    // =====================================================

    public function storePlanAccion(Request $request, SstActividad $actividad)
    {
        $this->abortUnlessCanManageActividadStructure($actividad);

        $request->validate([
            'accion'            => 'required|string|max:500',
            'responsable'       => 'nullable|string|max:200',
            'fecha_compromiso'  => 'nullable|date',
        ]);

        $plan = $actividad->planesAccion()->create([
            'accion'           => $request->accion,
            'responsable'      => $request->responsable,
            'fecha_compromiso' => $request->fecha_compromiso,
            'estado'           => 'PENDIENTE',
            'observacion'      => $request->observacion,
            'creado_por'       => auth()->id(),
        ]);
        $this->registrarActividadLog($actividad, 'plan_creado', 'Plan de accion creado.', [
            'plan_id' => $plan->id,
            'plan' => $plan->only(['accion', 'responsable', 'fecha_compromiso', 'estado', 'observacion']),
        ], $request);

        return back()->with('success', 'Plan de acción creado.');
    }

    public function updatePlanAccion(Request $request, SstPlanAccion $plan)
    {
        $plan->loadMissing('actividad.categoria.programa');
        $this->abortUnlessCanManageActividadStructure($plan->actividad);
        $antes = $plan->only(['estado', 'observacion']);

        $request->validate([
            'estado'      => 'required|string|in:' . implode(',', array_keys(SstPlanAccion::estadosMap())),
            'observacion' => 'nullable|string',
        ]);

        $plan->update([
            'estado'      => $request->estado,
            'observacion' => $request->observacion,
        ]);
        $plan->refresh();
        $this->registrarActividadLog($plan->actividad, 'plan_actualizado', 'Plan de accion actualizado.', [
            'plan_id' => $plan->id,
            'campos' => $this->diffCambios($antes, $plan->only(['estado', 'observacion'])),
        ], $request);

        return back()->with('success', 'Plan de acción actualizado.');
    }

    public function destroyPlanAccion(SstPlanAccion $plan)
    {
        $plan->loadMissing('actividad.categoria.programa');
        $this->abortUnlessCanManageActividadStructure($plan->actividad, 'puede_eliminar');
        $this->registrarActividadLog($plan->actividad, 'plan_eliminado', 'Plan de accion eliminado.', [
            'plan_id' => $plan->id,
            'plan' => $plan->only(['accion', 'responsable', 'fecha_compromiso', 'estado', 'observacion']),
        ], request());

        $plan->delete();
        return back()->with('success', 'Plan de acción eliminado.');
    }

    // =====================================================
    // COMENTARIOS OPERATIVOS
    // =====================================================

    public function storeComentario(Request $request, SstActividad $actividad)
    {
        $this->abortUnlessCanManageActividadStructure($actividad);

        $request->validate([
            'comentario' => 'required|string|max:1000',
        ]);

        $comentario = $actividad->comentarios()->create([
            'user_id' => auth()->id(),
            'comentario' => trim($request->comentario),
        ]);
        $this->registrarActividadLog($actividad, 'comentario_creado', 'Comentario agregado.', [
            'comentario_id' => $comentario->id,
            'comentario' => $comentario->comentario,
        ], $request);

        return back()->with('success', 'Comentario agregado.');
    }

    public function destroyComentario(SstActividadComentario $comentario)
    {
        $comentario->loadMissing('actividad.categoria.programa');
        $actividad = $comentario->actividad;
        $programa = $actividad?->categoria?->programa;
        $user = auth()->user();

        $esAutor = $user && (int) $comentario->user_id === (int) $user->id;
        $puedeEliminar = ($esAutor && $this->canManageActividadStructure($actividad))
            || ($programa && $this->canAdministratePrograma($programa, 'puede_eliminar'));

        abort_unless($puedeEliminar, 403);
        $this->registrarActividadLog($actividad, 'comentario_eliminado', 'Comentario eliminado.', [
            'comentario_id' => $comentario->id,
            'comentario' => $comentario->comentario,
            'autor_id' => $comentario->user_id,
        ], request());

        $comentario->delete();

        return back()->with('success', 'Comentario eliminado.');
    }

    // =====================================================
    // REPROGRAMACIÓN
    // =====================================================

    public function reprogramarActividad(Request $request, SstActividad $actividad)
    {
        $actividad->loadMissing('categoria.programa');
        $this->abortUnlessCanManageActividadStructure($actividad);

        $request->validate([
            'mes_original' => 'required|integer|min:1|max:12',
            'mes_nuevo'    => 'required|integer|min:1|max:12|different:mes_original',
            'motivo'       => 'required|string|max:500',
        ]);

        $mesOrig = (int) $request->mes_original;
        $mesNuevo = (int) $request->mes_nuevo;
        $anioPrograma = (int) ($actividad->categoria?->programa?->anio ?? now()->year);
        $anioActual = (int) now()->year;
        $mesActual = (int) now()->month;

        if ($anioPrograma < $anioActual) {
            return back()->withErrors([
                'mes_nuevo' => 'No se puede reprogramar una actividad de un programa de años anteriores.',
            ]);
        }

        if ($anioPrograma === $anioActual && $mesNuevo < $mesActual) {
            return back()->withErrors([
                'mes_nuevo' => 'El nuevo mes debe ser el mes actual o uno posterior.',
            ]);
        }

        $seguimientoOriginal = $actividad->seguimiento()->where('mes', $mesOrig)->first();
        if (!$seguimientoOriginal || !$seguimientoOriginal->programado) {
            return back()->withErrors([
                'mes_original' => 'El mes seleccionado no está programado para esta actividad.',
            ]);
        }

        if ($seguimientoOriginal->realizado || (int) $seguimientoOriginal->cantidad_realizada > 0) {
            return back()->withErrors([
                'mes_original' => 'No se puede reprogramar un mes con avance registrado. Revise el seguimiento antes de moverlo.',
            ]);
        }

        DB::transaction(function () use ($actividad, $mesOrig, $mesNuevo, $request) {
            // Log the reprogramming
            SstReprogramacion::create([
                'actividad_id'     => $actividad->id,
                'mes_original'     => $mesOrig,
                'mes_nuevo'        => $mesNuevo,
                'motivo'           => $request->motivo,
                'reprogramado_por' => auth()->id(),
            ]);

            // Remove seguimiento from original month (mark as not programmed)
            $actividad->seguimiento()->where('mes', $mesOrig)->update([
                'programado'          => false,
                'actualizado_por'     => auth()->id(),
                'fecha_actualizacion' => now(),
            ]);

            // Create/update seguimiento for new month as programmed
            $actividad->seguimiento()->updateOrCreate(
                ['mes' => $mesNuevo],
                [
                    'programado'          => true,
                    'actualizado_por'     => auth()->id(),
                    'fecha_actualizacion' => now(),
                ]
            );
        });

        $this->recalcularEstadoActividad($actividad);
        $this->registrarActividadLog($actividad, 'actividad_reprogramada', 'Actividad reprogramada.', [
            'mes_original' => $mesOrig,
            'mes_nuevo' => $mesNuevo,
            'motivo' => $request->motivo,
        ], $request);

        $meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        return back()->with('success', "Actividad reprogramada de {$meses[$mesOrig]} a {$meses[$mesNuevo]}.");
    }

    // =====================================================
    // HELPERS
    // =====================================================

    private function syncProgramaAsignados(ProgramaSst $programa, Request $request): array
    {
        $programa->loadMissing('asignados');
        $antesIds = $programa->asignados
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $asignados = collect($request->input('asignados', []))
            ->push($request->input('responsable_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $programa->asignados()->sync($asignados);
        $despuesIds = collect($asignados)->sort()->values()->all();

        return [
            'antes' => $this->usuariosResumenPorIds($antesIds),
            'despues' => $this->usuariosResumenPorIds($despuesIds),
            'agregados' => $this->usuariosResumenPorIds(array_values(array_diff($despuesIds, $antesIds))),
            'removidos' => $this->usuariosResumenPorIds(array_values(array_diff($antesIds, $despuesIds))),
            'cambio' => $antesIds !== $despuesIds,
        ];
    }

    private function usuariosResumenPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'nombre' => $user->nombre_completo,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    private function canAccessAllProgramas(?User $user): bool
    {
        if (!$user || !$user->rol) {
            return false;
        }

        if ($user->esSuperAdmin() || $user->esAdminSistema()) {
            return true;
        }

        $codigo = strtoupper((string) ($user->rol->codigo ?? ''));
        $nombre = strtoupper((string) ($user->rol->nombre ?? ''));

        return str_contains($codigo, 'COORDIN')
            || str_contains($nombre, 'COORDIN')
            || str_contains($codigo, 'JEFE')
            || str_contains($nombre, 'JEFE')
            || str_contains($codigo, 'PREVENCION')
            || str_contains($nombre, 'PREVENCION');
    }

    private function scopeVisibleProgramas($query, User $user): void
    {
        if ($this->canAccessAllProgramas($user)) {
            return;
        }

        $query->where(function ($q) use ($user) {
            $q->where('creado_por', $user->id)
                ->orWhere('responsable_id', $user->id)
                ->orWhereHas('asignados', fn ($asignados) => $asignados->where('users.id', $user->id))
                ->orWhereHas('categorias.actividades', fn ($actividad) => $actividad->where('responsable_id', $user->id));
        });
    }

    private function canViewPrograma(ProgramaSst $programa): bool
    {
        $user = auth()->user();

        if (!$user || !$user->tieneAcceso('carta_gantt', 'puede_ver')) {
            return false;
        }

        return $this->canAccessAllProgramas($user)
            || (int) $programa->creado_por === (int) $user->id
            || $programa->estaAsignadoA($user)
            || $this->hasResponsibleActivity($programa, $user);
    }

    private function abortUnlessCanViewPrograma(ProgramaSst $programa): void
    {
        abort_unless($this->canViewPrograma($programa), 403);
    }

    private function hasResponsibleActivity(ProgramaSst $programa, User $user): bool
    {
        return $programa->categorias()
            ->whereHas('actividades', fn ($actividad) => $actividad->where('responsable_id', $user->id))
            ->exists();
    }

    private function canAdministratePrograma(ProgramaSst $programa, string $accion = 'puede_editar'): bool
    {
        $user = auth()->user();

        if (!$user || !$user->tieneAcceso('carta_gantt', $accion)) {
            return false;
        }

        return $this->canAccessAllProgramas($user)
            || (int) $programa->creado_por === (int) $user->id;
    }

    private function abortUnlessCanAdministratePrograma(ProgramaSst $programa, string $accion = 'puede_editar'): void
    {
        abort_unless($this->canAdministratePrograma($programa, $accion), 403);
    }

    private function canManageProgramaStructure(?ProgramaSst $programa, string $accion = 'puede_editar'): bool
    {
        if (!$programa) {
            return false;
        }

        $user = auth()->user();

        if (!$user || !$user->tieneAcceso('carta_gantt', 'puede_ver')) {
            return false;
        }

        if ($accion === 'puede_eliminar') {
            return $this->canAdministratePrograma($programa, $accion);
        }

        if ($programa->estaAsignadoA($user)) {
            return true;
        }

        if (!$user->tieneAcceso('carta_gantt', $accion)) {
            return false;
        }

        return $this->canAccessAllProgramas($user)
            || (int) $programa->creado_por === (int) $user->id;
    }

    private function abortUnlessCanManageProgramaStructure(?ProgramaSst $programa, string $accion = 'puede_editar'): void
    {
        abort_unless($this->canManageProgramaStructure($programa, $accion), 403);
    }

    private function canManageActividadStructure(?SstActividad $actividad, string $accion = 'puede_editar'): bool
    {
        if (!$actividad) {
            return false;
        }

        $actividad->loadMissing('categoria.programa.asignados');

        return $this->canManageProgramaStructure($actividad->categoria?->programa, $accion);
    }

    private function abortUnlessCanManageActividadStructure(?SstActividad $actividad, string $accion = 'puede_editar'): void
    {
        abort_unless($this->canManageActividadStructure($actividad, $accion), 403);
    }

    private function registrarProgramaLog(?ProgramaSst $programa, string $accion, string $resumen, array $cambios = [], ?Request $request = null): void
    {
        if (!$programa) {
            return;
        }

        try {
            $request ??= request();

            SstActividadLog::create([
                'programa_id' => $programa->id,
                'actividad_id' => null,
                'user_id' => auth()->id(),
                'accion' => $accion,
                'resumen' => $resumen,
                'cambios' => array_filter($cambios, fn ($value) => $value !== null && $value !== []),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Carta Gantt: no se pudo registrar bitacora de programa {$accion}", [
                'programa_id' => $programa->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function registrarActividadLog(?SstActividad $actividad, string $accion, string $resumen, array $cambios = [], ?Request $request = null): void
    {
        if (!$actividad) {
            return;
        }

        try {
            $actividad->loadMissing('categoria.programa');
            $request ??= request();

            SstActividadLog::create([
                'programa_id' => $actividad->categoria?->programa?->id,
                'actividad_id' => $actividad->exists ? $actividad->id : null,
                'user_id' => auth()->id(),
                'accion' => $accion,
                'resumen' => $resumen,
                'cambios' => array_filter($cambios, fn ($value) => $value !== null && $value !== []),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Carta Gantt: no se pudo registrar bitacora {$accion}", [
                'actividad_id' => $actividad->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function programaSnapshot(ProgramaSst $programa): array
    {
        return [
            'titulo' => $programa->titulo,
            'anio' => (int) $programa->anio,
            'descripcion' => $programa->descripcion,
            'estado' => $programa->estado,
            'centro_costo_id' => $programa->centro_costo_id,
            'responsable_id' => $programa->responsable_id,
            'asignados' => $programa->relationLoaded('asignados')
                ? $programa->asignados->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()
                : [],
        ];
    }

    private function actividadSnapshot(SstActividad $actividad): array
    {
        return [
            'nombre' => $actividad->nombre,
            'descripcion' => $actividad->descripcion,
            'responsable_id' => $actividad->responsable_id,
            'responsable' => $actividad->responsable,
            'fecha_inicio' => optional($actividad->fecha_inicio)->toDateString(),
            'fecha_fin' => optional($actividad->fecha_fin)->toDateString(),
            'prioridad' => $actividad->prioridad,
            'estado' => $actividad->estado,
            'periodicidad' => $actividad->periodicidad,
            'cantidad_programada' => (int) ($actividad->cantidad_programada ?? 1),
        ];
    }

    private function diffCambios(array $antes, array $despues): array
    {
        $cambios = [];
        foreach (array_unique(array_merge(array_keys($antes), array_keys($despues))) as $campo) {
            $valorAntes = $antes[$campo] ?? null;
            $valorDespues = $despues[$campo] ?? null;

            if ($valorAntes !== $valorDespues) {
                $cambios[$campo] = [
                    'antes' => $valorAntes,
                    'despues' => $valorDespues,
                ];
            }
        }

        return $cambios;
    }

    /**
     * Envía email de actividad al responsable con CC al jefe del programa y superadmins.
     */
    private function enviarNotificacionActividad(SstActividad $actividad, string $tipo, ?int $mes = null): void
    {
        // Verificar si las notificaciones SST están activas
        $notifActiva = Configuracion::get('sst_notif_activa', 'true');
        if ($notifActiva === 'false' || $notifActiva === '0') {
            return;
        }

        // Verificar si el tipo de notificación específico está habilitado
        $tipoConfigMap = [
            'asignacion'            => 'sst_notif_asignacion',
            'vencimiento'           => 'sst_notif_vencimiento',
            'vencida'               => 'sst_notif_vencida',
            'recordatorio'          => 'sst_notif_recordatorio',
            'seguimiento_pendiente' => 'sst_notif_seguimiento',
        ];
        if (isset($tipoConfigMap[$tipo])) {
            $tipoActivo = Configuracion::get($tipoConfigMap[$tipo], 'true');
            if ($tipoActivo === 'false' || $tipoActivo === '0') {
                return;
            }
        }

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

        // Agregar CC adicionales desde configuración
        $ccAdicional = Configuracion::get('sst_notif_cc_adicional', '');
        if ($ccAdicional) {
            $extras = collect(preg_split('/[;,]+/', $ccAdicional))
                ->map(fn($e) => trim($e))
                ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->reject(fn($e) => $e === $responsableEmail);
            $ccEmails = $ccEmails->merge($extras)->unique();
        }

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

            // Notificación in-app para cada destinatario
            $tipoMap = ['asignacion' => 'info', 'vencimiento' => 'warning', 'vencida' => 'danger', 'recordatorio' => 'warning', 'seguimiento_pendiente' => 'warning'];
            $tituloMap = ['asignacion' => 'Actividad SST asignada', 'vencimiento' => 'Actividad SST por vencer', 'vencida' => 'Actividad SST vencida', 'recordatorio' => 'Recordatorio SST', 'seguimiento_pendiente' => 'Seguimiento SST pendiente'];
            $allUsers = collect([$toEmail])->merge($ccEmails)->unique();
            foreach ($allUsers as $ue) {
                User::where('email', $ue)->first()?->notify(new AppNotification(
                    $tituloMap[$tipo] ?? 'Alerta SST',
                    $actividad->nombre,
                    $tipoMap[$tipo] ?? 'info',
                    route('carta-gantt.show', $actividad->categoria->programa_id ?? 0)
                ));
            }

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
        // No auto-cambiar actividades canceladas manualmente
        if ($actividad->estado === 'CANCELADA') {
            return;
        }

        $actividad->load('seguimiento');
        $programados = $actividad->seguimiento->where('programado', true)->count();
        $realizados  = $actividad->seguimiento->where('realizado', true)->count();

        // También considerar progreso parcial (cantidad_realizada > 0 aunque no esté 100% realizado)
        $conProgresoParcial = $actividad->seguimiento
            ->where('programado', true)
            ->filter(fn($s) => (int) $s->cantidad_realizada > 0)
            ->count();

        if ($programados > 0 && $realizados >= $programados) {
            $actividad->update(['estado' => 'COMPLETADA']);
        } elseif ($realizados > 0 || $conProgresoParcial > 0) {
            $actividad->update(['estado' => 'EN_PROGRESO']);
        } elseif ($programados > 0) {
            $actividad->update(['estado' => 'PENDIENTE']);
        }
    }

    // =====================================================
    // IMPORTACIÓN MASIVA CSV
    // =====================================================

    public function descargarPlantilla()
    {
        $headers = ['categoria', 'nombre', 'responsable_email', 'prioridad', 'periodicidad', 'cantidad', 'fecha_inicio', 'fecha_fin', 'meses_programados'];
        $ejemplo = ['Capacitaciones', 'Inducción SST nuevos trabajadores', 'bmachero@saep.cl', 'ALTA', 'MENSUAL', '4', '2026-01-15', '2026-12-31', '1,3,6,9,12'];

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
        $this->abortUnlessCanAdministratePrograma($cartaGantt, 'puede_crear');

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

                try {
                    $fechaInicio = $this->parseCsvDate($row['fecha_inicio'] ?? null);
                    $fechaFin    = $this->parseCsvDate($row['fecha_fin'] ?? null);
                } catch (\InvalidArgumentException $e) {
                    $errores[] = "Fila {$fila}: {$e->getMessage()}";
                    continue;
                }

                if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) {
                    $errores[] = "Fila {$fila}: fecha_fin no puede ser anterior a fecha_inicio.";
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

                $actividad = $categoria->actividades()->create([
                    'nombre'              => $nombre,
                    'responsable'         => $responsableNombre,
                    'responsable_id'      => $responsableId,
                    'prioridad'           => $prioridad,
                    'periodicidad'        => $periodicidad ?: null,
                    'cantidad_programada' => max(1, (int) ($row['cantidad'] ?? 1)),
                    'fecha_inicio'        => $fechaInicio,
                    'fecha_fin'           => $fechaFin,
                    'orden'               => $categoria->actividades()->max('orden') + 1,
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

                $this->registrarActividadLog($actividad->fresh(['seguimiento']), 'actividad_importada', 'Actividad importada desde CSV.', [
                    'actividad' => $this->actividadSnapshot($actividad->fresh(['seguimiento'])),
                    'fila_csv' => $fila,
                    'meses_programados' => collect($mesesProg)->map(fn ($mes) => (int) $mes)->values()->all(),
                ], $request);

                $creadas++;
            }

            $this->registrarProgramaLog($cartaGantt, 'importacion_actividades', 'Importacion de actividades CSV.', [
                'archivo' => $request->file('archivo')?->getClientOriginalName(),
                'filas_leidas' => count($rows),
                'actividades_creadas' => $creadas,
                'advertencias' => array_slice($errores, 0, 10),
            ], $request);

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

    private function parseCsvDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                $errors = Carbon::getLastErrors();
                $hasErrors = is_array($errors)
                    && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

                if (!$hasErrors && $date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable $e) {
                // Try the next accepted format.
            }
        }

        throw new \InvalidArgumentException("fecha inválida '{$value}'. Use AAAA-MM-DD o DD/MM/AAAA.");
    }

    // =====================================================
    // PREVIEW EMAIL TEMPLATE
    // =====================================================

    public function previewEmail(string $tipo)
    {
        $validTypes = ['asignacion', 'vencimiento', 'vencida', 'recordatorio', 'seguimiento_pendiente'];
        if (!in_array($tipo, $validTypes)) {
            abort(404);
        }

        // Usar una actividad real o crear datos de ejemplo
        $actividad = SstActividad::with(['responsableUser', 'categoria.programa.responsable'])->first();

        if (!$actividad) {
            return response('<p>No hay actividades para previsualizar. Crea al menos una actividad primero.</p>', 200);
        }

        return new SstActividadAlertaMail($actividad, $tipo);
    }
}
