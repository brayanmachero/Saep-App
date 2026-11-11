<?php

namespace App\Services;

use App\Models\CentroCosto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vincula centros_costo con los centros oficiales de Talana (empresas SUB y EST).
 *
 * Por defecto solo registra el vínculo (código, nombre oficial, modalidad y vigencia)
 * en columnas propias: no renombra, no crea ni desactiva centros, porque
 * centros_costo lo usan muchos módulos. Esas acciones son opcionales.
 *
 * Orden de emparejamiento:
 *   1. codigo_talana ya vinculado
 *   2. codigo local igual al código Talana (ej. 35-SUB)
 *   3. nombre local igual al nombre Talana con modalidad (ej. "LTS PEÑON SUB")
 *   4. nombre local igual al nombre base (ej. "LTS PEÑON"); si el mismo nombre
 *      existe en SUB y EST, decide la razón social (NORMAL→SUB, TRANSITORIO→EST)
 */
class CentroCostoTalanaSyncService
{
    public function __construct(private readonly TalanaService $talana) {}

    public function synchronize(
        bool $dryRun = true,
        bool $crearFaltantes = false,
        bool $actualizarNombres = false,
        bool $desactivarNoVigentes = false
    ): array {
        $lock = Cache::lock('centros-costo-talana-sync', 900);
        if (! $lock->get()) {
            throw new \RuntimeException('Ya hay una sincronización de centros de costo con Talana en curso.');
        }

        try {
            [$talana, $duplicadosTalana] = $this->fetchTalana();
            $plan = $this->plan($talana, CentroCosto::all());
            $plan['conflictos'] = array_merge(
                array_map(fn ($key) => ['talana' => $key, 'motivo' => 'Código repetido en la respuesta de Talana'], $duplicadosTalana),
                $plan['conflictos']
            );

            $report = [
                'dry_run' => $dryRun,
                'talana_total' => count($talana),
                'vinculos' => $plan['vinculos'],
                'faltantes' => $plan['faltantes'],
                'conflictos' => $plan['conflictos'],
                'locales_sin_talana' => $plan['locales_sin_talana'],
                'huerfanos' => $plan['huerfanos'],
                'creados' => [],
                'renombrados' => [],
                'desactivados' => [],
            ];

            $aplicar = function () use (&$report, $plan, $dryRun, $crearFaltantes, $actualizarNombres, $desactivarNoVigentes) {
                $now = now();

                foreach ($plan['vinculos'] as $vinculo) {
                    /** @var CentroCosto $local */
                    $local = $vinculo['local_model'];
                    $t = $vinculo['talana_row'];

                    $local->fill([
                        'codigo_talana' => $t['key'],
                        'modalidad_talana' => $t['modalidad'],
                        'nombre_talana' => $t['nombre_original'],
                        'talana_vigente' => $t['vigente'],
                        'talana_sincronizado_at' => $now,
                    ]);

                    if ($actualizarNombres && $local->nombre !== $t['nombre']) {
                        $report['renombrados'][] = ['id' => $local->id, 'de' => $local->nombre, 'a' => $t['nombre']];
                        $local->nombre = $t['nombre'];
                    }

                    if ($desactivarNoVigentes && ! $t['vigente'] && $local->activo) {
                        $report['desactivados'][] = ['id' => $local->id, 'nombre' => $local->nombre, 'talana' => $t['key']];
                        $local->activo = false;
                    }

                    if (! $dryRun && $local->isDirty()) {
                        $local->save();
                    }
                }

                if ($crearFaltantes) {
                    $codigosUsados = CentroCosto::pluck('codigo')->map(fn ($c) => strtoupper((string) $c))->flip();

                    foreach ($plan['faltantes'] as $faltante) {
                        $t = $faltante['talana_row'];
                        if (! $t['vigente']) {
                            continue; // no se importan centros históricos
                        }

                        $codigo = isset($codigosUsados[$t['key']]) ? 'TAL-' . $t['key'] : $t['key'];
                        if (isset($codigosUsados[$codigo])) {
                            $report['conflictos'][] = ['talana' => $t['key'], 'motivo' => "No se pudo crear: el código {$codigo} ya existe"];
                            continue;
                        }
                        $codigosUsados[$codigo] = true;

                        $datos = [
                            'codigo' => $codigo,
                            'nombre' => $t['nombre'],
                            'razon_social' => $t['modalidad'] === 'EST' ? 'TRANSITORIO' : 'NORMAL',
                            'activo' => true,
                            'codigo_talana' => $t['key'],
                            'modalidad_talana' => $t['modalidad'],
                            'nombre_talana' => $t['nombre_original'],
                            'talana_vigente' => true,
                            'talana_sincronizado_at' => $now,
                        ];
                        $report['creados'][] = ['talana' => $t['key'], 'nombre' => $t['nombre'], 'codigo' => $codigo];

                        if (! $dryRun) {
                            CentroCosto::create($datos);
                        }
                    }
                }
            };

            if ($dryRun) {
                $aplicar();
            } else {
                DB::transaction($aplicar);
            }

            // Sin modelos en el reporte: solo datos legibles.
            $report['vinculos'] = array_map(fn ($v) => array_diff_key($v, ['local_model' => 1, 'talana_row' => 1]), $report['vinculos']);
            $report['faltantes'] = array_map(fn ($f) => array_diff_key($f, ['talana_row' => 1]), $report['faltantes']);

            return $report;
        } finally {
            $lock->release();
        }
    }

