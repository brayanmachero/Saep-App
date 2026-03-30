<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insertar módulo solo si no existe
        $existe = DB::table('modulos')->where('slug', 'notas_personales')->exists();
        if (!$existe) {
            DB::table('modulos')->insert([
                'slug'        => 'notas_personales',
                'nombre'      => 'Notas por Voz',
                'descripcion' => 'Notas personales con dictado por voz y clasificación inteligente',
                'icono'       => 'bi-journal-text',
                'grupo'       => 'Herramientas',
                'orden'       => 55,
                'activo'      => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // Asignar acceso a rol Admin por defecto
        $moduloId = DB::table('modulos')->where('slug', 'notas_personales')->value('id');
        if ($moduloId) {
            $adminRol = DB::table('roles')->where('codigo', 'admin')->value('id') ?? 1;
            $yaAsignado = DB::table('rol_modulo')->where('rol_id', $adminRol)->where('modulo_id', $moduloId)->exists();
            if ($adminRol && !$yaAsignado) {
                DB::table('rol_modulo')->insert([
                    'rol_id'          => $adminRol,
                    'modulo_id'       => $moduloId,
                    'puede_ver'       => true,
                    'puede_crear'     => true,
                    'puede_editar'    => true,
                    'puede_eliminar'  => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'notas_personales')->value('id');
        if ($moduloId) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
        }
        DB::table('modulos')->where('slug', 'notas_personales')->delete();
    }
};
