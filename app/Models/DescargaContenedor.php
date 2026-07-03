<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DescargaContenedor extends Model
{
    use SoftDeletes;

    protected $table = 'descarga_contenedores';

    protected $fillable = [
        'carga_id',
        'estado',
        'origen',
        'operacion',
        'centro_costo_id',
        'bodega',
        'supervisor_id',
        'supervisor_nombre',
        'facturacion_mes',
        'fecha',
        'contenedor',
        'equipo_descarga',
        'hora_cita',
        'hora_inicio_descarga',
        'hora_termino_descarga',
        'item',
        'cajas',
        'pallets',
        'producto',
        'fact_codigo',
        'tarifa_id',
        'tarifa_cliente_snapshot',
        'tarifa_proceso_snapshot',
        'costo_unitario_snapshot',
        'pago_colaborador_snapshot',
        'requiere_revision_tarifa',
        'observacion',
        'raw_row',
        'creado_por',
        'validado_por',
        'validado_at',
        'liquidado_por',
        'liquidado_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'raw_row' => 'array',
        'validado_at' => 'datetime',
        'liquidado_at' => 'datetime',
        'pallets' => 'decimal:2',
        'costo_unitario_snapshot' => 'decimal:2',
        'pago_colaborador_snapshot' => 'decimal:2',
        'requiere_revision_tarifa' => 'boolean',
    ];

    public function carga()
    {
        return $this->belongsTo(DescargaContenedorCarga::class, 'carga_id');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function tarifa()
    {
        return $this->belongsTo(DescargaContenedorTarifa::class, 'tarifa_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function validadoPor()
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function liquidadoPor()
    {
        return $this->belongsTo(User::class, 'liquidado_por');
    }

    public function participantes()
    {
        return $this->hasMany(DescargaContenedorParticipante::class, 'descarga_contenedor_id');
    }

    public function trabajadores()
    {
        return $this->belongsToMany(User::class, 'descarga_contenedor_participantes', 'descarga_contenedor_id', 'user_id')
            ->withPivot([
                'nombre_snapshot',
                'rut_snapshot',
                'cargo_snapshot',
                'centro_costo_id_snapshot',
                'centro_costo_snapshot',
                'rol_en_descarga',
                'porcentaje_participacion',
                'monto_calculado',
            ])
            ->withTimestamps();
    }

    public function trabajadoresTalana()
    {
        return $this->belongsToMany(TalanaTrabajador::class, 'descarga_contenedor_participantes', 'descarga_contenedor_id', 'talana_trabajador_id')
            ->withPivot([
                'nombre_snapshot',
                'rut_snapshot',
                'cargo_snapshot',
                'centro_costo_id_snapshot',
                'centro_costo_snapshot',
                'rol_en_descarga',
                'porcentaje_participacion',
                'monto_calculado',
            ])
            ->withTimestamps();
    }

    public function getEstadoBadgeAttribute(): array
    {
        return match ($this->estado) {
            'validado' => ['label' => 'Validado', 'class' => 'badge-success'],
            'cerrado' => ['label' => 'Cerrado', 'class' => 'badge-info'],
            'liquidado' => ['label' => 'Liquidado', 'class' => 'badge-secondary'],
            default => ['label' => 'Borrador', 'class' => 'badge-warning'],
        };
    }

    public function validationBlockers()
    {
        $this->loadMissing('participantes');

        $participantesCount = $this->participantes->count();
        $porcentajeTotal = round((float) $this->participantes->sum('porcentaje_participacion'), 2);

        return collect([
            blank($this->fecha) ? 'falta fecha' : null,
            blank($this->contenedor) ? 'falta contenedor' : null,
            blank($this->centro_costo_id) && blank($this->bodega) ? 'falta centro o bodega' : null,
            blank($this->fact_codigo) ? 'falta código FACT' : null,
            !$this->tarifa_id ? 'falta tarifa asociada' : null,
            $this->requiere_revision_tarifa ? 'tarifa pendiente de revisión' : null,
            !$this->requiere_revision_tarifa && $this->pago_colaborador_snapshot === null ? 'falta pago colaborador' : null,
            $this->participantes->isEmpty() ? 'falta equipo de trabajadores' : null,
            $participantesCount > 0 && abs($porcentajeTotal - 100.0) > 0.01 ? 'porcentajes no suman 100%' : null,
        ])->filter()->values();
    }

    public function listoParaValidar(): bool
    {
        return $this->estado === 'borrador' && $this->validationBlockers()->isEmpty();
    }
}
