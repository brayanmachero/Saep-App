<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnviarCorreosEpp extends Command
{
    protected $signature = 'epp:enviar-correos';
    protected $description = 'Procesa notificaciones de solicitudes de EPP mediante Resend';

    public function handle(): int
    {
        $rows = DB::table('epp_correos as actual')->where('proximo_intento','<=',now())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('epp_correos as anterior')
                ->whereColumn('anterior.pedido_id','actual.pedido_id')->whereColumn('anterior.id','<','actual.id')
                ->whereNull('anterior.resend_id'))
            ->orderBy('id')->limit(40)->get();
        foreach ($rows as $row) {
            $query = DB::table('epp_correos')->where('id',$row->id);
            if (! (clone $query)->where('proximo_intento','<=',now())->update([
                'primer_intento' => $row->primer_intento ?? now(), 'proximo_intento' => now()->addMinutes(5),
            ])) {
                continue;
            }
            if ($row->primer_intento && now()->subHours(23)->greaterThan($row->primer_intento)) {
                $query->update(['proximo_intento'=>null,'error'=>'Revisar entrega en Resend antes de reintentar']);
                Log::error('EPP: correo requiere conciliación', ['id'=>$row->id]);
                continue;
            }
            try {
                $response = Http::withToken(config('services.resend.key'))->timeout(12)
                    ->withHeaders(['Idempotency-Key'=>$row->clave])
                    ->post('https://api.resend.com/emails', json_decode($row->mensaje,true))->throw();
                $id = $response->json('id');
                if (!$id) {
                    throw new \RuntimeException('Resend no devolvió identificador');
                }
                $query->update(['resend_id'=>$id,'proximo_intento'=>null,'error'=>null]);
            } catch (\Throwable $e) {
                $query->update(['error'=>class_basename($e).' '.$e->getCode()]);
                Log::warning('EPP: envío pendiente de reintento', ['id'=>$row->id,'error'=>class_basename($e),'codigo'=>$e->getCode()]);
            }
        }
        return self::SUCCESS;
    }
}
