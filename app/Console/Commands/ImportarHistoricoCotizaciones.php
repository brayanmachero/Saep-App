<?php

namespace App\Console\Commands;

use App\Modules\Comercial\Models\Cotizacion;
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

            if (! $this->option('aplicar')) {
                continue;
            }

            foreach ($records as $record) {
                $alreadyExists = Cotizacion::withTrashed()->where('numero', $record['numero'])->exists();
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
