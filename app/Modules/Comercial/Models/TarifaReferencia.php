<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaReferencia extends Model
{
    protected $table = 'comercial_tarifas_referencia';

    protected $guarded = ['id'];

    protected $casts = [
        'conceptos' => 'array', 'precio' => 'decimal:6',
        'fecha_referencia' => 'date', 'vigente_desde' => 'date',
        'vigente_hasta' => 'date', 'revisado_at' => 'datetime',
    ];

    public function fuente()
    {
        return $this->belongsTo(TarifaFuente::class, 'fuente_id');
    }

    public function revisiones()
    {
        return $this->hasMany(TarifaRevision::class, 'tarifa_id');
    }

    public static function grupoPara(array $datos): string
    {
        return hash('sha256', json_encode(array_map(
            fn ($key) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) ($datos[$key] ?? '')))),
            ['cliente', 'centro', 'cargo', 'categoria', 'modalidad', 'unidad', 'variante', 'moneda']
        ), JSON_UNESCAPED_UNICODE));
    }
}
