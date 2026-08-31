<?php

namespace App\Console\Commands;

use App\Mail\TalanaAsistenciaReporteMail;
use App\Models\TalanaAusencia;
use App\Models\TalanaContrato;
use App\Models\TalanaMarca;
use App\Services\TalanaService;
use App\Support\TalanaMarcaDirection;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Genera y envía el reporte diario de asistencia Talana por email.
 *
 * Uso:
 *   php artisan talana:reporte-asistencia                         (ayer, envía email)
 *   php artisan talana:reporte-asistencia --fecha=2026-05-28      (fecha específica)
 *   php artisan talana:reporte-asistencia --dry-run               (muestra resumen, no envía)
 *   php artisan talana:reporte-asistencia --email=otro@saep.cl    (destinatario override)
 *   php artisan talana:reporte-asistencia --dias-nuevo=60         (umbral días para "nuevo")
 */
class TalanaReporteAsistencia extends Command
{
    protected $signature = 'talana:reporte-asistencia
                            {--fecha=           : Fecha YYYY-MM-DD a analizar (default: ayer)}
                            {--email=           : Email destinatario (sobrescribe el destinatario de asistencia y omite sus copias)}
                            {--centro-costo=    : Centro de costo a reportar (sobrescribe TALANA_ASISTENCIA_CENTRO_COSTO)}
                            {--empresa-id=      : Empresa Talana a reportar (sobrescribe TALANA_ASISTENCIA_EMPRESA_ID)}
                            {--dias-nuevo=60    : Días de antigüedad máxima para marcar como "nuevo"}
                            {--jornada-normal=9 : Horas de jornada normal para calcular exceso (default: 9)}
                            {--horas-extras-max=7 : Horas extras sobre la jornada normal que disparan revisión (default: 7)}
                            {--dry-run          : Muestra resumen por consola sin enviar email}';

    protected $description = 'Genera el reporte diario de asistencia Talana y lo envía por email';

