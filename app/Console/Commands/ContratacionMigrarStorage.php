<?php

namespace App\Console\Commands;

use App\Models\PostulanteContratacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ContratacionMigrarStorage extends Command
{
    protected $signature = 'contratacion:migrar-storage
        {--from= : Ruta absoluta o relativa al disco public origen (default: storage/app/public/contratacion)}
        {--dry-run : No copia archivos, solo muestra lo que haría}
        {--delete-source : Elimina el origen tras copiar (USAR CON PRECAUCIÓN)}';

    protected $description = 'Migra archivos de postulantes desde el disco public legacy al disco private (storage/app/private/contratacion).';

    public function handle(): int
    {
        $from = $this->option('from') ?: storage_path('app/public/contratacion');
        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');

        if (!File::isDirectory($from)) {
            $this->error("Origen no existe: {$from}");
            return self::FAILURE;
        }

        $this->info("Origen: {$from}");
        $this->info('Destino: storage/app/private/contratacion (disco local)');
        $this->newLine();

        $campos = [
            'cedula_frontal', 'cedula_dorso', 'certificado_residencia', 'certificado_antecedentes',
            'certificado_afp', 'certificado_salud', 'certificado_estudios', 'curriculum',
            'licencia_municipal_frontal', 'licencia_municipal_dorso',
            'licencia_a3_a4_a5_frontal', 'licencia_a3_a4_a5_dorso',
            'hoja_vida_conductor',
        ];

        $postulantes = PostulanteContratacion::query()->get();
        $this->info("Postulantes en DB: {$postulantes->count()}");

        $copiados = 0;
        $faltantes = 0;
        $yaPresentes = 0;

        foreach ($postulantes as $p) {
            foreach ($campos as $campo) {
                $rel = $p->{$campo};
                if (!$rel) continue;

                $origenAbs = $from . DIRECTORY_SEPARATOR . str_replace('contratacion/', '', $rel);
                if (!File::exists($origenAbs)) {
                    $origenAbs = $from . DIRECTORY_SEPARATOR . $rel;
                }

                if (!File::exists($origenAbs)) {
                    $this->warn("  [FALTA] {$p->folio} {$campo} -> {$rel}");
                    $faltantes++;
                    continue;
                }

                if (Storage::disk('local')->exists($rel)) {
                    $yaPresentes++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY] {$p->folio} {$campo} -> {$rel}");
                } else {
                    Storage::disk('local')->put($rel, File::get($origenAbs));
                    if ($deleteSource) {
                        File::delete($origenAbs);
                    }
                }
                $copiados++;
            }
        }

        $this->newLine();
        $this->info("Copiados: {$copiados}");
        $this->info("Ya presentes en destino: {$yaPresentes}");
        if ($faltantes > 0) {
            $this->warn("Referencias en DB sin archivo origen: {$faltantes}");
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: no se copió nada. Re-ejecuta sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }
}
