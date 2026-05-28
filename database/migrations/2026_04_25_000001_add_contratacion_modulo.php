<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insertar módulo contratación
        DB::table('modulos')->updateOrInsert(
            ['slug' => 'contratacion'],
            [
                'slug'       => 'contratacion',
                'nombre'     => 'Contratación RRHH',
                'icono'      => 'bi-person-badge-fill',
                'grupo'      => 'RRHH',
                'orden'      => 28,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Asignar el módulo automáticamente a todos los roles SUPER_ADMIN
        $modulo = DB::table('modulos')->where('slug', 'contratacion')->first();
        if ($modulo) {
            $superAdminRoles = DB::table('roles')->where('codigo', 'SUPER_ADMIN')->pluck('id');
            foreach ($superAdminRoles as $rolId) {
                DB::table('rol_modulo')->updateOrInsert(
                    ['rol_id' => $rolId, 'modulo_id' => $modulo->id],
                    [
                        'puede_ver'      => true,
                        'puede_crear'    => true,
                        'puede_editar'   => true,
                        'puede_eliminar' => true,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $modulo = DB::table('modulos')->where('slug', 'contratacion')->first();
        if ($modulo) {
            DB::table('rol_modulo')->where('modulo_id', $modulo->id)->delete();
        }
        DB::table('modulos')->where('slug', 'contratacion')->delete();
    }
};
