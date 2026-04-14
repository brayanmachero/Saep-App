<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanActividadLog extends Model
{
    protected $table = 'kanban_actividad_log';

    protected $fillable = [
        'tablero_id', 'tarea_id', 'user_id', 'accion', 'detalle',
    ];

    public function tablero()
    {
        return $this->belongsTo(KanbanTablero::class, 'tablero_id');
    }

    public function tarea()
    {
        return $this->belongsTo(KanbanTarea::class, 'tarea_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Registrar una acción de actividad.
     */
    public static function registrar(int $tableroId, ?int $tareaId, string $accion, ?string $detalle = null): self
    {
        return static::create([
            'tablero_id' => $tableroId,
            'tarea_id'   => $tareaId,
            'user_id'    => auth()->id(),
            'accion'     => $accion,
            'detalle'    => $detalle ? \Str::limit($detalle, 497) : null,
        ]);
    }
}
