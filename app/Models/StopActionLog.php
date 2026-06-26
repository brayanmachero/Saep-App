<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class StopActionLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'status',
        'summary',
        'filters',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'filters' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(array $attrs): ?self
    {
        try {
            return static::create($attrs);
        } catch (\Throwable $e) {
            Log::warning('stop_action_logs: no se pudo registrar auditoria STOP', [
                'action' => $attrs['action'] ?? null,
                'status' => $attrs['status'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
