<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KizeoAutomationRun extends Model
{
    protected $fillable = [
        'kizeo_automation_rule_id',
        'form_id',
        'data_id',
        'status',
        'filename',
        'sharepoint_path',
        'error_message',
        'context',
        'processed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'processed_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(KizeoAutomationRule::class, 'kizeo_automation_rule_id');
    }
}
