<?php

namespace App\Listeners;

use App\Models\MailLog;
use App\Services\MailAutomationService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class LogMailSent
{
    public function handle(MessageSent $event): void
    {
        try {
            $original = $event->sent->getOriginalMessage();

            // Destinatarios (puede haber múltiples, tomamos el primero)
            $to     = $original->getTo();
            $toEmail = !empty($to) ? $to[0]->getAddress() : 'desconocido';
            $toName  = !empty($to) ? ($to[0]->getName() ?: null) : null;

            // Obtener el nombre corto del Mailable desde la clave que Laravel inyecta automáticamente
            $mailableClass = $event->data['__laravel_mailable'] ?? null;
            $customKey = $event->data[MailAutomationService::CUSTOM_MAIL_KEY] ?? null;
            $mailable = $mailableClass ? class_basename($mailableClass) : ($customKey ? class_basename($customKey) : null);

            MailLog::create([
                'mailable'  => $mailable,
                'subject'   => $original->getSubject(),
                'to_email'  => $toEmail,
                'to_name'   => $toName,
                'status'    => 'sent',
                'body_html' => $original->getHtmlBody(),
                'sent_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Nunca interrumpir el flujo por un error de logging
            Log::debug('MailLog listener error: ' . $e->getMessage());
        }
    }
}
