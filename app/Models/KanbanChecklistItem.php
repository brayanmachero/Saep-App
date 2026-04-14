<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanChecklistItem extends Model
{
    protected $table = 'kanban_checklist_items';

    protected $fillable = ['tarea_id', 'texto', 'completado', 'orden'];

    protected $casts = [
        'completado' => 'boolean',
    ];

    public function tarea()
    {
        return $this->belongsTo(KanbanTarea::class, 'tarea_id');
    }
}