    /**
     * Calcula el emparejamiento sin modificar nada.
     *
     * @param  array<string,array>  $talana  filas Talana indexadas por clave CODIGO-MODALIDAD
     */
    public function plan(array $talana, Collection $locales): array
    {
        $claimed = [];     // id local => clave Talana
        $resolved = [];    // clave Talana => [local, metodo]
        $conflictos = [];

        // Closure por referencia: una arrow fn capturaría $claimed vacío para siempre.
        $libre = function (CentroCosto $c) use (&$claimed) {
            return ! isset($claimed[$c->id]);
        };
        $sinVinculo = fn (CentroCosto $c) => blank($c->codigo_talana);
        $claim = function (string $key, CentroCosto $local, string $metodo) use (&$claimed, &$resolved) {
            $claimed[$local->id] = $key;
            $resolved[$key] = ['local' => $local, 'metodo' => $metodo];
        };

        // 1. Vínculo existente
        foreach ($talana as $key => $t) {
            $local = $locales->first(fn (CentroCosto $c) => strtoupper((string) $c->codigo_talana) === $key);
            if ($local) {
                $claim($key, $local, 'vínculo existente');
            }
        }

        // 2. Código local igual al código Talana
        foreach ($talana as $key => $t) {
            if (isset($resolved[$key])) continue;
            $matches = $locales->filter(fn (CentroCosto $c) => $sinVinculo($c) && $libre($c) && strtoupper(trim((string) $c->codigo)) === $key);
            if ($matches->count() === 1) {
                $claim($key, $matches->first(), 'código local');
            }
        }

        // 3. Nombre con modalidad
        foreach ($talana as $key => $t) {
            if (isset($resolved[$key])) continue;
            $matches = $locales->filter(fn (CentroCosto $c) => $sinVinculo($c) && $libre($c)
                && CentroCosto::normalizarNombre($c->nombre) === $t['normalizado']);
            if ($matches->count() === 1) {
                $claim($key, $matches->first(), 'nombre con modalidad');
            } elseif ($matches->count() > 1) {
                $conflictos[] = ['talana' => $key, 'motivo' => 'Varios centros locales con el mismo nombre: ' . $matches->pluck('id')->implode(', ')];
                $resolved[$key] = null;
            }
        }

        // 4. Nombre base (sin SUB/EST)
        $modalidadesPorBase = collect($talana)->groupBy('base')->map->count();
        foreach ($talana as $key => $t) {
            if (array_key_exists($key, $resolved)) continue;

            $matches = $locales->filter(fn (CentroCosto $c) => $sinVinculo($c) && $libre($c)
                && CentroCosto::normalizarNombre($c->nombre) === $t['base'])->values();

            if ($matches->isEmpty()) continue;

            $compatibles = $matches->filter(fn (CentroCosto $c) => $this->razonSocialCompatible($c, $t['modalidad']))->values();
            $compartido = ($modalidadesPorBase[$t['base']] ?? 1) > 1;

            if ($compatibles->count() === 1) {
                $claim($key, $compatibles->first(), 'nombre base');
            } elseif (! $compartido && $matches->count() === 1) {
                $claim($key, $matches->first(), 'nombre base (razón social local distinta)');
            } else {
                $conflictos[] = [
                    'talana' => $key,
                    'motivo' => $compartido
                        ? "El nombre existe en SUB y EST; la razón social local no permite decidir (locales: {$matches->pluck('id')->implode(', ')})"
                        : 'Varios centros locales con el mismo nombre: ' . $matches->pluck('id')->implode(', '),
                ];
                $resolved[$key] = null;
            }
        }

        $vinculos = [];
        $faltantes = [];
        foreach ($talana as $key => $t) {
            $r = $resolved[$key] ?? null;
            if ($r) {
                $vinculos[] = [
                    'talana' => $key,
                    'talana_nombre' => $t['nombre'],
                    'vigente' => $t['vigente'],
                    'local_id' => $r['local']->id,
                    'local_nombre' => $r['local']->nombre,
                    'local_activo' => (bool) $r['local']->activo,
                    'metodo' => $r['metodo'],
                    'local_model' => $r['local'],
                    'talana_row' => $t,
                ];
            } elseif (! array_key_exists($key, $resolved)) {
                $faltantes[] = ['talana' => $key, 'nombre' => $t['nombre'], 'vigente' => $t['vigente'], 'talana_row' => $t];
            }
        }

        $huerfanos = $locales
            ->filter(fn (CentroCosto $c) => filled($c->codigo_talana) && ! isset($talana[strtoupper($c->codigo_talana)]))
            ->map(fn (CentroCosto $c) => ['id' => $c->id, 'nombre' => $c->nombre, 'codigo_talana' => $c->codigo_talana])
            ->values()->all();

        $localesSinTalana = $locales
            ->filter(fn (CentroCosto $c) => ! isset($claimed[$c->id]) && blank($c->codigo_talana))
            ->map(fn (CentroCosto $c) => ['id' => $c->id, 'codigo' => $c->codigo, 'nombre' => $c->nombre, 'activo' => (bool) $c->activo])
            ->sortBy('nombre')->values()->all();

        return [
            'vinculos' => $vinculos,
            'faltantes' => $faltantes,
            'conflictos' => $conflictos,
            'huerfanos' => $huerfanos,
            'locales_sin_talana' => $localesSinTalana,
        ];
    }

