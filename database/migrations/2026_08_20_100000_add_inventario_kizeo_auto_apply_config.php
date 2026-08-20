<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'inventario_kizeo_auto_aplicar'],
            [
                'valor' => '0',
                'tipo' => 'BOOLEAN',
                'categoria' => 'inventario',
                'descripcion' => 'Descontar automáticamente del stock de Sede Central las entregas NUEVAS de Kizeo. El histórico no se aplica al activar.',
                'editable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'inventario_kizeo_auto_aplicar_desde'],
            [
                'valor' => '',
                'tipo' => 'TEXT',
                'categoria' => 'inventario',
                'descripcion' => 'Fecha y hora desde la cual una entrega Kizeo se considera nueva para el descuento automático.',
                'editable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'inventario_kizeo_auto_aplicar_por'],
            [
                'valor' => '',
                'tipo' => 'TEXT',
                'categoria' => 'inventario',
                'descripcion' => 'Usuario que activó o desactivó el descuento automático de Kizeo.',
                'editable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('configuraciones')->whereIn('clave', [
            'inventario_kizeo_auto_aplicar',
            'inventario_kizeo_auto_aplicar_desde',
            'inventario_kizeo_auto_aplicar_por',
        ])->delete();
    }
};
