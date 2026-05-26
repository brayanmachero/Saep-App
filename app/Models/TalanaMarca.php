<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaMarca extends Model
{
    protected $table = 'talana_marcas';

    protected $fillable = [
        'persona_talana_id', 'persona_nombre', 'persona_rut',
        'fecha', 'hora', 'tipo', 'centro_costo_nombre', 'raw_ts', 'synced_at',
    ];

    protected $casts = [
        'fecha'     => 'date',
        'raw_ts'    => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function persona()
    {
        return $this->belongsTo(TalanaPersona::class, 'persona_talana_id', 'talana_id');
    }
}
