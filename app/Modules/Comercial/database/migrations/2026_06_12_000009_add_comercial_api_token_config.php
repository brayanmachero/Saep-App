<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        $actual = DB::table('configuraciones')->where('clave', 'comercial_api_token')->first();

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'comercial_api_token'],
            [
                'valor' => $actual?->valor ?: Str::random(64),
                'tipo' => 'PASSWORD',
                'categoria' => 'integraciones',
                'descripcion' => 'Token para consultar tarifas comerciales vigentes desde Excel Power Query u otras integraciones.',
                'editable' => true,
                'updated_at' => now(),
                'created_at' => $actual?->created_at ?: now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        DB::table('configuraciones')->where('clave', 'comercial_api_token')->delete();
    }
};
