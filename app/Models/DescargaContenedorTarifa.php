<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescargaContenedorTarifa extends Model
{
    protected $table = 'descarga_contenedor_tarifas';

    protected $fillable = [
        'cliente',
        'codigo',
        'proceso',
        'costo_unitario',
        'pago_colaborador',
        'requiere_revision',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
        'pago_colaborador' => 'decimal:2',
        'requiere_revision' => 'boolean',
        'activo' => 'boolean',
    ];
}
