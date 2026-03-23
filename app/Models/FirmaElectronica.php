<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirmaElectronica extends Model
{
    protected $table = 'firmas_electronicas';

    protected $fillable = [
        'entidad_tipo','entidad_id','firmante_id','firmante_nombre','firmante_rut',
        'firmante_email','firmante_cargo','firma_imagen','hash_sha256','ip_address',
        'user_agent','latitud','longitud','geolocalizacion','proposito',
    ];

    protected $hidden = ['firma_imagen']; // excluir de listados por peso

    public function firmante() { return $this->belongsTo(User::class, 'firmante_id'); }

    public static function generarHash(string $firmaImagen, string $nombre, string $timestamp): string
    {
        return hash('sha256', $firmaImagen . $nombre . $timestamp);
    }
}
