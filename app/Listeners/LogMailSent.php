<?php

namespace App\Listeners;

use App\Models\MailLog;
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

            // Intentar obtener el nombre corto de la clase Mailable
            $mailable = null;
            foreach ($event->data as $value) {
                if (is_object($value) && $value instanceof \Illuminate\Mail\Mailable) {
                    $mailable = class_basename($value);
                    break;
                }
            }

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
