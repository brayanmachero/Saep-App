<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Crear módulo stop_dashboard
        $moduloId = DB::table('modulos')->insertGetId([
            'slug'        => 'stop_dashboard',
            'nombre'      => 'Tarjeta STOP CCU',
            'descripcion' => 'Dashboard de tarjetas STOP sincronizadas desde Google Drive',
            'icono'       => 'bi-hand-index-fill',
            'grupo'       => 'Prevención SST',
            'orden'       => 6,
            'activo'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Auto-alimentar rol_modulo para todos los roles
        $roles = DB::table('roles')->pluck('id');
        $rows = $roles->map(fn ($rolId) => [
            'rol_id'          => $rolId,
            'modulo_id'       => $moduloId,
            'puede_ver'       => true,
            'puede_crear'     => false,
            'puede_editar'    => false,
            'puede_eliminar'  => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        DB::table('rol_modulo')->insert($rows);
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('slug', 'stop_dashboard')->first();
        if ($modulo) {
            DB::table('rol_modulo')->where('modulo_id', $modulo->id)->delete();
            DB::table('modulos')->where('id', $modulo->id)->delete();
        }
    }
};
