<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaPersonal extends Model
{
    protected $table = 'notas_personales';

    protected $fillable = [
        'user_id',
        'contenido',
        'categoria',
        'fecha_recordatorio',
        'completada',
        'origen',
    ];

    protected $casts = [
        'fecha_recordatorio' => 'date',
        'completada'         => 'boolean',
    ];

    public const CATEGORIAS = [
        'General',
        'Reunión',
        'Tarea',
        'Recordatorio',
        'Horas Extra',
        'Personal',
        'Urgente',
        'Idea',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDelUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePendientes($query)
    {
        return $query->where('completada', false);
    }

    public function scopeCompletadas($query)
    {
        return $query->where('completada', true);
    }

    public function scopeCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopeDelMes($query, ?int $mes = null, ?int $anio = null)
    {
        $mes  = $mes  ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('created_at', $mes)->whereYear('created_at', $anio);
    }
}
