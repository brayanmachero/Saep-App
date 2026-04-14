<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanAdjunto extends Model
{
    protected $table = 'kanban_adjuntos';

    protected $fillable = [
        'tarea_id', 'nombre_original', 'ruta', 'mime_type', 'tamanio', 'subido_por',
    ];

    public function tarea()
    {
        return $this->belongsTo(KanbanTarea::class, 'tarea_id');
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function getTamanioFormateadoAttribute(): string
    {
        $bytes = $this->tamanio;
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }

    public function esImagen(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}
