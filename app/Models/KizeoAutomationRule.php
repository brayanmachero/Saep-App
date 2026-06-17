<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KizeoAutomationRule extends Model
{
    protected $fillable = [
        'name',
        'form_id',
        'form_name',
        'enabled',
        'priority',
        'conditions',
        'sharepoint_site',
        'sharepoint_folder',
        'folder_template',
        'filename_template',
        'export_id',
        'continue_legacy',
        'last_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'conditions' => 'array',
        'continue_legacy' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(KizeoAutomationRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(KizeoAutomationRun::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeForForm(Builder $query, string $formId): Builder
    {
        return $query->where('form_id', $formId);
    }
}
