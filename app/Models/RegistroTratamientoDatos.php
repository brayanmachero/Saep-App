<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroTratamientoDatos extends Model
{
    protected $table = 'registro_tratamiento_datos';

    protected $fillable = [
        'user_id',
        'accion',
        'tabla_afectada',
        'registro_id',
        'tipo_dato',
        'descripcion',
        'ip_address',
        'user_agent',
        'datos_anteriores',
        'datos_nuevos',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registrar una actividad de tratamiento de datos personales.
     */
    public static function registrar(
        string $accion,
        string $tablaAfectada,
        ?int $registroId = null,
        string $tipoDato = 'personal',
        ?string $descripcion = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null
    ): static {
        return static::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $registroId,
            'tipo_dato' => $tipoDato,
            'descripcion' => $descripcion,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
        ]);
    }
}
