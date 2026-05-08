<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeCentroCosto extends Command
{
    protected $signature = 'saep:merge-centro-costo
                            {--dry-run : Solo muestra qué cambiaría, sin modificar nada}
                            {--keep=CCU CENTRAL : Nombre exacto del centro que se conserva}
                            {--remove=CCU CERVECERA : Nombre exacto del centro que se elimina/fusiona}';

    protected $description = 'Fusiona dos centros de costo, redirigiendo todos los registros al centro a conservar y desactivando el otro.';

    // Todas las tablas con columna directa centro_costo_id
    private array $tablasDirectas = [
        'users'           => 'Usuarios',
        'charlas'         => 'Charlas SST',
        'visitas_sst'     => 'Visitas SST',
        'auditorias_sst'  => 'Auditorías SST',
        'accidentes_sst'  => 'Accidentes SST',
        'ley_karin'       => 'Casos Ley Karin',
        'programas_sst'   => 'Programas SST (Carta Gantt)',
        'kanban_tableros' => 'Tableros Kanban',
        'kanban_tareas'   => 'Tareas Kanban',
    ];

    public function handle(): int
    {
        $keepName   = $this->option('keep');
        $removeName = $this->option('remove');
        $isDryRun   = $this->option('dry-run');

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║        FUSIÓN DE CENTROS DE COSTO — SAEP             ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();

        // ─── 1. Localizar ambos centros ────────────────────────────────────
        $centroKeep   = DB::table('centros_costo')->where('nombre', $keepName)->first();
        $centroRemove = DB::table('centros_costo')->where('nombre', $removeName)->first();

        if (! $centroKeep) {
            $this->error("No se encontró el centro a conservar: \"{$keepName}\"");
            $this->showAvailableCentros();
            return self::FAILURE;
        }

        if (! $centroRemove) {
            $this->error("No se encontró el centro a eliminar: \"{$removeName}\"");
            $this->showAvailableCentros();
            return self::FAILURE;
        }

        $this->info("Centro a CONSERVAR : [{$centroKeep->id}] {$centroKeep->nombre} (activo: " . ($centroKeep->activo ? 'Sí' : 'No') . ')');
        $this->warn("Centro a FUSIONAR  : [{$centroRemove->id}] {$centroRemove->nombre} (activo: " . ($centroRemove->activo ? 'Sí' : 'No') . ')');
        $this->newLine();

        // ─── 2. Conteo por tabla directa ───────────────────────────────────
        $this->line('📋 <fg=yellow>REGISTROS CON COLUMNA DIRECTA centro_costo_id</>');
        $totalDirecto = 0;

        $rows = [];
        foreach ($this->tablasDirectas as $tabla => $etiqueta) {
            if (! $this->tableExists($tabla)) {
                continue;
            }
            $count = DB::table($tabla)->where('centro_costo_id', $centroRemove->id)->count();
            $totalDirecto += $count;
            $rows[] = [$etiqueta, $tabla, $count];
        }

        $this->table(['Módulo', 'Tabla', 'Registros afectados'], $rows);
        $this->line("   Total registros directos a reasignar: <fg=cyan>{$totalDirecto}</>");
        $this->newLine();

        // ─── 3. Análisis de respuestas (datos_json) ────────────────────────
        $this->line('📋 <fg=yellow>RESPUESTAS DE FORMULARIOS (datos_json)</>');

        $respuestasAfectadas = $this->findRespuestasAfectadas($centroRemove);
        $totalJson = count($respuestasAfectadas);

        $this->line("   Respuestas que contienen referencias a \"{$removeName}\" en el JSON: <fg=cyan>{$totalJson}</>");

        if ($totalJson > 0 && $isDryRun) {
            $this->newLine();
            $this->line('   Muestra (primeras 10):');
            $preview = array_slice($respuestasAfectadas, 0, 10);
            foreach ($preview as $r) {
                $this->line("   • Respuesta ID {$r->id} | Formulario ID {$r->formulario_id} | {$r->fecha_envio}");
            }
            if ($totalJson > 10) {
                $this->line("   ... y " . ($totalJson - 10) . " más.");
            }
        }

        $this->newLine();
        $totalGeneral = $totalDirecto + $totalJson;
        $this->info("TOTAL de registros que serían modificados: {$totalGeneral}");
        $this->newLine();

        // ─── 4. Si es dry-run, terminar ────────────────────────────────────
        if ($isDryRun) {
            $this->warn('Modo --dry-run: no se realizó ningún cambio.');
            $this->line('Para ejecutar la migración real, corre:');
            $this->line("  php artisan saep:merge-centro-costo");
            return self::SUCCESS;
        }

        // ─── 5. Confirmación ───────────────────────────────────────────────
        if (! $this->confirmAction($centroKeep, $centroRemove, $totalGeneral)) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        // ─── 6. Ejecutar migración ────────────────────────────────────────
        DB::beginTransaction();
        try {
            $this->line('');
            $this->line('Migrando registros...');

            // 6a. Tablas con columna directa
            foreach ($this->tablasDirectas as $tabla => $etiqueta) {
                if (! $this->tableExists($tabla)) {
                    continue;
                }
                $updated = DB::table($tabla)
                    ->where('centro_costo_id', $centroRemove->id)
                    ->update(['centro_costo_id' => $centroKeep->id]);

                if ($updated > 0) {
                    $this->line("  ✔ {$etiqueta} ({$tabla}): {$updated} registros actualizados");
                }
            }

            // 6b. Respuestas (datos_json)
            if ($totalJson > 0) {
                $this->line("  Procesando {$totalJson} respuestas con datos JSON...");
                $jsonUpdated = $this->updateRespuestasJson($respuestasAfectadas, $centroRemove, $centroKeep);
                $this->line("  ✔ Respuestas (datos_json): {$jsonUpdated} registros actualizados");
            }

            // 6c. Desactivar el centro de costo fusionado
            DB::table('centros_costo')
                ->where('id', $centroRemove->id)
                ->update(['activo' => false]);

            $this->line("  ✔ Centro \"{$removeName}\" marcado como inactivo (ID: {$centroRemove->id})");

            DB::commit();

            $this->newLine();
            $this->info('✅ Fusión completada exitosamente.');
            $this->warn("   Recuerda: \"{$removeName}\" fue DESACTIVADO (no eliminado) por seguridad.");
            $this->line('   Si confirmas que todo está correcto y no hay referencias residuales,');
            $this->line("   puedes eliminarlo manualmente desde la sección Centros de Costo.");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Error durante la migración: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function tableExists(string $tabla): bool
    {
        return DB::getSchemaBuilder()->hasTable($tabla);
    }

    /**
     * Busca respuestas cuyo datos_json contenga el ID numérico
     * o el nombre del centro a remover.
     */
    private function findRespuestasAfectadas(object $centroRemove): array
    {
        $id   = $centroRemove->id;
        $name = $centroRemove->nombre;

        // MySQL: busca en texto plano del JSON (rápido, sin parsear)
        return DB::table('respuestas')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($id, $name) {
                // El ID como valor JSON numérico o de cadena
                $q->whereRaw('JSON_SEARCH(datos_json, "one", ?) IS NOT NULL', [(string) $id])
                  ->orWhereRaw('JSON_SEARCH(datos_json, "one", ?) IS NOT NULL', [$name]);
            })
            ->select('id', 'formulario_id', 'datos_json', 'fecha_envio')
            ->get()
            ->toArray();
    }

    /**
     * Actualiza los datos_json de las respuestas afectadas,
     * reemplazando el ID y nombre del centro a remover por los del centro a conservar.
     * Maneja tanto valores numéricos como de texto.
     */
    private function updateRespuestasJson(array $respuestas, object $centroRemove, object $centroKeep): int
    {
        $updated    = 0;
        $idRemove   = (string) $centroRemove->id;
        $nameRemove = $centroRemove->nombre;
        $idKeep     = (string) $centroKeep->id;
        $nameKeep   = $centroKeep->nombre;

        foreach ($respuestas as $r) {
            $datos = json_decode($r->datos_json, true);
            if (! is_array($datos)) {
                continue;
            }

            $changed = false;
            $datos   = $this->replaceInArray($datos, $idRemove, $nameRemove, $idKeep, $nameKeep, $changed);

            if ($changed) {
                DB::table('respuestas')
                    ->where('id', $r->id)
                    ->update(['datos_json' => json_encode($datos, JSON_UNESCAPED_UNICODE)]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Recorre recursivamente el array de datos y reemplaza cualquier valor
     * que coincida con el ID o nombre del centro a remover.
     */
    private function replaceInArray(
        array $data,
        string $idRemove,
        string $nameRemove,
        string $idKeep,
        string $nameKeep,
        bool &$changed
    ): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->replaceInArray($value, $idRemove, $nameRemove, $idKeep, $nameKeep, $changed);
            } elseif (is_string($value)) {
                if ($value === $idRemove || $value === $nameRemove) {
                    // Conservar el mismo tipo: si era el ID como string → nuevo ID como string
                    $data[$key] = ($value === $idRemove) ? $idKeep : $nameKeep;
                    $changed    = true;
                }
            } elseif (is_int($value) || is_float($value)) {
                if ((string) $value === $idRemove) {
                    $data[$key] = (int) $idKeep;
                    $changed    = true;
                }
            }
        }
        return $data;
    }

    private function confirmAction(object $keep, object $remove, int $total): bool
    {
        $this->line('┌─────────────────────────────────────────────────────┐');
        $this->line("│  Se modificarán <fg=cyan>{$total}</> registros en producción.        │");
        $this->line('│  Esta operación es IRREVERSIBLE sin un backup.       │');
        $this->line('└─────────────────────────────────────────────────────┘');
        $this->newLine();

        return $this->confirm(
            "¿Confirmas fusionar \"{$remove->nombre}\" → \"{$keep->nombre}\"?",
            false
        );
    }

    private function showAvailableCentros(): void
    {
        $this->newLine();
        $this->line('Centros de costo disponibles en la base de datos:');
        $centros = DB::table('centros_costo')->orderBy('nombre')->get(['id', 'nombre', 'activo']);
        $this->table(
            ['ID', 'Nombre', 'Activo'],
            $centros->map(fn($c) => [$c->id, $c->nombre, $c->activo ? 'Sí' : 'No'])->toArray()
        );
    }
}
