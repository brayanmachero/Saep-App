<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')
            ->where('slug', 'descarga_contenedores')
            ->update([
                'nombre' => 'Contenedores',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('modulos')
            ->where('slug', 'descarga_contenedores')
            ->update([
                'nombre' => 'Descarga de Contenedores',
                'updated_at' => now(),
            ]);
    }
};
