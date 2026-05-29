<?php

namespace App\Console\Commands;

use App\Http\Controllers\ContratacionController;
use App\Models\ContratacionSyncLog;
use App\Models\PostulanteContratacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class ContratacionSyncReintentar extends Command
{
    protected $signature = 'contratacion:sync-reintentar
                            {--postulante= : ID del postulante a reintentar (omite para reintentar TODOS los fallidos)}
                            {--dry-run : Solo mostrar qué se reintentaría sin ejecutar}';

    protected $description = 'Reintenta la subida a SharePoint de fichas de postulantes cuyo último sync falló';

    public function handle(): int
    {
        $controller = App::make(ContratacionController::class);

        if ($id = $this->option('postulante')) {
            $postulantes = PostulanteContratacion::where('id', $id)->get();
        } else {
            // Subselect: último log por postulante donde status = fallido
            $latestIds = ContratacionSyncLog::selectRaw('MAX(id) as id')
                ->groupBy('postulante_id')
                ->pluck('id');

            $postulanteIdsConFallo = ContratacionSyncLog::whereIn('id', $latestIds)
                ->where('status', ContratacionSyncLog::STATUS_FALLIDO)
                ->pluck('postulante_id');

            $postulantes = PostulanteContratacion::whereIn('id', $postulanteIdsConFallo)->get();
        }

        if ($postulantes->isEmpty()) {
            $this->info('No hay postulantes con sync fallido pendiente.');
            return self::SUCCESS;
        }

        $this->info("Postulantes a reintentar: {$postulantes->count()}");
        foreach ($postulantes as $p) {
            $this->line(" - [{$p->folio}] {$p->nombre} ({$p->rut})");
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: no se ejecutó nada.');
            return self::SUCCESS;
        }

        $ok = 0; $ko = 0;
        foreach ($postulantes as $p) {
            $this->line("Reintentando: {$p->folio} …");
            try {
                $controller->resincronizarSharePoint($p);
                $p->refresh();
                $ultimo = $p->ultimoSync()->first();
                if ($ultimo && $ultimo->status === ContratacionSyncLog::STATUS_EXITOSO) {
                    $this->info(" ✓ OK");
                    $ok++;
                } else {
                    $this->error(" ✗ FALLÓ: " . ($ultimo->error_mensaje ?? 'sin detalle'));
                    $ko++;
                }
            } catch (\Throwable $e) {
                $this->error(" ✗ EXCEPCIÓN: " . $e->getMessage());
                $ko++;
            }
        }

        $this->newLine();
        $this->info("Resultado: {$ok} OK, {$ko} fallidos");
        return $ko > 0 ? self::FAILURE : self::SUCCESS;
    }
}
