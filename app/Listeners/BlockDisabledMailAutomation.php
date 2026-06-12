<?php

namespace App\Listeners;

use App\Models\MailLog;
use App\Services\MailAutomationService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

class BlockDisabledMailAutomation
{
    public function __construct(private readonly MailAutomationService $automation)
    {
    }

    public function handle(MessageSending $event): ?bool
    {
        try {
            $key = $this->automation->resolveKeyFromData($event->data);

            if ($this->automation->isEnabledFor($key)) {
                return null;
            }

            $message = $event->message;
            $to = $message->getTo();
            $toEmail = ! empty($to) ? $to[0]->getAddress() : 'desconocido';
            $toName = ! empty($to) ? ($to[0]->getName() ?: null) : null;
            $html = $message->getHtmlBody();

            if (! $html && $message->getTextBody()) {
                $html = '<pre style="white-space:pre-wrap;font-family:Arial,sans-serif;">'
                    .htmlspecialchars($message->getTextBody(), ENT_QUOTES, 'UTF-8')
                    .'</pre>';
            }

            MailLog::recordBlocked(
                toEmail: $toEmail,
                subject: $message->getSubject() ?: '(sin asunto)',
                reason: $this->automation->disabledReason($key),
                mailable: $key,
                toName: $toName,
                bodyHtml: $html
            );

            return false;
        } catch (\Throwable $e) {
            Log::warning('Mail automation guard error: '.$e->getMessage());

            return null;
        }
    }
}
