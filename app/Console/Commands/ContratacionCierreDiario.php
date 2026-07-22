<?php

namespace App\Console\Commands;

use App\Mail\ContratacionCierreDiarioMail;
use App\Models\Configuracion;
use App\Models\MailLog;
use App\Models\PostulanteContratacion;
use App\Services\OneDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ContratacionCierreDiario extends Command
{
    protected $signature = 'contratacion:cierre-diario
        {--date= : Fecha a reportar en formato YYYY-MM-DD}
        {--to=* : Destinatario adicional o reemplazo temporal}
        {--skip-empty : No enviar correo si no hubo postulantes}
        {--force : Enviar aunque ya exista un cierre enviado para la fecha y destinatario}';

    protected $description = 'Envía el cierre diario de postulantes RRHH con enlaces a documentos en SharePoint.';

    private const DEFAULT_RECIPIENTS = 'mmejias@saep.cl, bmachero@saep.cl';

    public function handle(OneDriveService $oneDrive): int
    {
        $fecha = $this->resolveDate();
        if (!$fecha) {
            return self::FAILURE;
        }

        $postulantes = PostulanteContratacion::query()
            ->whereBetween('created_at', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->orderBy('created_at')
            ->get();

        if ($postulantes->isEmpty() && $this->option('skip-empty')) {
            $this->info('No hubo postulantes para la fecha indicada. Correo omitido.');
            return self::SUCCESS;
        }

        $destinatarios = $this->resolveRecipients();
        if (empty($destinatarios)) {
            $this->error('No hay destinatarios validos configurados para el cierre diario.');
            return self::FAILURE;
        }

        $filas = $this->buildRows($postulantes, $oneDrive);
        $resumen = [
            'total' => $postulantes->count(),
            'documentos_completos' => $postulantes->filter(fn ($p) => $p->documentosCompletos())->count(),
            'documentos_pendientes' => $postulantes->reject(fn ($p) => $p->documentosCompletos())->count(),
            'pendiente' => $postulantes->where('estado', 'pendiente')->count(),
            'en_revision' => $postulantes->where('estado', 'en_revision')->count(),
            'aprobado' => $postulantes->where('estado', 'aprobado')->count(),
            'rechazado' => $postulantes->where('estado', 'rechazado')->count(),
        ];

        $sent = 0;
        foreach ($destinatarios as $email) {
            if (!$this->option('force') && $this->alreadySent($email, $fecha)) {
                $this->line("Cierre ya enviado a {$email} para {$fecha->format('Y-m-d')}. Usa --force para reenviar.");
                continue;
            }

            try {
                Mail::to($email)->send(new ContratacionCierreDiarioMail($fecha, $postulantes, $filas, $resumen));
                $sent++;
            } catch (\Throwable $e) {
                if ($this->canUseMailLog()) {
                    MailLog::recordFailed(
                        $email,
                        'Cierre diario postulaciones RRHH - ' . $fecha->format('d/m/Y'),
                        $e->getMessage(),
                        'ContratacionCierreDiarioMail'
                    );
                }

                Log::error('Contratacion cierre diario: fallo envio', [
                    'email' => $email,
                    'fecha' => $fecha->toDateString(),
                    'error' => $e->getMessage(),
                ]);
                $this->error("No se pudo enviar a {$email}: {$e->getMessage()}");
            }
        }

        $this->info("Cierre diario generado: {$postulantes->count()} postulante(s), {$sent} correo(s) enviado(s).");

        return self::SUCCESS;
    }

    private function resolveDate(): ?Carbon
    {
        $timezone = config('app.timezone', 'America/Santiago');
        $date = $this->option('date');

        try {
            return $date
                ? Carbon::createFromFormat('Y-m-d', (string) $date, $timezone)->startOfDay()
                : now($timezone)->startOfDay();
        } catch (\Throwable) {
            $this->error('La fecha debe venir en formato YYYY-MM-DD.');
            return null;
        }
    }

    private function resolveRecipients(): array
    {
        $fromOption = array_filter(array_map('trim', (array) $this->option('to')));
        $raw = !empty($fromOption)
            ? implode(',', $fromOption)
            : Configuracion::get('contratacion_cierre_diario_emails', self::DEFAULT_RECIPIENTS);

        return collect(explode(',', (string) $raw))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function buildRows($postulantes, OneDriveService $oneDrive): array
    {
        $site = config('services.microsoft_graph.contratacion_site', 'RRH');
        $folder = trim(config('services.microsoft_graph.contratacion_folder', 'Postulantes Documents'), '/');
        $canResolveSharePoint = $oneDrive->isConfigured();

        return $postulantes->map(function (PostulanteContratacion $postulante) use ($oneDrive, $site, $folder, $canResolveSharePoint) {
            $sharepointFolder = "{$folder}/{$postulante->rut} - {$postulante->nombre}";
            $sharepointUrl = null;

            if ($canResolveSharePoint) {
                $sharepointUrl = $oneDrive->getItemWebUrlForSite($site, $sharepointFolder);

                if (!$sharepointUrl) {
                    $sharepointUrl = $oneDrive->getItemWebUrlForSite(
                        $site,
                        "{$sharepointFolder}/{$this->fichaFilename($postulante)}"
                    );
                }
            }

            $faltantes = $postulante->documentosFaltantes();

            return [
                'folio' => $postulante->folio,
                'nombre' => $postulante->nombre,
                'rut' => $postulante->rut,
                'email' => $postulante->email,
                'estado' => $postulante->estado_label,
                'estado_color' => $postulante->estado_color,
                'hora' => optional($postulante->created_at)->format('H:i'),
                'documentos_completos' => $postulante->documentosCompletos(),
                'documentos_recibidos' => count($postulante->documentosSubidos()),
                'documentos_faltantes' => count($faltantes),
                'faltantes_labels' => $this->missingDocumentLabels($faltantes),
                'sharepoint_path' => $sharepointFolder,
                'sharepoint_url' => $sharepointUrl,
                'panel_url' => route('contratacion.show', $postulante),
            ];
        })->all();
    }

    private function fichaFilename(PostulanteContratacion $postulante): string
    {
        $seq = (int) substr(strrchr($postulante->folio, '-'), 1);
        $fichaNum = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        return "{$postulante->rut} - FICHA {$fichaNum} - {$postulante->nombre}.pdf";
    }

    private function missingDocumentLabels(array $faltantes): string
    {
        if (empty($faltantes)) {
            return 'Sin pendientes';
        }

        $labels = [
            'carnet_frontal' => 'Carnet frontal',
            'carnet_reverso' => 'Carnet reverso',
            'certificado_afp' => 'Certificado AFP',
            'certificado_fonasa' => 'Certificado FONASA',
        ];

        return collect($faltantes)
            ->map(fn ($campo) => $labels[$campo] ?? $campo)
            ->implode(', ');
    }

    private function alreadySent(string $email, Carbon $fecha): bool
    {
        if (!$this->canUseMailLog()) {
            return false;
        }

        return MailLog::query()
            ->where('mailable', 'ContratacionCierreDiarioMail')
            ->where('to_email', $email)
            ->where('status', 'sent')
            ->where('subject', 'Cierre diario postulaciones RRHH - ' . $fecha->format('d/m/Y'))
            ->exists();
    }

    private function canUseMailLog(): bool
    {
        return Schema::hasTable('mail_logs');
    }
}
