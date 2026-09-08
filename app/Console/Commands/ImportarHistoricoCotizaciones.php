<?php

namespace App\Console\Commands;

use App\Modules\Comercial\Services\ImportadorHistoricoCotizacionesService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ImportarHistoricoCotizaciones extends Command
{
    protected $signature = 'comercial:importar-historico
        {directorio : Directorio local con archivos XLSX históricos}
        {--aplicar : Persiste solamente los registros listos para importar}
        {--detalle : Muestra el detalle cronológico de las tarifas que pasarían el control}
        {--limite=0 : Cantidad máxima de archivos a revisar}';

    protected $description = 'Analiza e importa cotizaciones históricas como fotografías de origen, sin recalcularlas con reglas vigentes.';

    public function handle(ImportadorHistoricoCotizacionesService $importador): int
    {
        $directory = realpath((string) $this->argument('directorio'));
        if ($directory === false || ! is_dir($directory)) {
            $this->error('No se encontró el directorio indicado.');

            return self::FAILURE;
        }

        $files = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)))
            ->filter(fn (SplFileInfo $file) => $file->isFile() && Str::lower($file->getExtension()) === 'xlsx')
            ->sortBy(fn (SplFileInfo $file) => $file->getPathname())
            ->values();

        $limit = max(0, (int) $this->option('limite'));
        if ($limit > 0) {
            $files = $files->take($limit);
        }

        $summary = [
            'archivos' => 0,
            'registros_listos' => 0,
            'importados' => 0,
            'ya_existentes' => 0,
            'revision' => 0,
            'observados' => 0,
            'omitidos' => 0,
        ];
        $issues = [];
        $readyPreview = [];

        foreach ($files as $file) {
            $summary['archivos']++;
            $analysis = $importador->analizar($file->getPathname(), $directory);
            $status = $analysis['status'];

            if ($status !== 'listo') {
                $summary[match ($status) {
                    'observado' => 'observados',
                    'omitido' => 'omitidos',
                    default => 'revision',
                }]++;
                $issues[] = [
                    $analysis['source']['ruta_relativa'] ?? $file->getFilename(),
                    $status,
                    $analysis['reason'] ?? 'Sin detalle',
                ];
                continue;
            }

            /** @var array<int, array<string, mixed>> $records */
            $records = $analysis['records'];
            $summary['registros_listos'] += count($records);
            foreach ($records as $record) {
                $readyPreview[] = [
                    'fecha' => $record['fecha_cotizacion']->toDateString(),
                    'cliente' => $record['resumen_importacion']['cliente'],
                    'centro' => $record['resumen_importacion']['centro'],
                    'modalidad' => $record['resumen_importacion']['modalidad'],
                    'cargo' => $record['cargo'],
                    'precio' => number_format($record['totales']['precio_venta'], 0, ',', '.'),
                    'fecha_fuente' => $record['resumen_importacion']['fecha_fuente'],
                ];
            }

            if (! $this->option('aplicar')) {
                continue;
            }

            foreach ($records as $record) {
                $alreadyExists = $importador->existe($record);
                $importador->importar($record);
                $summary[$alreadyExists ? 'ya_existentes' : 'importados']++;
            }
        }

        $this->table(['Concepto', 'Cantidad'], [
            ['Archivos analizados', $summary['archivos']],
            ['Tarifas históricas listas', $summary['registros_listos']],
            ['Importadas', $summary['importados']],
            ['Ya existentes', $summary['ya_existentes']],
            ['Requieren homologación o lectura', $summary['revision']],
            ['Observadas por fórmulas', $summary['observados']],
            ['Omitidas por instrucción de origen', $summary['omitidos']],
        ]);

        if ($this->option('detalle') && $readyPreview !== []) {
            $readyPreview = collect($readyPreview)->sortBy(['fecha', 'cliente', 'centro', 'cargo'])->values()->all();
            $this->newLine();
            $this->table(
                ['Fecha', 'Cliente', 'Centro', 'Mod.', 'Cargo', 'Precio', 'Fuente fecha'],
                array_map(fn (array $item) => [
                    $item['fecha'],
                    $item['cliente'],
                    $item['centro'],
                    $item['modalidad'],
                    $item['cargo'],
                    '$'.$item['precio'],
                    $item['fecha_fuente'],
                ], array_slice($readyPreview, 0, 50)),
            );
            if (count($readyPreview) > 50) {
                $this->warn('Se muestran las primeras 50 de '.count($readyPreview).' tarifas listas.');
            }
        }

        if ($issues !== []) {
            $this->newLine();
            $this->table(['Archivo', 'Estado', 'Motivo'], array_slice($issues, 0, 30));
            if (count($issues) > 30) {
                $this->warn('Se muestran los primeros 30 archivos que requieren atención.');
            }
        }

        if (! $this->option('aplicar')) {
            $this->info('Simulación finalizada. No se guardaron cambios. Usa --aplicar sólo después de revisar este resultado.');
        }

        return self::SUCCESS;
    }
}
