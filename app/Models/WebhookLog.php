<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'origen', 'form_id', 'data_id', 'tipo', 'estado',
        'resumen', 'archivo', 'sharepoint_path', 'email_enviado',
        'destinatarios', 'metadata', 'error_message', 'ip',
    ];

    protected $casts = [
        'email_enviado' => 'boolean',
        'destinatarios' => 'array',
        'metadata'      => 'array',
    ];

    /**
     * Registrar un webhook exitoso.
     */
    public static function logSuccess(array $attrs): static
    {
        return static::create(array_merge($attrs, ['estado' => 'success']));
    }

    /**
     * Registrar un webhook con error.
     */
    public static function logError(array $attrs): static
    {
        return static::create(array_merge($attrs, ['estado' => 'error']));
    }

    /**
     * Registrar un webhook ignorado.
     */
    public static function logIgnored(array $attrs): static
    {
        return static::create(array_merge($attrs, ['estado' => 'ignored']));
    }
}
