<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulos') || ! Schema::hasTable('roles') || ! Schema::hasTable('rol_modulo')) {
            return;
        }

        $now = now();

        DB::table('modulos')->updateOrInsert(
            ['slug' => 'comercial'],
            [
                'nombre' => 'Comercial',
                'descripcion' => 'Cotizador y mantenedores comerciales EST/SUB',
                'icono' => 'bi-calculator-fill',
                'grupo' => 'Comercial',
                'orden' => 35,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $modulos = DB::table('modulos')->whereIn('slug', ['dashboard', 'comercial', 'documentacion'])->pluck('id', 'slug');

        DB::table('roles')->updateOrInsert(
            ['codigo' => 'COMERCIAL'],
            [
                'nombre' => 'Comercial',
                'puede_crear_forms' => false,
                'puede_aprobar' => true,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $roles = DB::table('roles')->whereIn('codigo', ['SUPER_ADMIN', 'COMERCIAL'])->pluck('id', 'codigo');

        if (isset($roles['SUPER_ADMIN'], $modulos['comercial'])) {
            $this->asignarModulo($roles['SUPER_ADMIN'], $modulos['comercial'], [1, 1, 1, 1], $now);
        }

        if (isset($roles['COMERCIAL'])) {
            if (isset($modulos['dashboard'])) {
                $this->asignarModulo($roles['COMERCIAL'], $modulos['dashboard'], [1, 0, 0, 0], $now);
            }
            if (isset($modulos['comercial'])) {
                $this->asignarModulo($roles['COMERCIAL'], $modulos['comercial'], [1, 1, 1, 0], $now);
            }
            if (isset($modulos['documentacion'])) {
                $this->asignarModulo($roles['COMERCIAL'], $modulos['documentacion'], [1, 0, 0, 0], $now);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulos')) {
            return;
        }

        $moduloId = DB::table('modulos')->where('slug', 'comercial')->value('id');
        if ($moduloId && Schema::hasTable('rol_modulo')) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
        }
        DB::table('modulos')->where('slug', 'comercial')->delete();
    }

    private function asignarModulo(int $rolId, int $moduloId, array $permisos, $now): void
    {
        DB::table('rol_modulo')->updateOrInsert(
            ['rol_id' => $rolId, 'modulo_id' => $moduloId],
            [
                'puede_ver' => (bool) $permisos[0],
                'puede_crear' => (bool) $permisos[1],
                'puede_editar' => (bool) $permisos[2],
                'puede_eliminar' => (bool) $permisos[3],
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};
