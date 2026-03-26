<?php

namespace App\Console\Commands;

use App\Services\KizeoService;
use Illuminate\Console\Command;

class KizeoCacheWarm extends Command
{
    protected $signature = 'kizeo:cache-warm';
    protected $description = 'Pre-carga el caché de Kizeo Forms para acceso rápido al dashboard';

    public function handle(KizeoService $kizeo): int
    {
        $this->info('Calentando caché de Kizeo Forms...');

        // Current month range
        $startDate = date('Y-m-01');
        $endDate   = date('Y-m-t');

        try {
            $this->line('  → Formularios PDR...');
            $kizeo->getPdrForms();

            $this->line('  → Usuarios...');
            $kizeo->getUserDictionary();

            $this->line("  → Dashboard ({$startDate} a {$endDate})...");
            $kizeo->getDashboardData($startDate, $endDate, true);

            $this->line("  → Deep Analytics (todos los formularios)...");
            set_time_limit(300);
            $kizeo->getAllDeepData($startDate, $endDate, true, 15);

            $this->info('✓ Caché calentado exitosamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
