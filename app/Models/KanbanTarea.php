<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanTarea extends Model
{
    protected $table = 'kanban_tareas';

    protected $fillable = [
        'tablero_id', 'columna_id', 'titulo', 'descripcion', 'prioridad',
        'asignado_a', 'creado_por', 'centro_costo_id', 'fecha_inicio',
        'fecha_vencimiento', 'orden', 'archivada',
        'recurrencia', 'recurrencia_hasta', 'tarea_origen_id',
    ];

    protected $casts = [
        'fecha_inicio'       => 'date',
        'fecha_vencimiento'  => 'date',
        'recurrencia_hasta'  => 'date',
        'archivada'          => 'boolean',
    ];

    public function tablero()
    {
        return $this->belongsTo(KanbanTablero::class, 'tablero_id');
    }

    public function columna()
    {
        return $this->belongsTo(KanbanColumna::class, 'columna_id');
    }

    public function asignado()
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function asignados()
    {
        return $this->belongsToMany(User::class, 'kanban_tarea_asignados', 'tarea_id', 'user_id')->withTimestamps();
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function etiquetas()
    {
        return $this->belongsToMany(KanbanEtiqueta::class, 'kanban_tarea_etiqueta', 'tarea_id', 'etiqueta_id');
    }

    public function comentarios()
    {
        return $this->hasMany(KanbanComentario::class, 'tarea_id')->orderByDesc('created_at');
    }

    public function adjuntos()
    {
        return $this->hasMany(KanbanAdjunto::class, 'tarea_id')->orderByDesc('created_at');
    }

    public function checklistItems()
    {
        return $this->hasMany(KanbanChecklistItem::class, 'tarea_id')->orderBy('orden');
    }

    public function actividadLog()
    {
        return $this->hasMany(KanbanActividadLog::class, 'tarea_id')->orderByDesc('created_at');
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->fecha_vencimiento && $this->fecha_vencimiento->isPast();
    }

    public function getChecklistProgresoAttribute(): array
    {
        $total = $this->checklistItems->count();
        $completados = $this->checklistItems->where('completado', true)->count();
        return ['total' => $total, 'completados' => $completados];
    }
}
