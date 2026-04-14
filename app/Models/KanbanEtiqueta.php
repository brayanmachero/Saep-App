<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanEtiqueta extends Model
{
    protected $table = 'kanban_etiquetas';

    protected $fillable = [
        'tablero_id', 'nombre', 'color',
    ];

    public function tablero()
    {
        return $this->belongsTo(KanbanTablero::class, 'tablero_id');
    }

    public function tareas()
    {
        return $this->belongsToMany(KanbanTarea::class, 'kanban_tarea_etiqueta', 'etiqueta_id', 'tarea_id');
    }
}
