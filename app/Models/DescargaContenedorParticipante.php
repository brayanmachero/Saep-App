<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescargaContenedorParticipante extends Model
{
    protected $table = 'descarga_contenedor_participantes';

    protected $fillable = [
        'descarga_contenedor_id',
        'user_id',
        'talana_trabajador_id',
        'nombre_snapshot',
        'rut_snapshot',
        'cargo_snapshot',
        'centro_costo_id_snapshot',
        'centro_costo_snapshot',
        'rol_en_descarga',
        'porcentaje_participacion',
        'monto_calculado',
    ];

    protected $casts = [
        'porcentaje_participacion' => 'decimal:2',
        'monto_calculado' => 'decimal:2',
    ];

    public function descarga()
    {
        return $this->belongsTo(DescargaContenedor::class, 'descarga_contenedor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function talanaTrabajador()
    {
        return $this->belongsTo(TalanaTrabajador::class, 'talana_trabajador_id');
    }
}
