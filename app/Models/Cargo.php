<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    protected $fillable = ['codigo','nombre','activo'];

    protected $casts = ['activo' => 'boolean'];

    public function usuarios() { return $this->hasMany(User::class, 'cargo_id'); }
}
