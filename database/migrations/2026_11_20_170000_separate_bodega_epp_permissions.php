<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();
            $module = DB::table('modulos')->where('slug', 'solicitudes_epp')->first();
            $moduleId = $module?->id ?? DB::table('modulos')->insertGetId([
                'slug' => 'solicitudes_epp', 'nombre' => 'Solicitudes de EPP',
                'descripcion' => 'Gestión de pedidos EPP, cantidades y reemplazos.',
                'grupo' => 'Bodega', 'icono' => 'bi-clipboard-check', 'orden' => 6,
                'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('modulos')->where('id', $moduleId)->update([
                'nombre' => 'Solicitudes de EPP', 'grupo' => 'Bodega', 'icono' => 'bi-clipboard-check',
                'activo' => true, 'updated_at' => $now,
            ]);
            // Conserva el acceso previo al separar solicitudes del permiso de inventario.
            $inventory = DB::table('modulos')->where('slug', 'inventario_bodega')->value('id');
            foreach (DB::table('rol_modulo')->where('modulo_id', $inventory)->where('puede_ver', true)->get() as $pivot) {
                DB::table('rol_modulo')->updateOrInsert(['rol_id' => $pivot->rol_id, 'modulo_id' => $moduleId], [
                    'puede_ver' => $pivot->puede_ver, 'puede_crear' => $pivot->puede_crear,
                    'puede_editar' => $pivot->puede_editar, 'puede_eliminar' => $pivot->puede_eliminar,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $source = DB::table('roles')->where('codigo', 'BODEGA_ENTREGAS')->first();
            $chiefId = DB::table('roles')->where('codigo', 'BODEGA_JEFATURA')->value('id');
            if (!$chiefId) {
                $values = $source ? (array) $source : [];
                unset($values['id']);
                $chiefId = DB::table('roles')->insertGetId(array_replace($values, [
                    'codigo' => 'BODEGA_JEFATURA', 'nombre' => 'Jefatura de Bodega',
                    'created_at' => $now, 'updated_at' => $now,
                ]));
                if ($source) {
                    foreach (DB::table('rol_modulo')->where('rol_id', $source->id)->get() as $pivot) {
                        $values = (array) $pivot;
                        unset($values['id']);
                        $values['rol_id'] = $chiefId;
                        DB::table('rol_modulo')->insert($values);
                    }
                }
            }
            foreach (DB::table('modulos')->where('grupo', 'Bodega')->get() as $bodegaModule) {
                DB::table('rol_modulo')->updateOrInsert(['rol_id' => $chiefId, 'modulo_id' => $bodegaModule->id], [
                    'puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true, 'puede_eliminar' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach (DB::table('roles')->whereIn('codigo', ['BODEGA_ENTREGAS', 'BODEGA_VEHICULOS'])->pluck('id') as $roleId) {
                    $allowed = in_array($bodegaModule->slug, ['solicitudes_epp', 'gestion_vehiculos'], true);
                    DB::table('rol_modulo')->updateOrInsert(['rol_id' => $roleId, 'modulo_id' => $bodegaModule->id], [
                        'puede_ver' => $allowed, 'puede_crear' => $allowed, 'puede_editar' => $allowed,
                        'puede_eliminar' => $allowed && $bodegaModule->slug === 'gestion_vehiculos',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            if ($source) {
                DB::table('users')->whereRaw('LOWER(email) = ?', ['ginfante@saep.cl'])
                    ->where('rol_id', $source->id)->update(['rol_id' => $chiefId, 'updated_at' => $now]);
            }
        });
    }

    public function down(): void
    {
        // Los permisos son datos administrables: revertir usando el respaldo previo al despliegue.
        throw new RuntimeException('Restaura el respaldo de permisos de Bodega antes de revertir esta migración.');
    }
};
