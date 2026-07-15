<?php

namespace App\Console\Commands;

use App\Mail\SstResumenActividadesMail;
use App\Models\Configuracion;
use App\Models\ProgramaSst;
use App\Models\SstActividad;
use App\Models\SstNotificacionLog;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SstEnviarRecordatorios extends Command
{
    protected $signature = 'sst:enviar-recordatorios';
    protected $description = 'Envía recordatorios SST según periodicidad a responsable, jefe y superadmins';

    private array $usuariosPorEmail = [];

    public function handle(): int
    {
        // Verificar si las notificaciones SST están activas
        if (in_array(Configuracion::get('sst_notif_activa', 'true'), ['false', '0'])) {
            $this->info('Notificaciones SST desactivadas en configuración.');
            return self::SUCCESS;
        }

        $mesActual   = (int) now()->format('n');
        $mesAnterior = $mesActual === 1 ? 12 : $mesActual - 1;
        $alertasPorEmail = collect();

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
                            $this->agregarAlerta($alertasPorEmail, $actividad, 'recordatorio', $mesActual, $jefeEmail, $superAdminEmails);
                        }
                    }

                    // ── 2) SEGUIMIENTO PENDIENTE DEL MES ANTERIOR ──
                    if ($notifSeguimiento && in_array((int) now()->format('j'), [1, 2])) {
                        $segAnterior = $actividad->seguimiento->firstWhere('mes', $mesAnterior);
                        if ($segAnterior && $segAnterior->programado && !$segAnterior->realizado) {
                            if (!$this->yaEnviadoHoy($actividad->id, 'seguimiento_pendiente', $mesAnterior)) {
                                $this->agregarAlerta($alertasPorEmail, $actividad, 'seguimiento_pendiente', $mesAnterior, $jefeEmail, $superAdminEmails);
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
                            $this->agregarAlerta($alertasPorEmail, $actividad, 'vencimiento', $mesActual, $jefeEmail, $superAdminEmails);
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
                            $this->agregarAlerta($alertasPorEmail, $actividad, 'vencida', $mesActual, $jefeEmail, $superAdminEmails);
                        }
                    }
                }
            }
        }

        $totalAlertas = $alertasPorEmail->count();
        $enviados = $this->enviarResumenes($alertasPorEmail);

        $this->info("Resumenes enviados: {$enviados}. Alertas consolidadas: {$totalAlertas}");
        Log::info("SST Recordatorios: {$enviados} resumenes enviados para {$totalAlertas} alertas");

        return self::SUCCESS;
    }

    /**
     * Agrega una alerta al resumen diario del responsable, jefe del programa y superadmins.
     */
    private function agregarAlerta(
        Collection $alertasPorEmail,
        SstActividad $actividad,
        string $tipo,
        ?int $mes,
        ?string $jefeEmail,
        Collection $superAdminEmails
    ): void {
        $actividad->loadMissing(['categoria.programa', 'responsableUser']);

        $responsable = $actividad->responsableUser;
        $responsableEmail = $responsable?->email;

        // Construir destinatarios: responsable + jefe + superadmins (sin duplicados).
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

        $programaId = $actividad->categoria->programa_id ?? 0;
        $dias = $actividad->fecha_fin
            ? (int) now()->startOfDay()->diffInDays($actividad->fecha_fin->copy()->startOfDay(), false)
            : null;

        foreach (collect([$toEmail])->merge($ccEmails)->filter()->unique() as $email) {
            $rolDest = match (true) {
                $email === $responsableEmail => 'responsable',
                $superAdminEmails->contains($email) => 'superadmin',
                default => 'jefe',
            };

            $alertasPorEmail->push([
                'email' => $email,
                'user' => $this->usuarioPorEmail($email),
                'actividad' => $actividad,
                'programa' => $actividad->categoria?->programa,
                'categoria' => $actividad->categoria,
                'tipo' => $tipo,
                'mes' => $mes,
                'dias' => $dias,
                'rol_destinatario' => $rolDest,
                'url' => $programaId ? route('carta-gantt.show', $programaId) : route('carta-gantt.index'),
            ]);
        }
    }

    /**
     * Envia un solo correo por destinatario y registra cada actividad notificada.
     */
    private function enviarResumenes(Collection $alertasPorEmail): int
    {
        $enviados = 0;

        $alertasPorEmail = $alertasPorEmail->unique(function (array $alerta) {
            return implode('|', [
                $alerta['email'],
                $alerta['actividad']->id,
                $alerta['tipo'],
                $alerta['mes'] ?? 'sin-mes',
            ]);
        })->values();

        foreach ($alertasPorEmail->groupBy('email') as $email => $items) {
            $items = $items->values();
            $usuario = $items->first()['user'] ?? $this->usuarioPorEmail($email);

            try {
                Mail::to($email)->send(new SstResumenActividadesMail(
                    email: $email,
                    items: $items,
                    nombre: $usuario?->nombre_completo ?: null
                ));

                if ($usuario) {
                    $primera = $items->first();
                    $usuario->notify(new AppNotification(
                        'Resumen Carta Gantt pendiente',
                        $items->count() . ' actividad(es) requieren revision o seguimiento.',
                        $items->contains(fn ($item) => in_array($item['tipo'], ['vencida', 'vencimiento'], true)) ? 'warning' : 'info',
                        $primera['url'] ?? route('carta-gantt.index')
                    ));
                }

                foreach ($items as $item) {
                    $this->registrarLogAlerta($item);
                }

                $enviados++;
            } catch (\Exception $e) {
                Log::warning("SST Resumen: error enviando a {$email}: {$e->getMessage()}");
            }
        }

        return $enviados;
    }

    private function registrarLogAlerta(array $item): void
    {
        SstNotificacionLog::create([
            'actividad_id'     => $item['actividad']->id,
            'user_id'          => $item['user']?->id,
            'email'            => $item['email'],
            'tipo'             => $item['tipo'],
            'mes'              => $item['mes'],
            'rol_destinatario' => $item['rol_destinatario'],
        ]);
    }

    private function usuarioPorEmail(string $email): ?User
    {
        if (!array_key_exists($email, $this->usuariosPorEmail)) {
            $this->usuariosPorEmail[$email] = User::where('email', $email)->first();
        }

        return $this->usuariosPorEmail[$email];
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
