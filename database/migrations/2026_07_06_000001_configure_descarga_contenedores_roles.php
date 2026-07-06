<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->updateOrInsert(
            ['codigo' => 'CONTENEDORES_COORDINADOR'],
            [
                'nombre' => 'Coordinador Contenedores',
                'puede_crear_forms' => false,
                'puede_aprobar' => false,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('roles')->updateOrInsert(
            ['codigo' => 'CONTENEDORES_CAPTURADOR'],
            [
                'nombre' => 'Capturador Contenedores',
                'puede_crear_forms' => false,
                'puede_aprobar' => false,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        if (!$moduloId) {
            return;
        }

        $this->setModulePermission('SUPER_ADMIN', $moduloId, true, true, true, true, $now);
        $this->setModulePermission('CONTENEDORES_COORDINADOR', $moduloId, true, true, true, false, $now);
        $this->setModulePermission('CONTENEDORES_CAPTURADOR', $moduloId, true, true, false, false, $now);

        foreach (['JEFE', 'COORDINADOR', 'SUPERVISOR'] as $roleCode) {
            $this->setModulePermission($roleCode, $moduloId, false, false, false, false, $now);
        }
    }

    public function down(): void
    {
        $now = now();
        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        if (!$moduloId) {
            return;
        }

        $this->setModulePermission('SUPER_ADMIN', $moduloId, true, true, true, true, $now);
        $this->setModulePermission('JEFE', $moduloId, true, true, true, false, $now);
        $this->setModulePermission('COORDINADOR', $moduloId, true, true, true, false, $now);
        $this->setModulePermission('SUPERVISOR', $moduloId, true, true, false, false, $now);

        DB::table('rol_modulo')
            ->where('modulo_id', $moduloId)
            ->whereIn('rol_id', DB::table('roles')
                ->whereIn('codigo', ['CONTENEDORES_COORDINADOR', 'CONTENEDORES_CAPTURADOR'])
                ->pluck('id'))
            ->delete();
    }

    private function setModulePermission(
        string $roleCode,
        int $moduleId,
        bool $canView,
        bool $canCreate,
        bool $canEdit,
        bool $canDelete,
        $timestamp
    ): void {
        $roleId = DB::table('roles')->where('codigo', $roleCode)->value('id');
        if (!$roleId) {
            return;
        }

        DB::table('rol_modulo')->updateOrInsert(
            ['rol_id' => $roleId, 'modulo_id' => $moduleId],
            [
                'puede_ver' => $canView,
                'puede_crear' => $canCreate,
                'puede_editar' => $canEdit,
                'puede_eliminar' => $canDelete,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]
        );
    }
};
