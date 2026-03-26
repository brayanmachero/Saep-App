<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class KizeoService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.kizeo.url', 'https://www.kizeoforms.com/rest/v3'), '/');
        $this->token   = config('services.kizeo.token', '');
    }

    /**
     * GET request a la API Kizeo.
     */
    private function get(string $endpoint, int $timeout = 30)
    {
        $response = Http::withHeaders(['Authorization' => $this->token])
            ->timeout($timeout)
            ->get("{$this->baseUrl}/{$endpoint}");

        if ($response->failed()) {
            throw new \Exception("Kizeo API error [{$response->status()}]: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * POST request a la API Kizeo.
     */
    private function post(string $endpoint, array $body = [], int $timeout = 30)
    {
        $response = Http::withHeaders(['Authorization' => $this->token])
            ->timeout($timeout)
            ->post("{$this->baseUrl}/{$endpoint}", $body);

        if ($response->failed()) {
            throw new \Exception("Kizeo API error [{$response->status()}]: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Obtener todos los formularios.
     */
    public function getForms(): array
    {
        $data = Cache::remember('kizeo_forms', 3600, function () {
            return $this->get('forms');
        });

        return $data['forms'] ?? [];
    }

    /**
     * Formularios filtrados por categoría "Prevención de Riesgos".
     */
    public function getPdrForms(): array
    {
        $forms = $this->getForms();
        return array_filter($forms, function ($f) {
            return stripos($f['class'] ?? '', 'Prevención') !== false
                || stripos($f['name'] ?? '', 'PDR') !== false;
        });
    }

    /**
     * Obtener todos los registros (data) de un formulario — cacheado 15 min.
     */
    public function getFormData(string $formId, bool $forceRefresh = false): array
    {
        $key = "kizeo_form_data_{$formId}";
        if ($forceRefresh) Cache::forget($key);

        return Cache::remember($key, 7200, function () use ($formId) {
            $data = $this->get("forms/{$formId}/data/all");
            return $data['data'] ?? [];
        });
    }

    /**
     * Obtener un registro específico con campos profundos — cacheado 1 hora.
     */
    public function getRecord(string $formId, string $dataId): ?array
    {
        $key = "kizeo_record_{$formId}_{$dataId}";

        return Cache::remember($key, 28800, function () use ($formId, $dataId) {
            $data = $this->get("forms/{$formId}/data/{$dataId}", 15);
            return $data['data'] ?? null;
        });
    }

    /**
     * Obtener registros profundos (con campos) filtrados por rango de fechas.
     * Limita a $limit registros para proteger memoria.
     */
    public function getDeepFormData(string $formId, ?string $startDate = null, ?string $endDate = null, int $limit = 300): array
    {
        $allMetadata = $this->getFormData($formId);

        // Filtrar por rango
        $filtered = array_filter($allMetadata, function ($record) use ($startDate, $endDate) {
            $d = explode(' ', $record['update_time'] ?? $record['create_time'] ?? '')[0] ?? '';
            if (!$d) return false;
            if ($startDate && $d < $startDate) return false;
            if ($endDate && $d > $endDate) return false;
            return true;
        });

        // Ordenar por más reciente y limitar
        usort($filtered, fn($a, $b) => ($b['create_time'] ?? '') <=> ($a['create_time'] ?? ''));
        $filtered = array_slice($filtered, 0, $limit);

        if (empty($filtered)) return [];

        // Obtener registros completos (secuencialmente para no saturar API)
        $results = [];
        foreach ($filtered as $record) {
            try {
                $full = $this->getRecord($formId, $record['id']);
                if ($full) $results[] = $full;
            } catch (\Exception $e) {
                continue;
            }
        }

        return $results;
    }

    /**
     * Obtener lista de usuarios Kizeo.
     */
    public function getUsers(): array
    {
        $data = Cache::remember('kizeo_users', 3600, function () {
            return $this->get('users');
        });

        // La API devuelve { status, data: { users: [...] } }
        $users = $data['data']['users']
            ?? $data['users']
            ?? $data['data']
            ?? $data;

        if (is_object($users)) {
            $users = (array) $users;
        }
        if (!is_array($users)) return [];

        // Si las entradas no son arrays de usuario, devolver vacío
        $first = reset($users);
        if ($first !== false && !is_array($first)) return [];

        return array_values($users);
    }

    /**
     * Construir diccionario userId => nombreCompleto.
     */
    public function getUserDictionary(): array
    {
        $users = $this->getUsers();
        $dic = [];
        foreach ($users as $u) {
            $id = $u['id'] ?? null;
            if (!$id) continue;
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $dic[$id] = $name ?: ($u['login'] ?? $u['user_name'] ?? "Usuario-{$id}");
        }
        return $dic;
    }

    /**
     * Obtener media (foto/firma) como base64.
     */
    public function getMedia(string $formId, string $recordId, string $mediaId): ?array
    {
        $url = "{$this->baseUrl}/forms/{$formId}/data/{$recordId}/medias/{$mediaId}";
        $response = Http::withHeaders(['Authorization' => $this->token])
            ->timeout(15)
            ->get($url);

        if ($response->failed()) return null;

        return [
            'type'   => $response->header('Content-Type', 'image/jpeg'),
            'base64' => base64_encode($response->body()),
        ];
    }

    /**
     * Limpiar toda la caché de Kizeo (forzar refresh completo).
     */
    public function clearCache(): void
    {
        Cache::forget('kizeo_forms');
        Cache::forget('kizeo_users');

        // Limpiar form data cacheado
        $forms = $this->getPdrForms();
        foreach ($forms as $f) {
            Cache::forget('kizeo_form_data_' . $f['id']);
        }

        // Limpiar dashboard y deep data (patrón wildcard via tags no disponible, limpiar conocidos)
        $prefixes = ['kizeo_dashboard_', 'kizeo_deep_all_'];
        // Re-fetch forms y users frescos
        Cache::forget('kizeo_forms');
        Cache::forget('kizeo_users');
    }

    /**
     * Info sobre el estado de la caché.
     */
    public function getCacheStatus(): array
    {
        return [
            'forms_cached'    => Cache::has('kizeo_forms'),
            'users_cached'    => Cache::has('kizeo_users'),
        ];
    }

    /**
     * Dashboard: agregar data de todos los formularios PDR con estadísticas.
     * Cacheado 4 horas por rango de fechas (persistente para acceso rápido).
     */
    public function getDashboardData(?string $startDate = null, ?string $endDate = null, bool $forceRefresh = false): array
    {
        $cacheKey = 'kizeo_dashboard_' . md5(($startDate ?? 'null') . '_' . ($endDate ?? 'null'));
        if ($forceRefresh) Cache::forget($cacheKey);

        return Cache::remember($cacheKey, 14400, function () use ($startDate, $endDate) {
            return $this->buildDashboardData($startDate, $endDate);
        });
    }

    /**
     * Construir data del dashboard (interno, sin caché).
     */
    private function buildDashboardData(?string $startDate, ?string $endDate): array
    {
        $forms = $this->getPdrForms();
        $userDic = $this->getUserDictionary();

        $totalRecords   = 0;
        $incidentes     = 0;
        $charlas        = 0;
        $inspecciones   = 0;
        $auditorsData   = [];
        $formDistribution = [];
        $dailyActivity  = [];
        $recentRecords  = [];

        // New: calendar events, per-form-type counts by day, incident tracking
        $calendarEvents = [];
        $incidentDates  = [];
        $formTypeByDay  = []; // date => [type => count]

        // Previous period for comparison (same length before startDate)
        $prevIncidentes   = 0;
        $prevCharlas      = 0;
        $prevInspecciones = 0;
        $prevTotal        = 0;
        $prevStartDate    = null;
        $prevEndDate      = null;

        if ($startDate && $endDate) {
            $days = (int) round((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
            $prevEndDate   = date('Y-m-d', strtotime($startDate) - 1);
            $prevStartDate = date('Y-m-d', strtotime($prevEndDate) - $days + 1);
        }

        foreach ($forms as $form) {
            $formId   = $form['id'];
            $formName = $form['name'] ?? "Form-{$formId}";
            $records  = $this->getFormData($formId);

            $nameLower = strtolower($formName);

            // Determine form category
            $category = 'otro';
            if (str_contains($nameLower, 'incidente') || str_contains($nameLower, 'accidente')) {
                $category = 'incidente';
            } elseif (str_contains($nameLower, 'charla') || str_contains($nameLower, 'capacitación') || str_contains($nameLower, 'reunión') || str_contains($nameLower, 'cphs')) {
                $category = 'charla';
            } elseif (str_contains($nameLower, 'inspección') || str_contains($nameLower, 'ast') || str_contains($nameLower, 'observación') || str_contains($nameLower, 'seguro')) {
                $category = 'inspeccion';
            } elseif (str_contains($nameLower, 'visita')) {
                $category = 'visita';
            }

            // Process current period
            $filtered = array_filter($records, function ($r) use ($startDate, $endDate) {
                $d = explode(' ', $r['update_time'] ?? $r['create_time'] ?? '')[0] ?? '';
                if (!$d) return false;
                if ($startDate && $d < $startDate) return false;
                if ($endDate && $d > $endDate) return false;
                return true;
            });

            $count = count($filtered);
            $totalRecords += $count;

            if ($category === 'incidente') $incidentes += $count;
            if ($category === 'charla') $charlas += $count;
            if (in_array($category, ['inspeccion', 'visita'])) $inspecciones += $count;

            if ($count > 0) {
                $formDistribution[] = ['label' => $formName, 'count' => $count];
            }

            foreach ($filtered as $record) {
                $dateRaw = $record['update_time'] ?? $record['create_time'] ?? '';
                $date = explode(' ', $dateRaw)[0] ?? '';

                if ($date) {
                    $dailyActivity[$date] = ($dailyActivity[$date] ?? 0) + 1;

                    // Calendar event
                    $calendarEvents[] = [
                        'date'     => $date,
                        'form'     => $formName,
                        'category' => $category,
                        'user'     => $userDic[$record['user_id'] ?? ''] ?? ($record['user_name'] ?? 'Desconocido'),
                        'form_id'  => $formId,
                        'record_id'=> $record['id'] ?? null,
                    ];

                    // Track by type per day
                    if (!isset($formTypeByDay[$date])) {
                        $formTypeByDay[$date] = ['incidente' => 0, 'charla' => 0, 'inspeccion' => 0, 'visita' => 0, 'otro' => 0];
                    }
                    $formTypeByDay[$date][$category] = ($formTypeByDay[$date][$category] ?? 0) + 1;

                    // Track incident dates
                    if ($category === 'incidente') {
                        $incidentDates[] = $date;
                    }
                }

                // Auditores
                $userId  = $record['user_id'] ?? null;
                $updateUserId = $record['update_user_id'] ?? null;
                $userName = $userDic[$userId]
                    ?? ($record['user_name'] ?? null)
                    ?? ($updateUserId ? ($userDic[$updateUserId] ?? null) : null)
                    ?? ($userId ? "ID-{$userId}" : 'Desconocido');

                if (!isset($auditorsData[$userName])) {
                    $auditorsData[$userName] = ['count' => 0, 'lastDate' => '', 'lastForm' => ''];
                }
                $auditorsData[$userName]['count']++;
                if ($date > $auditorsData[$userName]['lastDate']) {
                    $auditorsData[$userName]['lastDate'] = $date;
                    $auditorsData[$userName]['lastForm'] = $formName;
                }

                $recentRecords[] = [
                    'form'       => $formName,
                    'form_id'    => $formId,
                    'record_id'  => $record['id'] ?? null,
                    'user'       => $userName,
                    'date'       => $dateRaw,
                    'date_short' => $date,
                ];
            }

            // Process previous period for comparison
            if ($prevStartDate && $prevEndDate) {
                $prevFiltered = array_filter($records, function ($r) use ($prevStartDate, $prevEndDate) {
                    $d = explode(' ', $r['update_time'] ?? $r['create_time'] ?? '')[0] ?? '';
                    if (!$d) return false;
                    return $d >= $prevStartDate && $d <= $prevEndDate;
                });
                $prevCount = count($prevFiltered);
                $prevTotal += $prevCount;
                if ($category === 'incidente') $prevIncidentes += $prevCount;
                if ($category === 'charla') $prevCharlas += $prevCount;
                if (in_array($category, ['inspeccion', 'visita'])) $prevInspecciones += $prevCount;
            }
        }

        // Ordenar actividad diaria
        ksort($dailyActivity);
        ksort($formTypeByDay);

        // Top auditores (ordenar por count desc)
        uasort($auditorsData, fn($a, $b) => $b['count'] <=> $a['count']);

        // Recientes (top 50)
        usort($recentRecords, fn($a, $b) => ($b['date'] ?? '') <=> ($a['date'] ?? ''));
        $recentRecords = array_slice($recentRecords, 0, 50);

        // === COMPLIANCE METRICS ===
        $today = date('Y-m-d');

        // Days without incidents: count from last incident to today (or end of period)
        sort($incidentDates);
        $lastIncidentDate = !empty($incidentDates) ? end($incidentDates) : null;
        $diasSinAccidente = $lastIncidentDate
            ? max(0, (int) round((strtotime($today) - strtotime($lastIncidentDate)) / 86400))
            : ($startDate ? (int) round((strtotime($today) - strtotime($startDate)) / 86400) : 0);

        // Period comparison deltas
        $deltaTotal = $prevTotal > 0 ? round((($totalRecords - $prevTotal) / $prevTotal) * 100, 1) : null;
        $deltaIncidentes = $prevIncidentes > 0 ? round((($incidentes - $prevIncidentes) / $prevIncidentes) * 100, 1) : null;
        $deltaCharlas = $prevCharlas > 0 ? round((($charlas - $prevCharlas) / $prevCharlas) * 100, 1) : null;
        $deltaInspecciones = $prevInspecciones > 0 ? round((($inspecciones - $prevInspecciones) / $prevInspecciones) * 100, 1) : null;

        // Days in period with at least 1 activity
        $activeDays = count($dailyActivity);
        $totalDaysInPeriod = ($startDate && $endDate) ? max(1, (int) round((strtotime($endDate) - strtotime($startDate)) / 86400) + 1) : $activeDays;
        $coverageRate = $totalDaysInPeriod > 0 ? round(($activeDays / $totalDaysInPeriod) * 100, 1) : 0;

        // === ALERTS ===
        $alerts = [];

        // Inactive auditors (>5 days)
        foreach ($auditorsData as $name => $d) {
            if (!$d['lastDate']) continue;
            $daysSince = (int) round((strtotime($today) - strtotime($d['lastDate'])) / 86400);
            if ($daysSince >= 5) {
                $alerts[] = [
                    'type'    => 'warning',
                    'icon'    => 'person-x-fill',
                    'title'   => "Auditor inactivo: {$name}",
                    'detail'  => "Sin actividad hace {$daysSince} días. Último: {$d['lastForm']} ({$d['lastDate']})",
                    'category'=> 'inactividad',
                ];
            }
        }

        // No incidents reported is good — but if period > 15 days and 0 inspections, flag
        if ($totalDaysInPeriod > 15 && $inspecciones === 0) {
            $alerts[] = [
                'type'    => 'danger',
                'icon'    => 'shield-x',
                'title'   => 'Sin inspecciones en el periodo',
                'detail'  => "No se registraron inspecciones ni visitas en {$totalDaysInPeriod} días.",
                'category'=> 'cumplimiento',
            ];
        }

        // If high incidents relative to inspections
        if ($incidentes > 0 && $inspecciones > 0 && ($incidentes / $inspecciones) > 0.5) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'exclamation-triangle-fill',
                'title'   => 'Alta tasa de incidentes vs inspecciones',
                'detail'  => "{$incidentes} incidentes vs {$inspecciones} inspecciones. Se recomienda aumentar cobertura preventiva.",
                'category'=> 'riesgo',
            ];
        }

        // Low charla coverage
        if ($totalDaysInPeriod > 7 && $charlas < 2) {
            $alerts[] = [
                'type'    => 'info',
                'icon'    => 'megaphone-fill',
                'title'   => 'Pocas charlas de seguridad',
                'detail'  => "Solo {$charlas} charlas registradas en {$totalDaysInPeriod} días. Mínimo recomendado: 1 semanal.",
                'category'=> 'cumplimiento',
            ];
        }

        // Days with no activity in the last 7 days
        $last7 = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            if (!isset($dailyActivity[$d]) && $d >= ($startDate ?? '2000-01-01') && $d <= ($endDate ?? '2099-12-31')) {
                $last7[] = $d;
            }
        }
        if (count($last7) >= 3) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'calendar-x-fill',
                'title'   => 'Días sin actividad reciente',
                'detail'  => count($last7) . " de los últimos 7 días sin registros SST.",
                'category'=> 'actividad',
            ];
        }

        // Good news alerts
        if ($diasSinAccidente >= 30) {
            $alerts[] = [
                'type'    => 'success',
                'icon'    => 'shield-check',
                'title'   => "{$diasSinAccidente} días sin accidentes",
                'detail'  => "¡Excelente! Se mantiene un periodo prolongado sin incidentes registrados.",
                'category'=> 'logro',
            ];
        }

        return [
            'forms'            => array_values($forms),
            'stats'            => [
                'total'        => $totalRecords,
                'incidentes'   => $incidentes,
                'charlas'      => $charlas,
                'inspecciones' => $inspecciones,
                'auditores'    => count($auditorsData),
            ],
            'formDistribution' => $formDistribution,
            'dailyActivity'    => $dailyActivity,
            'auditorsData'     => $auditorsData,
            'recentRecords'    => $recentRecords,
            // New sections
            'compliance'       => [
                'diasSinAccidente' => $diasSinAccidente,
                'lastIncident'     => $lastIncidentDate,
                'coverageRate'     => $coverageRate,
                'activeDays'       => $activeDays,
                'totalDays'        => $totalDaysInPeriod,
                'delta'            => [
                    'total'        => $deltaTotal,
                    'incidentes'   => $deltaIncidentes,
                    'charlas'      => $deltaCharlas,
                    'inspecciones' => $deltaInspecciones,
                ],
            ],
            'calendar'         => [
                'events'       => $calendarEvents,
                'typeByDay'    => $formTypeByDay,
            ],
            'alerts'           => $alerts,
            'cached_at'        => now()->toDateTimeString(),
        ];
    }

    /**
     * Deep Analytics: obtener datos profundos de TODOS los formularios PDR.
     * Cacheado 30 minutos. Límite por formulario para proteger memoria.
     */
    public function getAllDeepData(?string $startDate = null, ?string $endDate = null, bool $forceRefresh = false, int $limitPerForm = 30): array
    {
        $cacheKey = 'kizeo_deep_all_' . md5(($startDate ?? 'null') . '_' . ($endDate ?? 'null'));
        if ($forceRefresh) Cache::forget($cacheKey);

        return Cache::remember($cacheKey, 14400, function () use ($startDate, $endDate, $limitPerForm) {
            return $this->buildAllDeepData($startDate, $endDate, $limitPerForm);
        });
    }

    /**
     * Construir deep data de todos los formularios (interno).
     */
    private function buildAllDeepData(?string $startDate, ?string $endDate, int $limitPerForm): array
    {
        $forms = $this->getPdrForms();
        $userDic = $this->getUserDictionary();
        $allRecords = [];
        $formStats = [];
        $totalFields = 0;
        $fieldKeysGlobal = [];

        foreach ($forms as $form) {
            $formId   = $form['id'];
            $formName = $form['name'] ?? "Form-{$formId}";

            try {
                $records = $this->getDeepFormData($formId, $startDate, $endDate, $limitPerForm);
            } catch (\Exception $e) {
                continue;
            }

            $formFieldKeys = [];
            foreach ($records as &$rec) {
                $rec['_form_name'] = $formName;
                $rec['_form_id']   = $formId;

                // Resolver nombre de usuario
                $userId = $rec['user_id'] ?? null;
                $rec['_user_display'] = $userDic[$userId] ?? ($rec['user_name'] ?? "ID-{$userId}");

                // Extraer info de firmas, asistentes y nombres de sub-registros
                $firmasTotal = 0;
                $firmasSigned = 0;
                $asistentes = 0;
                $attendeeNames = [];

                foreach ($rec['fields'] ?? [] as $k => $field) {
                    // Recopilar field keys
                    if (!str_starts_with($k, '_') && $k !== 'id') {
                        $formFieldKeys[$k] = true;
                        $fieldKeysGlobal[$k] = true;
                    }

                    // Firmas a nivel raíz del registro
                    if (isset($field['type']) && $field['type'] === 'signature') {
                        $firmasTotal++;
                        if (!empty($field['value'])) $firmasSigned++;
                    }

                    // Sub-registros (asistentes, etc.)
                    if (isset($field['value']) && is_array($field['value'])) {
                        foreach ($field['value'] as $subRec) {
                            if (!is_array($subRec)) continue;
                            $asistentes++;

                            // Buscar firmas dentro del sub-registro
                            foreach ($subRec as $sf) {
                                if (!is_array($sf) || !isset($sf['type'])) continue;
                                if ($sf['type'] === 'signature') {
                                    $firmasTotal++;
                                    if (!empty($sf['value'])) $firmasSigned++;
                                }
                            }

                            // Extraer nombres de asistentes (buscar campos con nombre/rut)
                            foreach ($subRec as $sfKey => $sf) {
                                if (!is_array($sf) || !isset($sf['value'])) continue;
                                $sfKeyLower = strtolower($sfKey);
                                if (str_contains($sfKeyLower, 'nombre') || str_contains($sfKeyLower, 'apellido')
                                    || str_contains($sfKeyLower, 'rut') || str_contains($sfKeyLower, 'name')) {
                                    $val = $sf['value'];
                                    if (is_string($val) && trim($val) !== '') {
                                        $attendeeNames[] = trim($val);
                                    }
                                }
                            }
                        }
                    }
                }

                $rec['_firmas_total'] = $firmasTotal;
                $rec['_firmas_signed'] = $firmasSigned;
                $rec['_asistentes'] = $asistentes;
                $rec['_attendee_names'] = $attendeeNames;
            }
            unset($rec);

            $formStats[] = [
                'form_id'   => $formId,
                'form_name' => $formName,
                'records'   => count($records),
                'fields'    => count($formFieldKeys),
            ];

            $allRecords = array_merge($allRecords, $records);
        }

        // Ordenar por fecha más reciente
        usort($allRecords, function ($a, $b) {
            return ($b['update_time'] ?? $b['create_time'] ?? '') <=> ($a['update_time'] ?? $a['create_time'] ?? '');
        });

        return [
            'records'    => $allRecords,
            'formStats'  => $formStats,
            'totalFields'=> count($fieldKeysGlobal),
            'cached_at'  => now()->toDateTimeString(),
        ];
    }
}
