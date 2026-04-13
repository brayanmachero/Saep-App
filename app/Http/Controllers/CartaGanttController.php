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
            'categorias.actividades.reprogramaciones.usuario',
            'centroCosto', 'responsable', 'creador',
        ]);
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.show', compact('cartaGantt', 'usuarios'));
    }

    public function exportPdf(ProgramaSst $cartaGantt)
    {
        $cartaGantt->load([
            'categorias.actividades.seguimiento',
            'categorias.actividades.responsableUser',
            'categorias.actividades.planesAccion',
            'categorias.actividades.reprogramaciones.usuario',
            'centroCosto', 'responsable', 'creador',
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
                foreach ($act->seguimiento as $seg) {
                    if ($seg->mes === $m && $seg->programado) {
                        $prog += $act->cantidad_programada;
                        $real += $seg->cantidad_realizada;
                    }
                }
            }
            $mesesData[$m] = ['prog' => $prog, 'real' => $real, 'pct' => $prog > 0 ? round(($real / $prog) * 100) : 0];
        }

        // Activities with issues
        $vencidas   = $todasActividades->filter(fn ($a) => $a->estaVencida)->values();
        $porVencer  = $todasActividades->filter(fn ($a) => $a->estaPorVencer)->values();

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
            'mesesData', 'vencidas', 'porVencer', 'reprogramaciones', 'prioridades'
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
        $request->validate([
            'nombre' => 'required|string|max:200',
            'orden'  => 'nullable|integer|min:1',
        ]);
        $cartaGantt->categorias()->create([
            'nombre' => $request->nombre,
            'orden'  => $request->orden ?? ($cartaGantt->categorias()->max('orden') + 1),
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

        return back()->with('success', 'Actividad agregada.');
    }

    public function updateActividad(Request $request, SstActividad $actividad)
    {
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
            'observacion' => 'nullable|string|max:1000',
        ]);

        $cantProg = max(1, (int) $actividad->cantidad_programada);
        $seg = $actividad->seguimiento()->where('mes', $request->mes)->first();

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

        $actividad->seguimiento()->updateOrCreate(
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
    // REPROGRAMACIÓN
    // =====================================================

    public function reprogramarActividad(Request $request, SstActividad $actividad)
    {
        $mesActual = (int) date('n');

        $request->validate([
            'mes_original' => 'required|integer|min:1|max:12',
            'mes_nuevo'    => 'required|integer|min:' . $mesActual . '|max:12|different:mes_original',
            'motivo'       => 'required|string|max:500',
        ]);

        $mesOrig = (int) $request->mes_original;
        $mesNuevo = (int) $request->mes_nuevo;

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
                'realizado'           => false,
                'cantidad_realizada'  => 0,
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

        $meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        return back()->with('success', "Actividad reprogramada de {$meses[$mesOrig]} a {$meses[$mesNuevo]}.");
    }

    // =====================================================
    // HELPERS
    // =====================================================

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
