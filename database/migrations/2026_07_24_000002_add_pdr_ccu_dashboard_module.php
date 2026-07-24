<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modulos')->updateOrInsert(
            ['slug' => 'pdr_ccu_dashboard'],
            [
                'nombre' => 'Observaciones CCU',
                'descripcion' => 'Indicadores de observaciones de conducta CCU sincronizados desde Kizeo.',
                'icono' => 'bi-clipboard2-pulse-fill',
                'grupo' => 'Prevención SST',
                'orden' => 29,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $moduleId = DB::table('modulos')->where('slug', 'pdr_ccu_dashboard')->value('id');
        $sourceModuleId = DB::table('modulos')->where('slug', 'kizeo_analytics')->value('id');

        if (!$moduleId || !$sourceModuleId) {
            return;
        }

        $permissions = DB::table('rol_modulo')
            ->where('modulo_id', $sourceModuleId)
            ->get();

        foreach ($permissions as $permission) {
            DB::table('rol_modulo')->updateOrInsert(
                [
                    'rol_id' => $permission->rol_id,
                    'modulo_id' => $moduleId,
                ],
                [
                    'puede_ver' => $permission->puede_ver,
                    'puede_crear' => $permission->puede_crear,
                    'puede_editar' => $permission->puede_editar,
                    'puede_eliminar' => $permission->puede_eliminar,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('modulos')->where('slug', 'pdr_ccu_dashboard')->delete();
    }
};
