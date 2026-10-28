<?php

namespace App\Jobs;

use App\Mail\VehiculoActaMail;
use App\Models\VehiculoActa;
use App\Services\ReservaVehiculoCalendarService;
use App\Services\ReservaVehiculoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class NotificarVehiculoActa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public int $actaId) {}

    public function handle(): void
    {
        Cache::lock('vehiculo-acta-notificar:'.$this->actaId, 180)->block(3, function () {
            $acta = VehiculoActa::findOrFail($this->actaId);
            if ($acta->estado !== 'FIRMADA') return;
            if (config('vehiculos_actas.calendar_enabled')) app(ReservaVehiculoCalendarService::class)->sincronizar($acta->reserva);
            if (!config('vehiculos_actas.mail_enabled') || $acta->email_enviado_at) return;
            // A worker killed after send requires checking the provider before retrying.
            if ($acta->email_estado === 'ENVIANDO') return;
            $acta->update(['email_estado' => 'ENVIANDO', 'email_error' => null]);
            try {
                $to = $acta->datos['conductor_email'];
                $cc = app(ReservaVehiculoService::class)->destinatariosGestionBodega()->pluck('email')
                    ->push($acta->datos['encargado']['email'])->filter()->map(fn ($email) => strtolower($email))->unique()->reject(fn ($email) => $email === strtolower($to))->values()->all();
                Mail::to($to)->cc($cc)->send(new VehiculoActaMail($acta));
                $acta->update(['email_estado' => 'ENVIADO', 'email_enviado_at' => now()]);
            } catch (\Throwable $e) {
                report($e);
                $acta->update(['email_estado' => 'ERROR', 'email_error' => 'No se confirmó el envío. Revisa el proveedor de correo antes de reintentar.']);
            }
        });
    }
}
