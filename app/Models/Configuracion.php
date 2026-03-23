<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave','valor','tipo','categoria','descripcion','editable'];

    protected $casts = ['editable' => 'boolean'];

    public static function get(string $clave, $default = null)
    {
        $config = static::where('clave', $clave)->first();
        return $config ? $config->valor : $default;
    }

    public static function set(string $clave, $valor): void
    {
        static::where('clave', $clave)->update(['valor' => $valor, 'updated_at' => now()]);
    }
}
