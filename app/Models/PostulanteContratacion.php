<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostulanteContratacion extends Model
{
    protected $table = 'postulantes_contratacion';

    protected $fillable = [
        'folio', 'nombre', 'rut', 'email',
        'google_id', 'google_name', 'google_avatar',
        'carnet_frontal', 'carnet_reverso',
        'certificado_afp', 'certificado_fonasa',
        'licencia_conducir_frontal', 'licencia_conducir_reverso',
        'estado', 'observaciones',
    ];

    // ── Folio automático (atómico: lock pesimista para evitar duplicados) ─
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->folio)) {
                $year = date('Y');
                // lockForUpdate dentro de una transacción serializa la lectura
                // del último folio y bloquea otros INSERT concurrentes.
                $model->folio = \DB::transaction(function () use ($year) {
                    $last = static::whereYear('created_at', $year)
                        ->orderByRaw('CAST(SUBSTRING_INDEX(folio, \'-\', -1) AS UNSIGNED) DESC')
                        ->lockForUpdate()
                        ->value('folio');
                    $seq = $last ? ((int) substr(strrchr($last, '-'), 1)) + 1 : 1;
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
        // Solo carnet y certificados son obligatorios
        $obligatorios = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa'];
        return array_filter($obligatorios, fn($c) => empty($this->$c));
    }

    public function documentosCompletos(): bool
    {
        return count($this->documentosFaltantes()) === 0;
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
