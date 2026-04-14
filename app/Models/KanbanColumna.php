<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanColumna extends Model
{
    protected $table = 'kanban_columnas';

    protected $fillable = [
        'tablero_id', 'nombre', 'color', 'orden',
    ];

    public function tablero()
    {
        return $this->belongsTo(KanbanTablero::class, 'tablero_id');
    }

    public function tareas()
    {
        return $this->hasMany(KanbanTarea::class, 'columna_id')->orderBy('orden');
    }
}