    public function __construct(private readonly TalanaService $talana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha = $this->option('fecha') ?: Carbon::yesterday('America/Santiago')->toDateString();
        $fechaAnalisis = Carbon::parse($fecha, 'America/Santiago')->startOfDay();
        $isDry = $this->option('dry-run');
        $diasNuevo = (int) ($this->option('dias-nuevo') ?? 60);
        $emailSobrescrito = $this->option('email');
        $email = $emailSobrescrito
            ?: config('talana_attendance.recipients.to')
            ?: config('services.talana.alerta_email');
        $email = is_string($email) ? trim($email) : '';
        $copias = $emailSobrescrito ? [] : $this->destinatariosEnCopia($email);
        $centroCosto = $this->resolverCentroCosto();
        $empresaId = $this->resolverEmpresaId();
        $alcanceReporte = $this->descripcionAlcance($centroCosto, $empresaId);

        $jornadaNormal = (float) ($this->option('jornada-normal') ?? 9);
        $horasExtrasMax = (float) ($this->option('horas-extras-max') ?? 7);
        $umbralAltoH = $jornadaNormal + $horasExtrasMax; // ej. 9+7 = 16h → sospechoso
        $umbralBajoH = 7.0;                             // < 7h trabajadas → sospechoso

        if (! $email) {
            $this->error('No hay email configurado. Usa --email= o define TALANA_ASISTENCIA_EMAIL en .env');

            return self::FAILURE;
        }

        $this->info('═══════════════════════════════════════════');
        $this->info("  Talana — Reporte Asistencia: {$fecha}");
        $this->info('═══════════════════════════════════════════');

        // ─── 1. Obtener las marcas necesarias desde la API ────────────────────
        // El día siguiente permite cerrar turnos nocturnos. El historial se toma
        // desde la base local para no convertir el reporte diario en una consulta
        // de varios días contra la API de Talana.
        $this->line('');
        $this->line('📡 Obteniendo marcas de asistencia (día y cierre nocturno)...');

        $desdeMarcas = $fechaAnalisis->toDateString();
        $hastaMarcas = $fechaAnalisis->copy()->addDay()->toDateString();

        try {
            $marcasRaw = $this->obtenerMarcasPorEmpresas($desdeMarcas, $hastaMarcas);
        } catch (\Throwable $e) {
            $this->error("Error al obtener marcas: {$e->getMessage()}");
            Log::error('TalanaReporteAsistencia: error marcas API', ['error' => $e->getMessage(), 'fecha' => $fecha]);

            return self::FAILURE;
        }

        $this->line("   ✓ {$this->cnt($marcasRaw)} marcas recibidas ({$desdeMarcas} a {$hastaMarcas})");

        // ─── 2. Agrupar marcas por persona e historial local ─────────────────
        [$marcasPorPersona, $personasConMarcasConsultadas] = $this->agruparMarcas($marcasRaw, $fechaAnalisis);
        $personasConHistorial = $this->personasConHistorialLocal(
            $fechaAnalisis->copy()->subDays(7),
            $fechaAnalisis->copy()->subDay()
        ) + $personasConMarcasConsultadas;

        // ─── 3. Cargar trabajadores activos desde DB local ────────────────────
        // (sincronizados en el talana:sync-db diario de las 06:00)
        $this->line('');
        $this->line('🗄️  Cargando trabajadores activos desde DB local...');

        $consultaContratos = TalanaContrato::query()
            ->where('finiquitado', false)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('hasta')
                    ->orWhere('hasta', '>=', $fecha);
            })
            ->where('desde', '<=', $fecha);

        if ($centroCosto) {
            $consultaContratos->whereRaw(
                'LOWER(TRIM(centro_costo_nombre)) = ?',
                [Str::lower($centroCosto)]
            );
        }
        if ($empresaId) {
            $consultaContratos->where('empresa_id', $empresaId);
        }

        $contratosActivos = $consultaContratos
            ->get(['talana_id', 'persona_talana_id', 'persona_nombre', 'persona_rut',
                'centro_costo_nombre', 'sucursal_nombre', 'tipo_contrato_nombre',
                'cargo_nombre', 'desde', 'hasta', 'empresa_id', 'empresa_nombre']);

        $alcance = $alcanceReporte ? " en {$alcanceReporte}" : '';
        $this->line("   ✓ {$this->cnt($contratosActivos->toArray())} contratos activos{$alcance} para {$fecha}");

        if ($alcanceReporte && $contratosActivos->isEmpty()) {
            $this->error("No hay contratos activos para el alcance configurado: {$alcanceReporte}");

            return self::FAILURE;
        }

        // ─── 4. Obtener jornada calculada del día directamente desde la API Talana ──
        // Se consulta en tiempo real (no depende de talana:sync-turnos) para garantizar
        // que los datos de ayer estén disponibles cuando se genera el reporte a las 08:15.
        // Permite distinguir días de descanso (workingDay=false) y detectar horas anómalas.
        $this->line('');
        $this->line('📡 Obteniendo jornada calculada (assignationSummary) de la API...');

        $jornadaPorPersona = []; // pid → bool (true=día laboral, false=día de descanso)
        $horasAsignacion = []; // pid → working_seconds (int)

        try {
            $empresas = config('services.talana.empresas', []);
            $assignSummaries = [];
            if (empty($empresas)) {
                $assignSummaries = $this->talana->assignationSummary($fecha, $fecha, 120);
            } else {
                foreach ($empresas as $empresaId => $empresaNombre) {
                    $lote = $this->talana->assignationSummary($fecha, $fecha, 120, (int) $empresaId);
                    $this->line("   {$empresaNombre}: {$this->cnt($lote)} jornadas");
                    $assignSummaries = array_merge($assignSummaries, $lote);
                }
            }

            if (empty($assignSummaries)) {
                $this->warn('   ⚠ Sin datos de jornada en API para '.$fecha.'. El filtro de días de descanso y horas no estará disponible.');
            } else {
                foreach ($assignSummaries as $rec) {
                    $person = $rec['person'] ?? [];
                    $personId = is_array($person) ? ($person['id'] ?? null) : $person;
                    if (! $personId) {
                        continue;
                    }
                    $jornadaPorPersona[$personId] = (bool) ($rec['workingDay'] ?? true);
                    if (isset($rec['workingSeconds']) && $rec['workingSeconds'] !== null) {
                        $horasAsignacion[$personId] = (int) $rec['workingSeconds'];
                    }
                }
                $totalDescansos = count(array_filter($jornadaPorPersona, fn ($v) => $v === false));
                $this->line('   ✓ '.count($assignSummaries)." jornadas cargadas ({$totalDescansos} días de descanso)");
            }
        } catch (\Throwable $e) {
            $this->warn('   ⚠ No se pudo cargar jornada calculada: '.$e->getMessage());
        }

        // Las ausencias sólo se ocupan cuando la sincronización local está vigente.
        [$ausenciasPorRut, $ausenciasVigentes] = $this->ausenciasActivasPorRut($fechaAnalisis);
        if (! $ausenciasVigentes) {
            $this->warn('   ⚠ Ausencias no actualizadas: no se usarán para clasificar inasistencias.');
        }

        // ─── 5. Analizar cada trabajador activo ───────────────────────────
        $resultado = $this->analizarTrabajadores(
            $contratosActivos,
            $marcasPorPersona,
            $jornadaPorPersona,
            $personasConHistorial,
            $ausenciasPorRut,
            $fechaAnalisis,
            $diasNuevo,
            $horasAsignacion,
            $umbralAltoH,
            $umbralBajoH,
            $ausenciasVigentes
        );
        $resultado['centro_costo'] = $centroCosto;
        $resultado['empresa_id'] = $empresaId;
        $resultado['alcance'] = $alcanceReporte;

        // ─── 6. Mostrar resumen por consola ───────────────────────────────────
        $this->mostrarResumen($resultado, $fecha);

        // ─── 7. Enviar email (salvo dry-run) ─────────────────────────────────
        if ($isDry) {
            $this->line('');
            $this->warn('[DRY-RUN] Email no enviado');

            return self::SUCCESS;
        }

        if ($resultado['total_alertas'] === 0) {
            $this->line('');
            $this->info('✅ Sin anomalías — email informativo igualmente enviado');
        }

        try {
            $correo = Mail::to($email);
            if ($copias !== []) {
                $correo->cc($copias);
            }
            $correo->send(new TalanaAsistenciaReporteMail($resultado, $fecha));
            $detalleCopias = $copias !== [] ? ' | CC: '.implode(', ', $copias) : '';
            $this->info("📧 Email enviado a {$email}{$detalleCopias}");
        } catch (\Throwable $e) {
            $this->error("Error al enviar email: {$e->getMessage()}");
            Log::error('TalanaReporteAsistencia: error email', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Normaliza las copias configuradas, descartando direcciones inválidas,
     * duplicadas o iguales al destinatario principal.
     *
     * @return array<int, string>
     */
    private function destinatariosEnCopia(string $destinatario): array
    {
        $configurados = config('talana_attendance.recipients.cc', '');
        $correos = is_array($configurados)
            ? $configurados
            : (preg_split('/[;,\r\n]+/', (string) $configurados) ?: []);

        $resultado = [];
        foreach ($correos as $correo) {
            $correo = trim((string) $correo);
            if (! filter_var($correo, FILTER_VALIDATE_EMAIL)
                || strcasecmp($correo, $destinatario) === 0) {
                continue;
            }

            $clave = Str::lower($correo);
            $resultado[$clave] = $correo;
        }

        return array_values($resultado);
    }

    private function resolverCentroCosto(): ?string
    {
        $centroCosto = $this->option('centro-costo')
            ?: config('services.talana.asistencia_centro_costo');

        if (! is_string($centroCosto)) {
            return null;
        }

        $centroCosto = preg_replace('/\s+/', ' ', trim($centroCosto));

        return $centroCosto !== '' ? $centroCosto : null;
    }

    private function resolverEmpresaId(): ?int
    {
        $empresaId = $this->option('empresa-id')
            ?: config('services.talana.asistencia_empresa_id');

        return is_numeric($empresaId) && (int) $empresaId > 0
            ? (int) $empresaId
            : null;
    }

    private function descripcionAlcance(?string $centroCosto, ?int $empresaId): ?string
    {
        $partes = [];
        if ($centroCosto) {
            $partes[] = $centroCosto;
        }
        if ($empresaId) {
            $empresas = config('services.talana.empresas', []);
            $partes[] = $empresas[$empresaId] ?? "Empresa Talana {$empresaId}";
        }

        return $partes ? implode(' · ', $partes) : null;
    }

    private function obtenerMarcasPorEmpresas(string $desde, string $hasta): array
    {
        $empresas = config('services.talana.empresas', []);
        if (empty($empresas)) {
            return $this->talana->marcasAsistencia($desde, $hasta, 120);
        }

        $marcas = [];
        foreach ($empresas as $empresaId => $empresaNombre) {
            $lote = $this->talana->marcasAsistencia($desde, $hasta, 120, (int) $empresaId);
            $this->line("   {$empresaNombre}: {$this->cnt($lote)} marcas");
            $marcas = array_merge($marcas, $lote);
        }

        return $marcas;
    }

    /**
     * La sincronización diaria persiste marcas para ambos empleadores. Usarla
     * como historial evita reconsultar ocho días de API sólo para distinguir
     * contratos recientes sin marcas previas.
     */
    private function personasConHistorialLocal(Carbon $desde, Carbon $hasta): array
    {
        return TalanaMarca::query()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->pluck('persona_talana_id')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($personaId) => [(int) $personaId => true])
            ->all();
    }

    /**
     * Las ausencias sólo se usan como evidencia si el último sync tiene menos
     * de 36 horas. Un dato vencido es menos confiable que no clasificar.
     */
    private function ausenciasActivasPorRut(Carbon $fecha): array
    {
        $ultimoSync = TalanaAusencia::max('synced_at');
        if (! $ultimoSync || Carbon::parse($ultimoSync)->lt(now('America/Santiago')->subHours(36))) {
            return [[], false];
        }

        $ausencias = TalanaAusencia::query()
            ->where('aprobada', true)
            ->whereDate('fecha_desde', '<=', $fecha->toDateString())
            ->where(function ($query) use ($fecha) {
                $query->whereNull('fecha_hasta')
                    ->orWhereDate('fecha_hasta', '>=', $fecha->toDateString());
            })
            ->get();

        $porRut = [];
        foreach ($ausencias as $ausencia) {
            $rut = $this->normalizarRut($ausencia->persona_rut);
            if ($rut) {
                $porRut[$rut] = $ausencia;
            }
        }

        return [$porRut, true];
    }

    // ─── Agrupar marcas por persona ───────────────────────────────────────────

    /**
     * Devuelve las marcas del día evaluado y los IDs con historial reciente.
     * Talana usa indistintamente E/Entrada y X/Salida; todas se normalizan
     * antes de cualquier cálculo.
     */
    private function agruparMarcas(array $marcasRaw, Carbon $fecha): array
    {
        $eventosPorPersona = [];
        $hastaHistorial = $fecha->copy()->endOfDay();
        $inicioSiguiente = $fecha->copy()->addDay()->startOfDay();
        $limiteSalidaNocturna = $inicioSiguiente->copy()->addHours(12);

        foreach ($marcasRaw as $m) {
            $personId = $m['person']['id'] ?? ($m['personId'] ?? null);
            if (! $personId) {
                continue;
            }

            try {
                $ts = Carbon::parse($m['TS'] ?? $m['markedAt'] ?? null)->setTimezone('America/Santiago');
            } catch (\Throwable) {
                continue;
            }

            $eventosPorPersona[$personId][] = [
                'ts' => $ts,
                'ts_str' => $ts->format('H:i:s'),
                'direction' => TalanaMarcaDirection::normalize($m['direction'] ?? $m['tipo'] ?? null),
                'dia_siguiente' => false,
            ];
        }

        $marcasPorPersona = [];
        $personasConHistorial = [];

        foreach ($eventosPorPersona as $pid => $eventos) {
            usort($eventos, fn ($a, $b) => $a['ts']->getTimestamp() <=> $b['ts']->getTimestamp());

            $marcasDelDia = array_values(array_filter(
                $eventos,
                fn ($marca) => $marca['ts']->isSameDay($fecha)
            ));

            foreach ($eventos as $marca) {
                if ($marca['ts']->lte($hastaHistorial)) {
                    $personasConHistorial[$pid] = true;
                    break;
                }
            }

            // Una entrada nocturna se completa con la primera salida de la mañana siguiente.
            if ($this->esEntradaNocturnaSinSalida($marcasDelDia)) {
                foreach ($eventos as $marca) {
                    if ($marca['ts']->betweenIncluded($inicioSiguiente, $limiteSalidaNocturna)
                        && $marca['direction'] === 'S') {
                        $marca['dia_siguiente'] = true;
                        $marcasDelDia[] = $marca;
                        break;
                    }
                }
            }

            if (! empty($marcasDelDia)) {
                $marcasPorPersona[$pid] = [
                    'marcas' => $marcasDelDia,
                    'categoria' => $this->categorizarMarcas($marcasDelDia),
                ];
            }
        }

        return [$marcasPorPersona, $personasConHistorial];
    }

    /**
     * Categoriza las marcas de un trabajador en un día:
     * - completo       → tiene al menos 1 entrada y 1 salida
     * - solo_entrada   → solo tiene E (no salió)
     * - solo_salida    → solo tiene S (entró pero no registró entrada)
     * - multiple       → más de 2 marcas (puede ser turno partido)
     * - sin_datos      → 0 marcas (no debería llegar aquí)
     */
    private function categorizarMarcas(array $marcas): string
    {
        if (empty($marcas)) {
            return 'sin_datos';
        }

        $dirs = array_column($marcas, 'direction');
        $tieneE = in_array('E', $dirs, true);
        $tieneS = in_array('S', $dirs, true);

        if ($tieneE && $tieneS) {
            return count($marcas) > 2 ? 'multiple' : 'completo';
        }

        if ($tieneE && ! $tieneS) {
            return 'solo_entrada';
        }

        if (! $tieneE && $tieneS) {
            return 'solo_salida';
        }

        return 'incompleto'; // 2 E o 2 S
    }

    private function esEntradaNocturnaSinSalida(array $marcas): bool
    {
        if ($this->categorizarMarcas($marcas) !== 'solo_entrada') {
            return false;
        }

        $ultima = end($marcas);

        return $ultima !== false
            && $ultima['direction'] === 'E'
            && (int) $ultima['ts']->format('H') >= 18;
    }

    // ─── Analizar trabajadores activos ────────────────────────────────────────

    private function analizarTrabajadores(
        $contratosActivos,
        array $marcasPorPersona,
        array $jornadaPorPersona,
        array $personasConHistorial,
        array $ausenciasPorRut,
        Carbon $fecha,
        int $diasNuevo,
        array $horasAsignacion = [],
        float $umbralAltoH = 16.0,
        float $umbralBajoH = 7.0,
        bool $ausenciasVigentes = false,
    ): array {
        $completos = [];
        $incompletas = [];
        $sinMarcacion = [];
        $sinHistorial = [];
        $descanso = [];
        $ausencias = [];
        $sinEvaluacion = [];
        $revision = [];
        $jornadasCubiertas = 0;

        foreach ($contratosActivos as $contrato) {
            $pid = $contrato->persona_talana_id;
            $tieneJornada = array_key_exists($pid, $jornadaPorPersona);
            if ($tieneJornada) {
                $jornadasCubiertas++;
            }

            if (isset($marcasPorPersona[$pid])) {
                $data = $marcasPorPersona[$pid];
                $fila = $this->buildFilaTrabajador($contrato, $data['marcas'], $data['categoria']);

                // Si marcó pero su jornada es día de descanso → requiere revisión
                $esDescansoConMarca = isset($jornadaPorPersona[$pid]) && $jornadaPorPersona[$pid] === false;

                if ($esDescansoConMarca) {
                    $revision[] = array_merge($fila, [
                        'categoria' => 'revision',
                        'motivo' => 'Marcó en día de descanso',
                    ]);
                } else {
                    switch ($data['categoria']) {
                        case 'completo':
                        case 'multiple':
                            // Sólo se comparan horas cuando Talana informa una
                            // duración oficial de jornada. Dos marcas completas no
                            // prueban por sí solas que el turno debía durar 7 horas.
                            $horas = array_key_exists($pid, $horasAsignacion)
                                ? $this->resolverHorasTrabajadas($pid, $horasAsignacion, $fila)
                                : null;
                            if ($horas !== null && $horas > $umbralAltoH) {
                                $revision[] = array_merge($fila, [
                                    'categoria' => 'revision',
                                    'motivo' => sprintf(
                                        'Horas excesivas: %.1fh trabajadas (máx. %.0fh por día)',
                                        $horas, $umbralAltoH
                                    ),
                                    'horas_trabajadas' => $horas,
                                ]);
                            } elseif ($horas !== null && $horas < $umbralBajoH) {
                                $revision[] = array_merge($fila, [
                                    'categoria' => 'revision',
                                    'motivo' => sprintf(
                                        'Horas insuficientes: %.1fh trabajadas (mín. %.0fh esperado)',
                                        $horas, $umbralBajoH
                                    ),
                                    'horas_trabajadas' => $horas,
                                ]);
                            } else {
                                $completos[] = $fila;
                            }
                            break;
                        default:
                            $incompletas[] = $fila;
                            break;
                    }
                }
            } else {
                // Sólo se alerta si Talana confirma jornada laboral. Sin jornada
                // confirmada, no es correcto inferir una ausencia.
                $esDescanso = $tieneJornada && $jornadaPorPersona[$pid] === false;
                $ausencia = $ausenciasVigentes
                    ? $this->ausenciaParaContrato($contrato, $ausenciasPorRut)
                    : null;

                if ($esDescanso) {
                    $descanso[] = $this->buildFilaTrabajador($contrato, [], 'descanso');
                } elseif ($ausencia) {
                    $ausencias[] = array_merge(
                        $this->buildFilaTrabajador($contrato, [], 'ausencia'),
                        ['motivo' => 'Ausencia aprobada: '.($ausencia->tipo_ausencia ?: 'Sin detalle')]
                    );
                } elseif ($tieneJornada && $jornadaPorPersona[$pid] === true) {
                    $sinMarcacion[] = array_merge(
                        $this->buildFilaTrabajador($contrato, [], 'sin_marcacion'),
                        ['motivo' => 'Jornada laboral confirmada por Talana, sin marca registrada']
                    );
                } else {
                    $fila = $this->buildFilaTrabajador($contrato, [], 'sin_evaluacion');
                    $esRecienteSinHistorial = $this->esContratoRecienteSinHistorial(
                        $contrato,
                        $fecha,
                        $diasNuevo,
                        $personasConHistorial
                    );

                    if ($esRecienteSinHistorial) {
                        $sinHistorial[] = array_merge($fila, [
                            'categoria' => 'sin_historial',
                            'motivo' => 'Contrato reciente sin marcas en los últimos 7 días',
                        ]);
                    } else {
                        $sinEvaluacion[] = array_merge($fila, [
                            'motivo' => 'Sin marca y sin jornada/ausencia confirmada por Talana',
                        ]);
                    }
                }
            }
        }

        // Ordenar por nombre
        $sortNombre = fn ($a, $b) => strcmp($a['nombre'], $b['nombre']);
        usort($completos, $sortNombre);
        usort($incompletas, $sortNombre);
        usort($sinMarcacion, $sortNombre);
        usort($sinHistorial, $sortNombre);
        usort($descanso, $sortNombre);
        usort($ausencias, $sortNombre);
        usort($sinEvaluacion, $sortNombre);
        usort($revision, $sortNombre);

        $porFranjaTurno = $this->resumirPorFranjaTurno([
            'completos' => $completos,
            'incompletas' => $incompletas,
            'sin_marcacion' => $sinMarcacion,
            'sin_historial' => $sinHistorial,
            'descanso' => $descanso,
            'ausencias' => $ausencias,
            'sin_evaluacion' => $sinEvaluacion,
            'revision' => $revision,
        ]);

        return [
            'fecha' => $fecha->toDateString(),
            'total_activos' => count($contratosActivos),
            'total_completos' => count($completos),
            'total_incompletas' => count($incompletas),
            'total_sin_marcacion' => count($sinMarcacion),
            'total_sin_historial' => count($sinHistorial),
            'total_descanso' => count($descanso),
            'total_ausencias' => count($ausencias),
            'total_sin_evaluacion' => count($sinEvaluacion),
            'total_revision' => count($revision),
            'total_alertas' => count($incompletas) + count($sinMarcacion) + count($revision),
            'total_jornadas_cubiertas' => $jornadasCubiertas,
            'ausencias_vigentes' => $ausenciasVigentes,
            'completos' => $completos,
            'incompletas' => $incompletas,
            'sin_marcacion' => $sinMarcacion,
            'sin_historial' => $sinHistorial,
            'descanso' => $descanso,
            'ausencias' => $ausencias,
            'sin_evaluacion' => $sinEvaluacion,
            'revision' => $revision,
            'por_franja_turno' => $porFranjaTurno,
        ];
    }

    /**
     * Agrupa el resultado por la primera entrada registrada. No representa el
     * turno contractual, porque el endpoint de Talana disponible para este
     * reporte no expone el nombre del turno asignado a cada persona.
     */
    private function resumirPorFranjaTurno(array $grupos): array
    {
        $orden = [
            'Mañana (06:00–13:59)',
            'Tarde (14:00–21:59)',
            'Noche (22:00–05:59)',
            'Sin entrada registrada',
            'Sin marcas registradas',
        ];

        $resultado = [];
        foreach ($orden as $franja) {
            $resultado[$franja] = [
                'franja' => $franja,
                'activos' => 0,
                'completos' => 0,
                'incompletas' => 0,
                'sin_marcacion' => 0,
                'revision' => 0,
                'alertas' => 0,
            ];
        }

        foreach ($grupos as $categoria => $filas) {
            foreach ($filas as $fila) {
                $franja = $fila['franja_turno'] ?? 'Sin entrada registrada';
                if (! isset($resultado[$franja])) {
                    $resultado[$franja] = [
                        'franja' => $franja,
                        'activos' => 0,
                        'completos' => 0,
                        'incompletas' => 0,
                        'sin_marcacion' => 0,
                        'revision' => 0,
                        'alertas' => 0,
                    ];
                }

                $resultado[$franja]['activos']++;
                if (array_key_exists($categoria, $resultado[$franja])) {
                    $resultado[$franja][$categoria]++;
                }
                if (in_array($categoria, ['incompletas', 'sin_marcacion', 'revision'], true)) {
                    $resultado[$franja]['alertas']++;
                }
            }
        }

        return array_values(array_filter(
            $resultado,
            fn (array $fila) => $fila['activos'] > 0
        ));
    }

    /**
     * Resuelve las horas trabajadas para una persona en el día.
     * Prioridad:
     *  1. working_seconds desde talana_assignation_summaries (dato oficial de Talana)
     *  2. Diferencia entre primera_entrada y ultima_salida (desde las marcas)
     * Devuelve null si no se puede calcular.
     */
    private function resolverHorasTrabajadas(int $pid, array $horasAsignacion, array $fila): ?float
    {
        // Prioridad 1: dato oficial calculado por Talana
        if (isset($horasAsignacion[$pid]) && $horasAsignacion[$pid] > 0) {
            return round($horasAsignacion[$pid] / 3600, 2);
        }

        // Prioridad 2: calcular desde primera_entrada / ultima_salida
        if ($fila['primera_entrada'] && $fila['ultima_salida']) {
            try {
                $tsE = Carbon::createFromFormat('H:i:s', $fila['primera_entrada']);
                $tsS = Carbon::createFromFormat('H:i:s', $fila['ultima_salida']);
                if ($tsS->lte($tsE)) {
                    $tsS->addDay(); // turno nocturno que cruza medianoche
                }

                return round($tsE->diffInSeconds($tsS, true) / 3600, 2);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private function esContratoRecienteSinHistorial($contrato, Carbon $fecha, int $diasNuevo, array $personasConHistorial): bool
    {
        if (! $contrato->desde) {
            return false;
        }

        $inicio = Carbon::parse($contrato->desde);
        $diasDesdeInicio = $inicio->diffInDays($fecha);

        if ($diasDesdeInicio > $diasNuevo) {
            return false;
        }

        return ! isset($personasConHistorial[$contrato->persona_talana_id]);
    }

    private function ausenciaParaContrato($contrato, array $ausenciasPorRut): ?TalanaAusencia
    {
        $rut = $this->normalizarRut($contrato->persona_rut ?? null);

        return $rut ? ($ausenciasPorRut[$rut] ?? null) : null;
    }

    private function normalizarRut(?string $rut): ?string
    {
        $rut = strtoupper((string) $rut);
        $rut = preg_replace('/[^0-9K]/', '', $rut);

        return $rut !== '' ? $rut : null;
    }

    private function buildFilaTrabajador($contrato, array $marcas, string $categoria): array
    {
        $primeraEntrada = null;
        $ultimaSalida = null;
        $marcasStr = [];

        foreach ($marcas as $m) {
            $etiqueta = TalanaMarcaDirection::label($m['direction']);
            if ($m['dia_siguiente'] ?? false) {
                $etiqueta .= ' día siguiente';
            }
            $marcasStr[] = "{$m['ts_str']} ({$etiqueta})";
            if ($m['direction'] === 'E' && ! $primeraEntrada) {
                $primeraEntrada = $m['ts_str'];
            }
            if ($m['direction'] === 'S') {
                $ultimaSalida = $m['ts_str'];
            }
        }

        $franjaTurno = $this->resolverFranjaTurno($primeraEntrada, $categoria);

        return [
            'nombre' => $contrato->persona_nombre ?? '—',
            'rut' => $contrato->persona_rut ?? '—',
            'centro_costo' => $contrato->centro_costo_nombre ?? $contrato->sucursal_nombre ?? '—',
            'cargo' => $contrato->cargo_nombre ?? '—',
            'tipo_contrato' => $contrato->tipo_contrato_nombre ?? '—',
            'empresa_id' => $contrato->empresa_id ?? null,
            'empresa' => $contrato->empresa_nombre ?? 'Sin empresa',
            'desde' => $contrato->desde ?? null,
            'hasta' => $contrato->hasta ?? null,
            'primera_entrada' => $primeraEntrada,
            'ultima_salida' => $ultimaSalida,
            'franja_turno' => $franjaTurno,
            'marcas' => implode(' / ', $marcasStr),
            'total_marcas' => count($marcas),
            'categoria' => $categoria,
            'horas_trabajadas' => null, // se rellena opcionalmente vía resolverHorasTrabajadas
            'motivo' => null, // se rellena opcionalmente para revisión
        ];
    }

    private function resolverFranjaTurno(?string $primeraEntrada, string $categoria): string
    {
        if (! $primeraEntrada) {
            return $categoria === 'sin_marcacion'
                ? 'Sin marcas registradas'
                : 'Sin entrada registrada';
        }

        try {
            $hora = (int) Carbon::createFromFormat('H:i:s', $primeraEntrada)->format('H');
        } catch (\Throwable) {
            return 'Sin entrada registrada';
        }

        if ($hora >= 6 && $hora < 14) {
            return 'Mañana (06:00–13:59)';
        }

        if ($hora >= 14 && $hora < 22) {
            return 'Tarde (14:00–21:59)';
        }

        return 'Noche (22:00–05:59)';
    }

    // ─── Consola ──────────────────────────────────────────────────────────────

    private function mostrarResumen(array $r, string $fecha): void
    {
        $this->line('');
        $this->info('┌─────────────────────────────────────────┐');
        $this->info("│   Resumen Asistencia — {$fecha}   │");
        $this->info('└─────────────────────────────────────────┘');
        $this->line("  Trabajadores activos:          {$r['total_activos']}");
        $this->line("  ✅ Con marcación completa:     {$r['total_completos']}");
        $this->line("  ⚠️  Marcación incompleta:       {$r['total_incompletas']}");
        $this->line("  ❌ Sin marca con jornada:       {$r['total_sin_marcacion']}");
        $this->line("  🆕 Reciente sin historial:      {$r['total_sin_historial']}");
        $this->line("  😴 Día de descanso (turno):    {$r['total_descanso']}");
        $this->line("  📋 Ausencias aprobadas:         {$r['total_ausencias']}");
        $this->line("  ◌ Sin evaluación de jornada:   {$r['total_sin_evaluacion']}");
        $this->line("  🔍 Revisión (anomalías):        {$r['total_revision']}");
        $this->line("  📊 Jornadas confirmadas:        {$r['total_jornadas_cubiertas']}");

        // Breakdown por empresa
        $empBreakdown = [];
        $grupos = ['completos', 'incompletas', 'sin_marcacion', 'sin_historial', 'descanso', 'ausencias', 'sin_evaluacion', 'revision'];
        foreach ($grupos as $g) {
            foreach ($r[$g] as $t) {
                $emp = $t['empresa'] ?? 'Sin empresa';
                if (! isset($empBreakdown[$emp])) {
                    $empBreakdown[$emp] = array_fill_keys($grupos, 0);
                }
                $empBreakdown[$emp][$g]++;
            }
        }
        if (count($empBreakdown) > 0) {
            $this->line('');
            $this->line('  ─── Por empresa ───────────────────────────');
            foreach ($empBreakdown as $emp => $c) {
                $total = array_sum($c);
                $this->line("  {$emp} ({$total}): ✅ {$c['completos']} | ⚠️ {$c['incompletas']} | ❌ {$c['sin_marcacion']} | ◌ {$c['sin_evaluacion']} | 🔍 {$c['revision']}");
            }
        }

        if (! empty($r['por_franja_turno'] ?? [])) {
            $this->line('');
            $this->line('  ─── Por franja de entrada observada ───────');
            foreach ($r['por_franja_turno'] as $franja) {
                $this->line("  {$franja['franja']} ({$franja['activos']}): ✅ {$franja['completos']} | ⚠️ Alertas: {$franja['alertas']}");
            }
            $this->comment('  Basado en la primera entrada registrada; no reemplaza el turno contractual de Talana.');
        }

        if (! empty($r['revision'])) {
            $this->line('');
            $this->warn('  Revisión (requieren atención):');
            foreach ($r['revision'] as $t) {
                $motivo = $t['motivo'] ?? 'Sin motivo';
                $this->line("    - {$t['nombre']} ({$t['rut']}) | {$motivo} | Marcas: {$t['marcas']}");
            }
        }

        if (! empty($r['incompletas'])) {
            $this->line('');
            $this->warn('  Marcación incompleta:');
            foreach ($r['incompletas'] as $t) {
                $this->line("    - {$t['nombre']} ({$t['rut']}) | {$t['marcas']} | {$t['categoria']}");
            }
        }

        if (! empty($r['sin_historial'])) {
            $this->line('');
            $this->warn('  Contratos recientes sin historial de marca:');
            foreach ($r['sin_historial'] as $t) {
                $this->line("    - {$t['nombre']} ({$t['rut']}) | contrato desde: {$t['desde']}");
            }
        }
    }

    private function cnt(array $arr): int
    {
        return count($arr);
    }
}
