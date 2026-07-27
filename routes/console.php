<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-refresh Kizeo cache every 4 hours
Schedule::command('kizeo:cache-warm')->everyFourHours()->withoutOverlapping();

// Actualizar el dashboard de Observaciones de Conducta CCU desde Kizeo.
Schedule::command('kizeo:sync-observaciones-ccu')->everyThirtyMinutes()->withoutOverlapping();

// Actualizar el dashboard de PDR Inspección Preventiva desde Kizeo.
Schedule::command('kizeo:sync-inspecciones-preventivas')->everyThirtyMinutes()->withoutOverlapping();

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

// Talana: sync completo de personas + contratos + marcas del mes actual (diario a las 06:00 AM)
Schedule::command('talana:sync-db')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->skip(fn() => ! config('services.talana.token'));

// Talana: mantener ausencias aprobadas vigentes para no reportar como falta
// a personas con vacaciones, licencia o permiso registrado.
Schedule::command('talana:sync-rrhh --solo-ausencias --meses=3')
    ->dailyAt('05:20')
    ->withoutOverlapping()
    ->skip(fn() => ! config('services.talana.token'));

// Talana: contratos próximos a vencer (lunes, miércoles y viernes a las 07:30 AM)
Schedule::command('talana:alertas-contratos --dias=' . config('services.talana.alerta_dias', 7))
    ->cron('30 7 * * 1,3,5')
    ->withoutOverlapping()
    ->skip(fn() => ! config('services.talana.alerta_email'));

// Talana: contratos ya vencidos con trabajadores aún activos (diario lunes–viernes a las 07:45 AM)
Schedule::command('talana:alertas-vencidos-activos')
    ->cron('45 7 * * 1-5')
    ->withoutOverlapping()
    ->skip(fn() => ! config('services.talana.alerta_email'));

// Talana: sync asignación persona↔turno + jornada calculada (diario 06:30 AM — tras sync-db de 06:00)
// Permite al reporte de asistencia distinguir días de descanso de ausencias reales (workingDay flag)
Schedule::command('talana:sync-turnos --dias=30')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->skip(fn() => ! config('services.talana.token'));

// Talana: reporte diario de asistencia (lunes–sábado a las 08:15 AM — después del sync-db de las 06:00)
// Detecta: marcación incompleta, personal sin marcación y probables nuevos sin enrolar/turno
Schedule::command('talana:reporte-asistencia')
    ->cron('15 8 * * 1-6')
    ->withoutOverlapping()
    ->skip(fn() => ! config('services.talana.alerta_email') || ! config('services.talana.token'));

// RRHH: cierre diario de postulantes ingresados en el portal (17:00 Chile)
Schedule::command('contratacion:cierre-diario')->dailyAt('17:00')->timezone('America/Santiago')->withoutOverlapping();
