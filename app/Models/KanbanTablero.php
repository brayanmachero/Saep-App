<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanTablero extends Model
{
    protected $table = 'kanban_tableros';

    protected $fillable = [
        'nombre', 'descripcion', 'creado_por', 'centro_costo_id', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function columnas()
    {
        return $this->hasMany(KanbanColumna::class, 'tablero_id')->orderBy('orden');
    }

    public function tareas()
    {
        return $this->hasMany(KanbanTarea::class, 'tablero_id');
    }

    public function etiquetas()
    {
        return $this->hasMany(KanbanEtiqueta::class, 'tablero_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class);
    }

    /**
     * Crear columnas por defecto al crear un tablero.
     */
    public function crearColumnasDefault(): void
    {
        $defaults = [
            ['nombre' => 'Backlog',     'color' => '#6b7280', 'orden' => 1],
            ['nombre' => 'Por Hacer',   'color' => '#3b82f6', 'orden' => 2],
            ['nombre' => 'En Progreso', 'color' => '#f59e0b', 'orden' => 3],
            ['nombre' => 'Revisión',    'color' => '#8b5cf6', 'orden' => 4],
            ['nombre' => 'Completado',  'color' => '#10b981', 'orden' => 5],
        ];

        foreach ($defaults as $col) {
            $this->columnas()->create($col);
        }
    }
}
