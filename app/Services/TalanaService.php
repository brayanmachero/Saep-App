<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente de solo lectura para la API REST de Talana (Producción).
 * IMPORTANTE: Esta clase solo realiza solicitudes GET — no modifica datos en Talana.
 */
class TalanaService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.talana.base_url', 'https://talana.com/es/api'), '/');
        $this->token   = (string) config('services.talana.token', '');
    }

    // ─── HTTP ─────────────────────────────────────────────────────────────────

    private function get(string $endpoint, array $query = [], int $timeout = 30): array
    {
        $url     = "{$this->baseUrl}/{$endpoint}";
        $headers = [
            'Authorization' => "Token {$this->token}",
            'Accept'        => 'application/json',
        ];

        $maxRetries = 5;
        $delay      = 2; // segundos iniciales

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->get($url, $query);

            if ($response->status() === 429) {
                if ($attempt === $maxRetries) {
                    throw new \RuntimeException(
                        "Talana API error [429] {$endpoint}: demasiadas solicitudes tras {$maxRetries} intentos"
                    );
                }
                $retryAfter = min((int) ($response->header('Retry-After') ?: $delay), 30);
                sleep($retryAfter);
                $delay = min($delay * 2, 60);
                continue;
            }

            if ($response->failed()) {
                throw new \RuntimeException(
                    "Talana API error [{$response->status()}] {$endpoint}: {$response->body()}"
                );
            }

            return $response->json() ?? [];
        }

        return []; // nunca se alcanza
    }

    // ─── Paginación ───────────────────────────────────────────────────────────

    /**
     * Recorre todas las páginas de un endpoint paginado y devuelve todos los registros.
     *
     * @param string $endpoint  Ej: 'contrato-paginado/'
     * @param array  $query     Parámetros adicionales (distintos de page / page_size)
     * @param int    $pageSize  Registros por página (máx recomendado: 200)
     * @param int    $timeout   Timeout por solicitud en segundos
     */
    public function fetchAll(
        string $endpoint,
        array $query = [],
        int $pageSize = 200,
        int $timeout = 30,
        int $startPage = 1,
        int $pageDelayMs = 0
    ): array {
        $all  = [];
        $page = $startPage;

        do {
            $data    = $this->get($endpoint, array_merge($query, ['page' => $page, 'page_size' => $pageSize]), $timeout);
            $results = $data['results'] ?? [];
            $all     = array_merge($all, $results);
            $hasNext = ! empty($data['next']);
            $page++;

            if ($hasNext && $pageDelayMs > 0) {
                usleep($pageDelayMs * 1000);
            }
        } while ($hasNext);

        return $all;
    }

    // ─── Endpoints de negocio ─────────────────────────────────────────────────

    /**
     * Obtiene todos los contratos (paginados, solo lectura).
     * Incluye empleadoDetails embebido — no requiere consulta adicional a personas.
     */
    public function contratos(array $query = []): array
    {
        return $this->fetchAll('contrato-paginado/', $query);
    }

    /**
     * Obtiene todos los registros de personas (paginados).
     */
    public function personas(array $query = []): array
    {
        return $this->fetchAll('personas-paginadas/', $query);
    }

    /**
     * Obtiene horas extras del período actual.
     */
    public function horasExtras(array $query = []): array
    {
        $data = $this->get('horas-extras/', $query);
        return is_array($data) ? $data : [];
    }

    /**
     * Obtiene días trabajados.
     */
    public function diasTrabajados(array $query = []): array
    {
        return $this->fetchAll('workedDays', $query);
    }

    /**
     * Devuelve la fecha/hora del servidor Talana (útil para health-check).
     */
    public function fechaServidor(): string
    {
        $data = $this->get('fechaserver/');
        return is_string($data) ? $data : (string) $data;
    }

    // ─── Contratos por vencer (filtro client-side) ────────────────────────────

    /**
     * Retorna contratos vigentes (no finiquitados) cuya fecha `hasta` cae
     * dentro de los próximos $dias días a partir de hoy.
     *
     * Estructura de cada elemento devuelto:
     *   id, cargo, tipoContratoDetails, desde, hasta, diasRestantes,
     *   sucursal{nombre}, centroCosto{nombre}, empleadoDetails{nombre,
     *   apellidoPaterno, apellidoMaterno, rut, email}, jefe{...}
     *
     * @param int $dias Umbral máximo de días restantes (default: 30)
     */
    public function contratosPorVencer(int $dias = 30): array
    {
        $todos  = $this->contratos();
        $hoy    = now()->startOfDay();
        $limite = now()->addDays($dias)->endOfDay();

        $resultado = [];

        foreach ($todos as $contrato) {
            if (empty($contrato['hasta'])) {
                continue; // Indefinido — sin fecha término
            }

            if ($contrato['finiquitado'] === true) {
                continue; // Ya finiquitado
            }

            $hasta = \Carbon\Carbon::parse($contrato['hasta'])->startOfDay();

            if ($hasta->lt($hoy) || $hasta->gt($limite)) {
                continue;
            }

            $contrato['diasRestantes'] = (int) $hoy->diffInDays($hasta);
            $resultado[]               = $contrato;
        }

        // Ordenar por fecha de vencimiento ascendente
        usort($resultado, fn($a, $b) => strcmp($a['hasta'], $b['hasta']));

        return $resultado;
    }

    /**
     * Retorna contratos que ya tienen fecha `hasta` pasada (vencidos)
     * pero el trabajador NO está finiquitado — posible anomalía contractual.
     *
     * @param int $diasAtras Cuántos días hacia atrás revisar (0 = todo el histórico)
     */
    public function contratosVencidosActivos(int $diasAtras = 0): array
    {
        $todos     = $this->contratos();
        $hoy       = now()->startOfDay();
        $limiteMin = $diasAtras > 0 ? now()->subDays($diasAtras)->startOfDay() : null;

        $resultado = [];

        foreach ($todos as $contrato) {
            if (empty($contrato['hasta'])) {
                continue;
            }

            if ($contrato['finiquitado'] === true) {
                continue; // Ya finiquitado formalmente — no es anomalía
            }

            $hasta = \Carbon\Carbon::parse($contrato['hasta'])->startOfDay();

            if ($hasta->gte($hoy)) {
                continue; // Aún vigente — no es vencido
            }

            if ($limiteMin && $hasta->lt($limiteMin)) {
                continue; // Fuera del rango solicitado
            }

            $contrato['diasVencido'] = (int) $hasta->diffInDays($hoy);
            $resultado[]             = $contrato;
        }

        // Ordenar por más vencido primero
        usort($resultado, fn($a, $b) => $b['diasVencido'] - $a['diasVencido']);

        return $resultado;
    }

    // ─── Asistencia ───────────────────────────────────────────────────────────

    /**
     * Obtiene marcas de asistencia (entradas/salidas) para un rango de fechas.
     * Requiere parámetros desde/hasta obligatorios para evitar consultar todo el histórico.
     *
     * @param string $desde  Fecha inicio YYYY-MM-DD
     * @param string $hasta  Fecha fin   YYYY-MM-DD
     */
    public function marcasAsistencia(string $desde, string $hasta, int $timeout = 60, ?int $empresa = null): array
    {
        $query = [
            'desde' => $desde,
            'hasta' => $hasta,
        ];
        if ($empresa !== null) {
            $query['empresa'] = $empresa;
        }

        return $this->fetchAll('mark/', $query, 200, $timeout);
    }

    /**
     * Obtiene los días de rotación de turnos (rotativeDay-paginado).
     */
    public function rotativeDays(array $query = []): array
    {
        return $this->fetchAll('rotativeDay-paginado/', $query);
    }

    // ─── RRHH ─────────────────────────────────────────────────────────────────

    /**
     * Obtiene el saldo de vacaciones actual por empleado (snapshot).
     * Endpoint: saldo-vacaciones-empresa/ (~862 registros).
     */
    public function saldoVacaciones(): array
    {
        return $this->fetchAll('saldo-vacaciones-empresa/', [], 200, 60);
    }

    /**
     * Obtiene ausencias/licencias/permisos paginados.
     * Filtros opcionales: fechaDesde, fechaHasta (formato YYYY-MM-DD).
     *
     * @param string|null $desde  Filtrar desde esta fecha (YYYY-MM-DD)
     * @param string|null $hasta  Filtrar hasta esta fecha (YYYY-MM-DD)
     */
    /**
     * Obtiene la jornada calculada por persona y día (assignationSummaryApi/).
     * Incluye workingDay (bool) — permite distinguir días de descanso de ausencias reales.
     *
     * @param string $desde  Fecha inicio YYYY-MM-DD
     * @param string $hasta  Fecha fin   YYYY-MM-DD
     */
    public function assignationSummary(string $desde, string $hasta, int $timeout = 120, ?int $empresa = null): array
    {
        // La API ignora cualquier parámetro de filtro y devuelve todos los registros (1.2 M+).
        // Los resultados vienen ordenados newest-first, por lo que hacemos early-stop
        // cuando encontramos registros anteriores a $desde para evitar descargar todo.
        $all      = [];
        $page     = 1;
        $pageSize = 200;
        $endpoint = 'assignationSummaryApi/';

        $query = ['page' => $page, 'page_size' => $pageSize];
        if ($empresa !== null) {
            $query['empresa'] = $empresa;
        }

        do {
            $query['page'] = $page;
            $data    = $this->get($endpoint, $query, $timeout);
            $results = $data['results'] ?? [];
            $hasNext = ! empty($data['next']);

            $shouldStop = false;
            foreach ($results as $record) {
                $date = $record['date'] ?? null;
                if ($date === null || $date < $desde) {
                    $shouldStop = true;
                    break;
                }
                if ($date <= $hasta) {
                    $all[] = $record;
                }
            }

            $page++;

            if ($hasNext && ! $shouldStop) {
                usleep(200 * 1000); // 200 ms entre páginas
            }
        } while ($hasNext && ! $shouldStop);

        return $all;
    }

    /**
     * Obtiene todas las asignaciones persona ↔ turno (workShiftPersonRange/).
     * Devuelve: id, fromDate, toDate, workShift (int), person (int).
     */
    public function workShiftPersonRange(array $query = [], int $timeout = 120): array
    {
        // 300 ms entre páginas para no exceder el rate-limit (60 K registros ≈ 303 páginas)
        return $this->fetchAll('workShiftPersonRange/', $query, 200, $timeout, 1, 300);
    }

    public function ausencias(?string $desde = null, ?string $hasta = null): array
    {
        $query = [];
        if ($desde) {
            $query['fechaDesde'] = $desde;
        }
        if ($hasta) {
            $query['fechaHasta'] = $hasta;
        }

        // La API ignora los filtros de fecha y devuelve todos los registros ordenados
        // por ID ascendente (más antiguos primero). Hacemos una probe para obtener el
        // total y saltamos directamente a las páginas recientes, evitando años de historia.
        $startPage = 1;
        if ($desde) {
            try {
                $probe     = $this->get('personaAusencia-paginado/', ['page_size' => 1, 'page' => 1], 60);
                $total     = (int) ($probe['count'] ?? 0);
                if ($total > 400) {
                    $totalPages = (int) ceil($total / 200);
                    $startPage  = max(1, $totalPages - 50); // Buffer: ~50 páginas = ~10.000 registros
                }
            } catch (\Exception) {
                // Fallback: comenzar desde página 1
            }
        }

        return $this->fetchAll('personaAusencia-paginado/', $query, 200, 120, $startPage);
    }
}
