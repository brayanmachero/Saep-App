<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        if (!$moduloId) {
            return;
        }

        $now = now();
        $roleIds = DB::table('roles')
            ->where(function ($query) {
                $query->whereIn('codigo', ['SUPER_ADMIN', 'ADMIN', 'ADMINISTRADOR', 'JEFE'])
                    ->orWhere('nombre', 'Jefe')
                    ->orWhere('nombre', 'Administrador');
            })
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $current = DB::table('rol_modulo')
                ->where('rol_id', $roleId)
                ->where('modulo_id', $moduloId)
                ->first();

            DB::table('rol_modulo')->updateOrInsert(
                ['rol_id' => $roleId, 'modulo_id' => $moduloId],
                [
                    'puede_ver' => $current->puede_ver ?? true,
                    'puede_crear' => $current->puede_crear ?? true,
                    'puede_editar' => $current->puede_editar ?? true,
                    'puede_eliminar' => true,
                    'created_at' => $current->created_at ?? $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        if (!$moduloId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('codigo', ['ADMIN', 'ADMINISTRADOR', 'JEFE'])
            ->pluck('id');

        DB::table('rol_modulo')
            ->where('modulo_id', $moduloId)
            ->whereIn('rol_id', $roleIds)
            ->update(['puede_eliminar' => false, 'updated_at' => now()]);
    }
};