    /** @return array{0: array<string,array>, 1: string[]} filas indexadas y claves duplicadas */
    private function fetchTalana(): array
    {
        $source = [];
        $duplicados = [];

        foreach (config('services.talana.inventory_companies', ['SUB' => 1039, 'EST' => 1081]) as $modalidad => $empresa) {
            $rows = $this->talana->centrosCosto((int) $empresa);
            if (! $rows) {
                throw new \RuntimeException("Talana no devolvió centros para {$modalidad}; no se modifica nada.");
            }

            foreach ($rows as $row) {
                $codigo = strtoupper(trim((string) ($row['codigo'] ?? '')));
                $nombre = trim((string) ($row['nombre'] ?? ''));
                if ((int) ($row['empresa'] ?? 0) !== (int) $empresa || $codigo === '' || $nombre === '') {
                    throw new \RuntimeException('Respuesta Talana inválida (empresa, código o nombre); no se modifica nada.');
                }

                $conModalidad = preg_match('/\b' . preg_quote($modalidad, '/') . '$/i', $nombre) ? $nombre : $nombre . ' ' . $modalidad;
                $key = $codigo . '-' . $modalidad;

                if (isset($source[$key]) || in_array($key, $duplicados, true)) {
                    $duplicados[] = $key;
                    unset($source[$key]);
                    continue;
                }

                $source[$key] = [
                    'key' => $key,
                    'modalidad' => $modalidad,
                    'nombre_original' => $nombre,
                    'nombre' => $conModalidad,
                    'normalizado' => CentroCosto::normalizarNombre($conModalidad),
                    'base' => CentroCosto::nombreBase($conModalidad),
                    'vigente' => (bool) ($row['vigente'] ?? false),
                ];
            }
        }

        return [$source, array_values(array_unique($duplicados))];
    }

    private function razonSocialCompatible(CentroCosto $centro, string $modalidad): bool
    {
        return match (strtoupper((string) $centro->razon_social)) {
            'TRANSITORIO' => $modalidad === 'EST',
            'NORMAL' => $modalidad === 'SUB',
            default => true,
        };
    }
}
