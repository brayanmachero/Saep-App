<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    protected $table = 'mail_logs';

    protected $fillable = [
        'mailable',
        'subject',
        'to_email',
        'to_name',
        'status',
        'error_message',
        'body_html',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // ── Helper: registrar un envío fallido manualmente ────────────
    public static function recordFailed(
        string $toEmail,
        string $subject,
        string $errorMessage,
        ?string $mailable = null
    ): void {
        try {
            static::create([
                'mailable'      => $mailable,
                'subject'       => $subject,
                'to_email'      => $toEmail,
                'status'        => 'failed',
                'error_message' => $errorMessage,
                'sent_at'       => now(),
            ]);
        } catch (\Throwable) {
            // No interrumpir el flujo si el log falla
        }
    }
}
