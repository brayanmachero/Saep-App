<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoAdjunto extends Model
{
    protected $table = 'archivos_adjuntos';

    protected $fillable = [
        'entidad_tipo','entidad_id','nombre_original','nombre_archivo',
        'ruta','mime_type','tamanio','campo_formulario','subido_por',
    ];

    public function subidoPor() { return $this->belongsTo(User::class, 'subido_por'); }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->ruta);
    }

    public function getTamanioFormateadoAttribute(): string
    {
        $bytes = $this->tamanio;
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }
}
