<?php

namespace App\Console\Commands;

use App\Mail\InventarioCierreDiarioMail;
use App\Models\EntregaBodega;
use App\Services\EntregaBodegaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InventarioCierreDiario extends Command
{
    protected $signature = 'inventario:cierre-diario {--preview : Mostrar conteo sin enviar ni reservar envío}';
    protected $description = 'Avisa a Bodega solo de salidas del día todavía no descontadas.';

    public function handle(): int
    {
        $fecha = now('America/Santiago')->toDateString();
        $deliveries = EntregaBodega::query()
            ->whereIn('kizeo_form_id', EntregaBodegaSyncService::currentFormIds())
            ->where('flujo_inventario', 'SALIDA')
            ->whereDate('fecha_pedido', $fecha)
            ->where(fn ($q) => $q->whereNull('estado_fuente')->orWhereNotIn('estado_fuente', ['ELIMINADA_EN_KIZEO']))
            ->whereDoesntHave('inventarioAplicacion')->orderBy('id')->get();
        if ($deliveries->isEmpty() || $this->option('preview')) {
            $this->info($deliveries->count().' entregas del día pendientes. No se envió correo.');
            return self::SUCCESS;
        }

        $recipient = 'bodega@saep.cl';
        // A durable unique daily reservation prevents duplicate scheduler invocations.
        // An uncertain transport failure stays reserved for administrator review.
        if (! DB::table('inventario_cierre_envios')->insertOrIgnore([
            'fecha' => $fecha, 'destinatario' => $recipient, 'estado' => 'enviando',
            'created_at' => now(), 'updated_at' => now(),
        ])) {
            $this->info('Cierre ya enviado o reservado para revisión. No se duplica.');
            return self::SUCCESS;
        }
        try {
            Mail::to($recipient)->send(new InventarioCierreDiarioMail($fecha, $deliveries));
            DB::table('inventario_cierre_envios')->where('fecha', $fecha)->update([
                'estado' => 'enviado', 'enviado_en' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('inventario_cierre_envios')->where('fecha', $fecha)->update(['estado' => 'requiere_revision', 'updated_at' => now()]);
            report($e);
            $this->error('Falló el envío; revisar transporte antes de reintentar para evitar duplicados.');
            return self::FAILURE;
        }
        $this->info('Cierre enviado: '.$deliveries->count().' entregas pendientes.');
        return self::SUCCESS;
    }
}
