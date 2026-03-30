<?php

namespace App\Console\Commands;

use App\Mail\SstActividadAlertaMail;
use App\Models\Configuracion;
use App\Models\ProgramaSst;
use App\Models\SstActividad;
use App\Models\SstNotificacionLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SstEnviarRecordatorios extends Command
{
    protected $signature = 'sst:enviar-recordatorios';
    protected $description = 'Envía recordatorios SST según periodicidad a responsable, jefe y superadmins';

    public function handle(): int
    {
        // Verificar si las notificaciones SST están activas
        if (in_array(Configuracion::get('sst_notif_activa', 'true'), ['false', '0'])) {
            $this->info('Notificaciones SST desactivadas en configuración.');
            return self::SUCCESS;
        }

        $mesActual   = (int) now()->format('n');
        $mesAnterior = $mesActual === 1 ? 12 : $mesActual - 1;
        $hoy         = now()->toDateString();
        $enviados    = 0;

        // Config values
        $diasAntesVencer  = (int) Configuracion::get('sst_notif_dias_antes_vencer', '7');
        $frecuenciaVencida = (int) Configuracion::get('sst_notif_frecuencia_vencida', '3');
        $maxDiasVencida   = (int) Configuracion::get('sst_notif_max_dias_vencida', '30');
        $notifRecordatorio = !in_array(Configuracion::get('sst_notif_recordatorio', 'true'), ['false', '0']);
        $notifSeguimiento  = !in_array(Configuracion::get('sst_notif_seguimiento', 'true'), ['false', '0']);
        $notifVencimiento  = !in_array(Configuracion::get('sst_notif_vencimiento', 'true'), ['false', '0']);
        $notifVencida      = !in_array(Configuracion::get('sst_notif_vencida', 'true'), ['false', '0']);

        // Pre-cargar superadmins (se reusan en cada envío)
        $superAdminEmails = User::whereHas('rol', fn ($q) => $q->where('codigo', 'SUPER_ADMIN'))
            ->where('activo', true)
            ->pluck('email')
            ->filter();

        // Solo programas activos
        $programas = ProgramaSst::where('estado', 'ACTIVO')
            ->with(['responsable', 'categorias.actividades' => function ($q) {
                $q->whereNotIn('estado', ['COMPLETADA', 'CANCELADA'])
                  ->with(['responsableUser', 'seguimiento', 'notificaciones']);
            }])
            ->get();

        foreach ($programas as $programa) {
            $jefeEmail = $programa->responsable?->email;

            foreach ($programa->categorias as $categoria) {
                foreach ($categoria->actividades as $actividad) {
                    // ── 1) RECORDATORIO POR PERIODICIDAD ──
                    if ($notifRecordatorio && $actividad->periodicidad && $actividad->debeRecordarHoy($mesActual)) {
                        if (!$this->yaEnviadoHoy($actividad->id, 'recordatorio', $mesActual)) {
                            $this->enviarAlerta($actividad, 'recordatorio', $mesActual, $jefeEmail, $superAdminEmails);
                            $enviados++;
                        }
                    }

                    // ── 2) SEGUIMIENTO PENDIENTE DEL MES ANTERIOR ──
                    if ($notifSeguimiento && in_array((int) now()->format('j'), [1, 2])) {
                        $segAnterior = $actividad->seguimiento->firstWhere('mes', $mesAnterior);
                        if ($segAnterior && $segAnterior->programado && !$segAnterior->realizado) {
                            if (!$this->yaEnviadoHoy($actividad->id, 'seguimiento_pendiente', $mesAnterior)) {
                                $this->enviarAlerta($actividad, 'seguimiento_pendiente', $mesAnterior, $jefeEmail, $superAdminEmails);
                                $enviados++;
                            }
                        }
                    }

                    // ── 3) PRÓXIMA A VENCER ──
                    if ($notifVencimiento
                        && $actividad->fecha_fin
                        && $actividad->fecha_fin->isFuture()
                        && $actividad->fecha_fin->diffInDays(now()) <= $diasAntesVencer
                    ) {
                        if (!$this->yaEnviadoHoy($actividad->id, 'vencimiento')) {
                            $this->enviarAlerta($actividad, 'vencimiento', $mesActual, $jefeEmail, $superAdminEmails);
                            $enviados++;
                        }
                    }

                    // ── 4) VENCIDA ──
                    if ($notifVencida
                        && $actividad->fecha_fin
                        && $actividad->fecha_fin->isPast()
                        && $actividad->fecha_fin->diffInDays(now()) <= $maxDiasVencida
                    ) {
                        $ultimoEnvio = SstNotificacionLog::where('actividad_id', $actividad->id)
                            ->where('tipo', 'vencida')
                            ->where('rol_destinatario', 'responsable')
                            ->latest()
                            ->first();

                        $enviar = !$ultimoEnvio || $ultimoEnvio->created_at->diffInDays(now()) >= $frecuenciaVencida;
                        if ($enviar) {
                            $this->enviarAlerta($actividad, 'vencida', $mesActual, $jefeEmail, $superAdminEmails);
                            $enviados++;
                        }
                    }
                }
            }
        }

        $this->info("Recordatorios enviados: {$enviados}");
        Log::info("SST Recordatorios: {$enviados} emails enviados");

        return self::SUCCESS;
    }

    /**
     * Envía la alerta al responsable con CC al jefe del programa y superadmins.
     */
    private function enviarAlerta(
        SstActividad $actividad,
        string $tipo,
        ?int $mes,
        ?string $jefeEmail,
        \Illuminate\Support\Collection $superAdminEmails
    ): void {
        $responsable = $actividad->responsableUser;
        $responsableEmail = $responsable?->email;

        // Construir CC: jefe + superadmins (sin duplicar al responsable)
        $ccEmails = collect();
        if ($jefeEmail) {
            $ccEmails->push($jefeEmail);
        }
        $ccEmails = $ccEmails->merge($superAdminEmails)->unique()->reject(fn ($e) => $e === $responsableEmail);

        // Agregar CC adicionales desde configuración
        $ccAdicional = Configuracion::get('sst_notif_cc_adicional', '');
        if ($ccAdicional) {
            $extras = collect(preg_split('/[;,]+/', $ccAdicional))
                ->map(fn($e) => trim($e))
                ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->reject(fn($e) => $e === $responsableEmail);
            $ccEmails = $ccEmails->merge($extras)->unique();
        }

        // Si no hay responsable, enviar al primer CC como destinatario principal
        $toEmail = $responsableEmail ?: $ccEmails->shift();
        if (!$toEmail) {
            return;
        }

        try {
            $mail = Mail::to($toEmail);
            if ($ccEmails->isNotEmpty()) {
                $mail->cc($ccEmails->all());
            }
            $mail->send(new SstActividadAlertaMail($actividad, $tipo));

            // Registrar en log para cada destinatario
            $allRecipients = collect([$toEmail])->merge($ccEmails);
            foreach ($allRecipients as $email) {
                $rolDest = match (true) {
                    $email === $responsableEmail => 'responsable',
                    $superAdminEmails->contains($email) => 'superadmin',
                    default => 'jefe',
                };
                SstNotificacionLog::create([
                    'actividad_id'     => $actividad->id,
                    'user_id'          => User::where('email', $email)->value('id'),
                    'email'            => $email,
                    'tipo'             => $tipo,
                    'mes'              => $mes,
                    'rol_destinatario' => $rolDest,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("SST Recordatorio ({$tipo}): error actividad #{$actividad->id}: {$e->getMessage()}");
        }
    }

    /**
     * Verifica si ya se envió este tipo de notificación hoy para esta actividad.
     */
    private function yaEnviadoHoy(int $actividadId, string $tipo, ?int $mes = null): bool
    {
        $query = SstNotificacionLog::where('actividad_id', $actividadId)
            ->where('tipo', $tipo)
            ->where('rol_destinatario', 'responsable')
            ->whereDate('created_at', now()->toDateString());

        if ($mes !== null) {
            $query->where('mes', $mes);
        }

        return $query->exists();
    }
}
