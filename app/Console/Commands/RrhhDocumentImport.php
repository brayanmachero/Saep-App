<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RrhhDocumentImport extends Command
{
    protected $signature = 'rrhh:preparar-pdfs {lote} {directorio} {--importar : Importar los archivos del manifiesto}';

    protected $description = 'Genera un manifiesto CSV por contrato y documento o importa un lote de PDFs revisados.';

    public function handle(): int
    {
        $dir = realpath($this->argument('directorio'));
        if (! $dir || ! is_dir($dir)) {
            $this->error('Indica una carpeta privada existente.');

            return self::FAILURE;
        }
        $manifest = $dir.DIRECTORY_SEPARATOR.'manifiesto.csv';
        $saved = [];
        $disk = Storage::disk('local');
        try {
            DB::transaction(function () use ($dir, $manifest, &$saved, $disk) {
                $id = $this->argument('lote');
                $batch = DB::table('rrhh_document_batches')->where('id', $id)->lockForUpdate()->first();
                if (! $batch || $batch->estado !== 'BORRADOR') {
                    throw new RuntimeException('Se requiere un lote en borrador.');
                }
                $slots = [];
                foreach (DB::table('rrhh_document_batch_items')->where('batch_id', $id)->where('excluido', false)->get() as $item) {
                    if (json_decode($item->observaciones, true)) {
                        throw new RuntimeException('Resuelve las observaciones antes de importar.');
                    }
                    $contract = json_decode($item->snapshot, true)['talana_id'];
                    foreach (json_decode($batch->battery_snapshot, true)['documentos'] as $i => $name) {
                        $slots["contrato-{$contract}-doc-".($i + 1).'.pdf'] = [$item->id, $i, $name];
                    }
                }
                if (! $slots) {
                    throw new RuntimeException('No hay contratos incluidos.');
                }
                $header = ['lote', 'archivo', 'documento', 'categoria_talana'];
                if (! $this->option('importar')) {
                    $f = @fopen($manifest, 'xb');
                    if (! $f) {
                        throw new RuntimeException('El manifiesto ya existe o la carpeta no permite escritura.');
                    }
                    try {
                        fputcsv($f, $header);
                        foreach ($slots as $key => $slot) {
                            fputcsv($f, [$id, $key, $slot[2], '']);
                        }
                    } finally {
                        fclose($f);
                    }

                    return;
                }
                $f = @fopen($manifest, 'rb');
                if (! $f) {
                    throw new RuntimeException('Falta manifiesto.csv.');
                }
                try {
                    if (fgetcsv($f) !== $header) {
                        throw new RuntimeException('Encabezado incorrecto.');
                    }
                    while (($row = fgetcsv($f)) !== false) {
                        [$lote,$key,$name,$category] = array_pad($row, 4, null);
                        if (count($row) !== 4 || $lote !== $id || ! isset($slots[$key]) || $slots[$key][2] !== $name || ! filled($category) || mb_strlen($category) > 120) {
                            throw new RuntimeException('Fila incorrecta, duplicada o sin categoria.');
                        }
                        [$item,$i] = $slots[$key];
                        unset($slots[$key]);
                        $path = $dir.DIRECTORY_SEPARATOR.$key;
                        if (! is_file($path) || is_link($path) || filesize($path) > 10485760 || ! str_starts_with((string) file_get_contents($path, false, null, 0, 5), '%PDF-') || (new \finfo(FILEINFO_MIME_TYPE))->file($path) !== 'application/pdf') {
                            throw new RuntimeException("PDF ausente o invalido: {$key}.");
                        }
                        $pdf = file_get_contents($path);
                        $hash = hash('sha256', $pdf);
                        $category = trim($category);
                        $old = DB::table('rrhh_document_deliveries')->where(['item_id' => $item, 'position' => $i])->first();
                        if ($old) {
                            if ($old->sha256 !== $hash || $old->category !== $category) {
                                throw new RuntimeException("El archivo {$key} difiere del PDF preparado. Revisar en el modulo.");
                            }

                            continue;
                        }
                        $dest = 'rrhh/documentos/'.Str::uuid().'.pdf';
                        if (! $disk->put($dest, $pdf)) {
                            throw new RuntimeException('Fallo al guardar el PDF.');
                        }
                        $saved[] = $dest;
                        DB::table('rrhh_document_deliveries')->insert(['item_id' => $item, 'position' => $i, 'category' => $category, 'path' => $dest, 'sha256' => $hash, 'prepared_by' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    }
                } finally {
                    fclose($f);
                }
                if ($slots) {
                    throw new RuntimeException('Faltan filas del lote en el manifiesto.');
                }
                if ($saved) {
                    DB::table('rrhh_document_batch_events')->insert(['batch_id' => $id, 'actor_id' => 0, 'accion' => 'Importar PDFs', 'detalle' => json_encode(['motivo' => 'Carga desde consola: '.count($saved).' PDF']), 'created_at' => now()]);
                }
            });
        } catch (Throwable $e) {
            if ($saved) {
                $disk->delete($saved);
            }
            $this->error($e instanceof RuntimeException && ! $e instanceof \PDOException ? $e->getMessage() : 'No se pudo completar la preparacion.');

            return self::FAILURE;
        }
        $this->info($this->option('importar') ? 'Lote preparado. PDFs nuevos: '.count($saved) : 'Manifiesto creado. Completa la categoria y coloca los PDFs junto al CSV.');

        return self::SUCCESS;
    }
}
