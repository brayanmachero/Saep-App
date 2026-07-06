<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class TalanaTrabajador extends Model
{
    protected $table = 'talana_trabajadores';

    protected $fillable = [
        'talana_id',
        'rut',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'cargo_id',
        'cargo_nombre',
        'departamento_id',
        'departamento_nombre',
        'centro_costo_id',
        'centro_costo_nombre',
        'centro_operativo_id',
        'centro_operativo_nombre',
        'tipo_nomina',
        'razon_social',
        'fecha_nacimiento',
        'fecha_ingreso',
        'fecha_termino',
        'telefono',
        'activo',
        'origen',
        'raw_payload',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_termino' => 'date',
        'raw_payload' => 'array',
    ];

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function centroOperativo()
    {
        return $this->belongsTo(CentroCosto::class, 'centro_operativo_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre . ' ' . $this->apellido_paterno . ' ' . $this->apellido_materno);
    }

    protected function rut(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) {
                    return null;
                }

                $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $value));
                if (strlen($clean) < 2) {
                    return $clean;
                }

                $dv = substr($clean, -1);
                $body = substr($clean, 0, -1);
                return $body . '-' . $dv;
            },
            set: fn (?string $value) => $value ? strtoupper(preg_replace('/[^0-9kK]/', '', $value)) : null,
        );
    }

    public function centroDescargaId(): ?int
    {
        return $this->centro_operativo_id ?: $this->centro_costo_id;
    }

    public function centroDescargaNombre(): ?string
    {
        return $this->centroOperativo?->nombre
            ?: $this->centro_operativo_nombre
            ?: $this->centroCosto?->nombre
            ?: $this->centro_costo_nombre;
    }
}
