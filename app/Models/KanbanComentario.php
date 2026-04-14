<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanComentario extends Model
{
    protected $table = 'kanban_comentarios';

    protected $fillable = ['tarea_id', 'user_id', 'contenido'];

    public function tarea()
    {
        return $this->belongsTo(KanbanTarea::class, 'tarea_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
