<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modulos')->updateOrInsert(
            ['slug' => 'descarga_contenedores'],
            [
                'nombre' => 'Contenedores',
                'descripcion' => 'Registro operativo, carga rápida y participantes por descarga.',
                'icono' => 'bi-box-seam-fill',
                'grupo' => 'Operaciones',
                'orden' => 18,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        if (!$moduloId) {
            return;
        }

        $permisos = [
            'SUPER_ADMIN' => [true, true, true, true],
            'JEFE' => [true, true, true, false],
            'COORDINADOR' => [true, true, true, false],
            'SUPERVISOR' => [true, true, false, false],
        ];

        foreach ($permisos as $rolCodigo => [$ver, $crear, $editar, $eliminar]) {
            $rolId = DB::table('roles')->where('codigo', $rolCodigo)->value('id');
            if (!$rolId) {
                continue;
            }

            DB::table('rol_modulo')->updateOrInsert(
                ['rol_id' => $rolId, 'modulo_id' => $moduloId],
                [
                    'puede_ver' => $ver,
                    'puede_crear' => $crear,
                    'puede_editar' => $editar,
                    'puede_eliminar' => $eliminar,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'descarga_contenedores')->value('id');
        if ($moduloId) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
            DB::table('modulos')->where('id', $moduloId)->delete();
        }
    }
};
