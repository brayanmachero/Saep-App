<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('modulos') || ! Schema::hasTable('rol_modulo')) {
            return;
        }

        $superAdminRoleIds = DB::table('roles')
            ->where('codigo', 'SUPER_ADMIN')
            ->pluck('id');

        if ($superAdminRoleIds->isEmpty()) {
            return;
        }

        $moduleIds = DB::table('modulos')
            ->where('activo', true)
            ->pluck('id');

        $now = now();

        foreach ($superAdminRoleIds as $roleId) {
            foreach ($moduleIds as $moduleId) {
                DB::table('rol_modulo')->updateOrInsert(
                    ['rol_id' => $roleId, 'modulo_id' => $moduleId],
                    [
                        'puede_ver' => true,
                        'puede_crear' => true,
                        'puede_editar' => true,
                        'puede_eliminar' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // No se revierte: no hay forma segura de reconstruir el estado previo de permisos.
    }
};
