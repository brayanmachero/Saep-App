<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostulanteContratacion extends Model
{
    use SoftDeletes;

    protected $table = 'postulantes_contratacion';

    protected $fillable = [
        'folio', 'nombre', 'rut', 'email',
        'google_id', 'google_name', 'google_avatar',
        'postulacion_anterior_id', 'es_vigente',
        'carnet_frontal', 'carnet_reverso',
        'certificado_afp', 'certificado_fonasa',
        'licencia_conducir_frontal', 'licencia_conducir_reverso',
        'estado', 'observaciones',
        'consentimiento_datos', 'consentimiento_at', 'consentimiento_version',
        'consentimiento_texto',
        'consentimiento_aceptado_at', 'consentimiento_ip', 'consentimiento_user_agent',
        'anonimizado_at',
    ];

    protected $casts = [
        'consentimiento_datos' => 'boolean',
        'consentimiento_at' => 'datetime',
        'consentimiento_aceptado_at' => 'datetime',
        'anonimizado_at' => 'datetime',
        'es_vigente' => 'boolean',
    ];

    public const DOCUMENTOS_OBLIGATORIOS = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa'];

    // ── Folio automático (atómico: lock pesimista para evitar duplicados) ─
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->folio)) {
                $year = date('Y');
                // lockForUpdate dentro de una transacción serializa la lectura
                // del último folio y bloquea otros INSERT concurrentes.
                $model->folio = \DB::transaction(function () use ($year) {
                    $lastSeq = static::whereYear('created_at', $year)
                        ->where('folio', 'like', "POST-{$year}-%")
                        ->lockForUpdate()
                        ->pluck('folio')
                        ->map(function ($folio) {
                            $suffix = strrchr((string) $folio, '-');

                            return $suffix === false ? 0 : (int) substr($suffix, 1);
                        })
                        ->max();
                    $seq = ((int) $lastSeq) + 1;
                    return 'POST-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
                });
            }
        });
    }

    // ── Helpers de estado ────────────────────────────────────────
    public static function estadosMap(): array
    {
        return [
            'pendiente'   => ['label' => 'Pendiente',    'color' => '#f59e0b'],
            'en_revision' => ['label' => 'En Revisión',  'color' => '#3b82f6'],
            'aprobado'    => ['label' => 'Aprobado',     'color' => '#22c55e'],
            'rechazado'   => ['label' => 'Rechazado',    'color' => '#ef4444'],
        ];
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::estadosMap()[$this->estado]['label'] ?? ($this->estado ?? 'Sin estado');
    }

    public function getEstadoColorAttribute(): string
    {
        return self::estadosMap()[$this->estado]['color'] ?? '#94a3b8';
    }

    // ── Documentos: lista de los que ya se subieron ───────────────
    public function documentosSubidos(): array
    {
        $docs = [];
        $campos = [
            'carnet_frontal'             => 'Carnet (Frontal)',
            'carnet_reverso'             => 'Carnet (Reverso)',
            'certificado_afp'            => 'Certificado AFP',
            'certificado_fonasa'         => 'Certificado FONASA',
            'licencia_conducir_frontal'  => 'Licencia de Conducir (Frontal)',
            'licencia_conducir_reverso'  => 'Licencia de Conducir (Reverso)',
        ];
        foreach ($campos as $campo => $label) {
            if ($this->$campo) {
                $docs[] = ['campo' => $campo, 'label' => $label, 'ruta' => $this->$campo];
            }
        }
        return $docs;
    }

    public function documentosFaltantes(): array
    {
        return array_filter(self::DOCUMENTOS_OBLIGATORIOS, fn($c) => empty($this->$c));
    }

    public function documentosCompletos(): bool
    {
        return count($this->documentosFaltantes()) === 0;
    }

    public function documentosObligatoriosSubidos(): int
    {
        return count(array_filter(self::DOCUMENTOS_OBLIGATORIOS, fn($c) => !empty($this->$c)));
    }

    // ── Relaciones ────────────────────────────────────────────────
    public function syncLogs(): HasMany
    {
        return $this->hasMany(ContratacionSyncLog::class, 'postulante_id')->latest('id');
    }

    public function postulacionAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'postulacion_anterior_id');
    }

    public function repostulaciones(): HasMany
    {
        return $this->hasMany(self::class, 'postulacion_anterior_id')->latest('id');
    }

    public function getEsRepostulacionAttribute(): bool
    {
        return $this->postulacion_anterior_id !== null;
    }

    public function ultimoSync(): HasOne
    {
        return $this->hasOne(ContratacionSyncLog::class, 'postulante_id')->latestOfMany();
    }

    // ── Formato RUT chileno ───────────────────────────────────────
    public static function formatearRut(string $rut): string
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        if (strlen($rut) < 2) return $rut;
        $dv   = substr($rut, -1);
        $num  = substr($rut, 0, -1);
        $num  = number_format((int)$num, 0, '', '.');
        return $num . '-' . strtoupper($dv);
    }

    public static function validarRut(string $rut): bool
    {
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        if (strlen($rut) < 2) return false;
        $dv  = substr($rut, -1);
        $num = (int) substr($rut, 0, -1);
        $sum = 0; $factor = 2;
        while ($num > 0) {
            $sum   += ($num % 10) * $factor;
            $num    = intdiv($num, 10);
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $calc = 11 - ($sum % 11);
        $expected = match($calc) { 11 => '0', 10 => 'K', default => (string)$calc };
        return $dv === $expected;
    }
}
