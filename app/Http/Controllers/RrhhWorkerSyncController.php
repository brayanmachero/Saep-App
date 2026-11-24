<?php

namespace App\Http\Controllers;

use App\Jobs\RrhhWorkerSyncJob;
use App\Models\TalanaContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RrhhWorkerSyncController extends Controller
{
    public static function info(): array
    {
        $result = Cache::get('rrhh_worker_sync_status', ['state'=>'idle','message'=>'Actualizaciones programadas: 06:00, 12:35 y 19:35 (Chile).']);
        $pending = Cache::has('rrhh_worker_sync_reservation');
        if (!$pending && in_array($result['state'], ['queued','running'])) {
            $result['state']='error';
            $result['message']='No se confirmó el término de la actualización. Puedes volver a solicitarla.';
        }
        $result['busy']=$pending || (bool)Cache::get('talana_sync_running', false);
        $last = Cache::get('talana_contracts_last_success');
        $result['last_success']=$last ? \Carbon\Carbon::parse($last)->timezone('America/Santiago')->format('d/m/Y H:i') : null;
        $latest = TalanaContrato::max('synced_at');
        $result['latest_record']=$latest ? \Carbon\Carbon::parse($latest)->timezone('America/Santiago')->format('d/m/Y H:i') : null;
        return $result;
    }

    public function status() { return response()->json(self::info())->header('Cache-Control', 'no-store'); }

    public function start(Request $request)
    {
        if (!config('services.talana.token')) return response()->json(['message'=>'No está configurada la conexión al padrón de Talana.'],503);
        $lock = Cache::lock('rrhh_worker_sync_request', 10);
        if (!$lock->get()) return response()->json(['message'=>'Ya se está solicitando una actualización.'],409);
        try {
            if (self::info()['busy']) return response()->json(['message'=>'Hay una actualización pendiente o en curso.'],409);
            if (Cache::has('rrhh_worker_sync_cooldown')) return response()->json(['message'=>'Espera tres minutos entre actualizaciones manuales.'],429);
            $runId=(string)Str::uuid();
            Cache::put('rrhh_worker_sync_reservation',$runId,now()->addMinutes(20));
            Cache::put('rrhh_worker_sync_cooldown',true,now()->addMinutes(3));
            Cache::put('rrhh_worker_sync_status',['state'=>'queued','message'=>'Actualización en cola. Puedes seguir trabajando mientras se procesa.','actor'=>(int)$request->user()->id,'run_id'=>$runId,'at'=>now()->toIso8601String()],now()->addDays(7));
            try { RrhhWorkerSyncJob::dispatch((int)$request->user()->id,$runId); }
            catch (\Throwable $e) {
                Cache::forget('rrhh_worker_sync_reservation');
                Cache::put('rrhh_worker_sync_status',['state'=>'error','message'=>'No se pudo iniciar la actualización.'],now()->addDays(7));
                report($e);
                return response()->json(['message'=>'No se pudo iniciar la actualización.'],503);
            }
            return response()->json(self::info(),202);
        } finally { $lock->release(); }
    }
}
