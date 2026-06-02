<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaWorkShiftPersonRange extends Model
{
    protected $table = 'talana_work_shift_person_ranges';

    protected $fillable = [
        'talana_id',
        'persona_talana_id',
        'work_shift_id',
        'from_date',
        'to_date',
        'synced_at',
    ];

    protected $casts = [
        'from_date'  => 'date',
        'to_date'    => 'date',
        'synced_at'  => 'datetime',
    ];

    public function persona()
    {
        return $this->belongsTo(TalanaPersona::class, 'persona_talana_id', 'talana_id');
    }
}
