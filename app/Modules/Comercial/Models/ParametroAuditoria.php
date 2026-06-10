<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametroAuditoria extends Model
{
    protected $table = 'comercial_parametro_auditorias';

    protected $fillable = [
        'parametro_id',
        'usuario_id',
        'clave',
        'nombre',
        'categoria',
        'valor_anterior',
        'valor_nuevo',
        'origen',
        'descripcion',
        'ip_address',
        'user_agent',
    ];

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(Parametro::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }
}
