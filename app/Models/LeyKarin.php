<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeyKarin extends Model
{
    protected $table = 'ley_karin';

    protected $fillable = [
        'folio', 'tipo', 'denunciante_id', 'denunciante_nombre', 'denunciante_rut',
        'denunciado_nombre', 'denunciado_cargo', 'centro_costo_id', 'canal',
        'fecha_denuncia', 'descripcion_hechos', 'medidas_cautelares',
        'resultado_investigacion', 'medidas_adoptadas',
        'fecha_resolucion', 'fecha_plazo_investigacion',
        'estado', 'anonima', 'confidencial', 'investigador_id',
    ];

    protected $casts = [
        'fecha_denuncia'             => 'date',
        'fecha_resolucion'           => 'date',
        'fecha_plazo_investigacion'  => 'date',
        'anonima'                    => 'boolean',
        'confidencial'               => 'boolean',
    ];

    public function denunciante()  { return $this->belongsTo(User::class, 'denunciante_id'); }
    public function investigador() { return $this->belongsTo(User::class, 'investigador_id'); }
    public function centroCosto()  { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->folio)) {
                $year = date('Y');
                $seq = static::whereYear('created_at', $year)->count() + 1;
                $model->folio = 'LK-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }
            if (empty($model->estado)) {
                $model->estado = 'RECIBIDA';
            }
        });
    }

    public static function tiposMap(): array
    {
        return [
            'ACOSO_LABORAL'       => 'Acoso Laboral',
            'ACOSO_SEXUAL'        => 'Acoso Sexual',
            'VIOLENCIA_EN_TRABAJO' => 'Violencia en el Trabajo',
        ];
    }

    public static function estadosMap(): array
    {
        return [
            'RECIBIDA'          => 'Recibida',
            'EN_INVESTIGACION'  => 'En Investigación',
            'RESUELTA'          => 'Resuelta',
            'DERIVADA_DT'       => 'Derivada a la DT',
            'ARCHIVADA'         => 'Archivada',
        ];
    }

    public static function canalesMap(): array
    {
        return [
            'PRESENCIAL'         => 'Presencial',
            'ESCRITO'            => 'Escrito',
            'CORREO_ELECTRONICO' => 'Correo Electrónico',
            'FORMULARIO_WEB'     => 'Formulario Web',
            'TELEFONO'           => 'Teléfono',
            'ANONIMO'            => 'Anónimo',
        ];
    }

    public function getTipoLabelAttribute(): string
    {
        return self::tiposMap()[$this->tipo] ?? $this->tipo;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::estadosMap()[$this->estado] ?? $this->estado;
    }

    public function getCanalLabelAttribute(): string
    {
        return self::canalesMap()[$this->canal] ?? ($this->canal ?: '—');
    }

    public function getEstadoBadgeAttribute(): array
    {
        return match ($this->estado) {
            'RECIBIDA'         => ['label' => 'Recibida',          'class' => 'badge info'],
            'EN_INVESTIGACION' => ['label' => 'En Investigación',  'class' => 'badge warning'],
            'RESUELTA'         => ['label' => 'Resuelta',          'class' => 'badge success'],
            'DERIVADA_DT'      => ['label' => 'Derivada a la DT',  'class' => 'badge warning'],
            'ARCHIVADA'        => ['label' => 'Archivada',         'class' => 'badge secondary'],
            default            => ['label' => $this->estado,       'class' => 'badge secondary'],
        };
    }

    public function getTipoBadgeAttribute(): array
    {
        return match ($this->tipo) {
            'ACOSO_SEXUAL'          => ['label' => 'Acoso Sexual',           'class' => 'badge danger'],
            'ACOSO_LABORAL'         => ['label' => 'Acoso Laboral',          'class' => 'badge warning'],
            'VIOLENCIA_EN_TRABAJO'  => ['label' => 'Violencia en el Trabajo','class' => 'badge danger'],
            default                 => ['label' => $this->tipo,              'class' => 'badge secondary'],
        };
    }

    public function getPlazoVencidoAttribute(): bool
    {
        return $this->fecha_plazo_investigacion && $this->fecha_plazo_investigacion->isPast()
            && !in_array($this->estado, ['RESUELTA', 'ARCHIVADA']);
    }
}
