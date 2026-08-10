<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modulos')->updateOrInsert(
            ['slug' => 'reclutamiento_whatsapp'],
            [
                'nombre' => 'Reclutamiento WhatsApp',
                'descripcion' => 'Contactos con consentimiento, campañas de reclutamiento y trazabilidad de mensajería.',
                'icono' => 'bi-whatsapp',
                'grupo' => 'RRHH',
                'orden' => 29,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $coordinadorId = DB::table('roles')->where('codigo', 'RECLUTAMIENTO_COORDINADOR')->value('id');
        if (!$coordinadorId) {
            $coordinadorId = DB::table('roles')->insertGetId([
                'codigo' => 'RECLUTAMIENTO_COORDINADOR',
                'nombre' => 'Coordinador de Reclutamiento',
                'puede_crear_forms' => false,
                'puede_aprobar' => true,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $reclutadorId = DB::table('roles')->where('codigo', 'RECLUTADOR')->value('id');
        if (!$reclutadorId) {
            $reclutadorId = DB::table('roles')->insertGetId([
                'codigo' => 'RECLUTADOR',
                'nombre' => 'Reclutador',
                'puede_crear_forms' => false,
                'puede_aprobar' => false,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $moduloId = DB::table('modulos')->where('slug', 'reclutamiento_whatsapp')->value('id');
        if (!$moduloId) {
            return;
        }

        $permisos = [
            'SUPER_ADMIN' => [true, true, true, true],
            'RECLUTAMIENTO_COORDINADOR' => [true, true, true, false],
            'RECLUTADOR' => [true, true, false, false],
        ];

        foreach ($permisos as $codigo => [$ver, $crear, $editar, $eliminar]) {
            $rolId = $codigo === 'RECLUTAMIENTO_COORDINADOR' ? $coordinadorId
                : ($codigo === 'RECLUTADOR' ? $reclutadorId : DB::table('roles')->where('codigo', $codigo)->value('id'));

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
        $moduloId = DB::table('modulos')->where('slug', 'reclutamiento_whatsapp')->value('id');
        if ($moduloId) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
            DB::table('modulos')->where('id', $moduloId)->delete();
        }

        // Los roles pueden tener usuarios asignados al momento de un rollback.
        // Se conservan para no dejar cuentas internas sin referencia de rol.
    }
};
