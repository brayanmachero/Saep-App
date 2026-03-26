<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeyKarinLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ley_karin_id', 'user_id', 'accion', 'detalle', 'ip',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function leyKarin() { return $this->belongsTo(LeyKarin::class); }
    public function user()     { return $this->belongsTo(User::class); }

    /**
     * Log an action on a Ley Karin complaint.
     */
    public static function registrar(int $leyKarinId, string $accion, ?string $detalle = null, ?int $userId = null): static
    {
        return static::create([
            'ley_karin_id' => $leyKarinId,
            'user_id'      => $userId ?? auth()->id(),
            'accion'       => $accion,
            'detalle'      => $detalle,
            'ip'           => request()->ip(),
        ]);
    }
}
