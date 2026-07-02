<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        $rolId = DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');

        if (!$moduloId || !$rolId) {
            return;
        }

        DB::table('rol_modulo')
            ->where('modulo_id', $moduloId)
            ->where('rol_id', $rolId)
            ->update([
                'puede_ver' => true,
                'puede_crear' => true,
                'puede_editar' => false,
                'puede_eliminar' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        $rolId = DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');

        if (!$moduloId || !$rolId) {
            return;
        }

        DB::table('rol_modulo')
            ->where('modulo_id', $moduloId)
            ->where('rol_id', $rolId)
            ->update([
                'puede_ver' => true,
                'puede_crear' => true,
                'puede_editar' => true,
                'puede_eliminar' => false,
                'updated_at' => now(),
            ]);
    }
};
