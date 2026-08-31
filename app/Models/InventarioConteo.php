<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioConteo extends Model
{
    public const ESTADOS = [
        'BORRADOR' => 'En conteo',
        'EN_REVISION' => 'En revision',
        'REEMPLAZADO' => 'Reemplazado por saldo consolidado',
        'APROBADO' => 'Aprobado y ajustado',
    ];

    protected $table = 'inventario_conteos';

    protected $fillable = [
        'codigo', 'ubicacion_id', 'fecha_corte', 'estado', 'observacion', 'creado_por',
        'aprobado_por', 'aprobado_en',
    ];

    protected $casts = ['fecha_corte' => 'date', 'aprobado_en' => 'datetime'];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(InventarioConteoLinea::class, 'conteo_id')->with(['producto', 'variante']);
    }

    public function puedeEliminarse(): bool
    {
        return in_array($this->estado, ['BORRADOR', 'EN_REVISION'], true);
    }
}
