<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-refresh Kizeo cache every 4 hours
Schedule::command('kizeo:cache-warm')->everyFourHours()->withoutOverlapping();

// Sincronizar seguimiento de charlas desde Kizeo (cada 6 horas)
Schedule::command('kizeo:sync-charla-tracking')->everySixHours()->withoutOverlapping();

// Reporte semanal de cumplimiento de charlas (lunes 08:00 AM)
Schedule::command('kizeo:charla-weekly-report --sync')->weeklyOn(1, '08:00')->withoutOverlapping();

// SST: enviar recordatorios de actividades próximas a vencer / vencidas (cada día a las 8:00 AM)
Schedule::command('sst:enviar-recordatorios')->dailyAt('08:00')->withoutOverlapping();

// Sincronizar Google Sheets → MySQL cada hora (Tarjeta STOP CCU)
Schedule::command('stop:sync-sheets --force')->hourly()->withoutOverlapping();

// Reporte semanal de Tarjeta STOP CCU (lunes 08:30 AM — mes en curso, filtrado por empresa)
Schedule::command('stop:weekly-report --frecuencia=semanal')->weeklyOn(1, '08:30')->withoutOverlapping();

// Reporte mensual de Tarjeta STOP CCU (día 1 de cada mes a las 09:00 AM — mes anterior, filtrado por empresa)
Schedule::command('stop:weekly-report --frecuencia=mensual')->monthlyOn(1, '09:00')->withoutOverlapping();

// Kanban: alertas de vencimiento de tareas (cada día a las 08:15 AM)
Schedule::command('kanban:alertas-vencimiento')->dailyAt('08:15')->withoutOverlapping();

// Kanban: crear instancias de tareas recurrentes (cada día a las 07:00 AM)
Schedule::command('kanban:tareas-recurrentes')->dailyAt('07:00')->withoutOverlapping();
