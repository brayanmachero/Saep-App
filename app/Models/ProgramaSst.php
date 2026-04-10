<?php

namespace App\Models;

use App\Models\CentroCosto;
use Illuminate\Database\Eloquent\Model;

class ProgramaSst extends Model
{
    protected $table = 'programas_sst';

    protected $fillable = [
        'anio', 'titulo', 'descripcion', 'estado',
        'codigo', 'centro_costo_id', 'responsable_id', 'creado_por',
    ];

    // Alias: views usan $prog->nombre
    public function getNombreAttribute(): string { return $this->titulo ?? ''; }

    // === Relationships ===
    public function centroCosto() { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function responsable() { return $this->belongsTo(User::class, 'responsable_id'); }
    public function categorias()  { return $this->hasMany(SstCategoria::class, 'programa_id'); }
    public function creador()     { return $this->belongsTo(User::class, 'creado_por'); }

    // === Auto-código ===
    protected static function booted(): void
    {
        static::creating(function (self $prog) {
            if (empty($prog->codigo)) {
                $year = $prog->anio ?? date('Y');
                $seq  = self::where('anio', $year)->count() + 1;
                $prog->codigo = sprintf('SST-%d-%03d', $year, $seq);
            }
        });
    }

    // === Stats ===
    public function getPorcentajeRealizadoAttribute(): int
    {
        $seguimientos = SstSeguimiento::whereHas('actividad', fn($q) =>
            $q->whereHas('categoria', fn($q2) => $q2->where('programa_id', $this->id))
        )->where('programado', true)
         ->with('actividad')
         ->get();

        $totalProg = 0;
        $totalReal = 0;
        foreach ($seguimientos as $s) {
            $cant = max(1, (int) ($s->actividad->cantidad_programada ?? 1));
            $totalProg += $cant;
            $totalReal += (int) ($s->cantidad_realizada ?? ($s->realizado ? $cant : 0));
        }
        return $totalProg > 0 ? (int) round($totalReal / $totalProg * 100) : 0;
    }

    public function getEstadoBadgeAttribute(): string
    {
        return match($this->estado) {
            'ACTIVO'   => 'success',
            'CERRADO'  => 'secondary',
            'BORRADOR' => 'warning',
            default    => 'info',
        };
    }

    public function getActividadesTotalesAttribute(): int
    {
        return SstActividad::whereHas('categoria', fn($q) => $q->where('programa_id', $this->id))->count();
    }

    public function getActividadesVencidasAttribute(): int
    {
        return SstActividad::whereHas('categoria', fn($q) => $q->where('programa_id', $this->id))
            ->where('fecha_fin', '<', now())
            ->where('estado', '!=', 'COMPLETADA')
            ->count();
    }
}
