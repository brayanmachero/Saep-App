<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentimientoDatos extends Model
{
    protected $table = 'consentimientos_datos';

    protected $fillable = [
        'user_id',
        'version_politica',
        'texto_aceptado',
        'ip_address',
        'user_agent',
        'fecha_aceptacion',
        'fecha_revocacion',
        'vigente',
    ];

    protected $casts = [
        'fecha_aceptacion' => 'datetime',
        'fecha_revocacion' => 'datetime',
        'vigente' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
