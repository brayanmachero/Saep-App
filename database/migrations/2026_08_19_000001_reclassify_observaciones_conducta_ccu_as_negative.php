<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('observaciones_conducta_ccu')) {
            return;
        }

        DB::table('observaciones_conducta_ccu')
            ->whereNotNull('tipo_observacion')
            ->where('tipo_observacion', '!=', '')
            ->update(['clasificacion' => 'Negativa']);

        DB::table('observaciones_conducta_ccu')
            ->where(function ($query) {
                $query->whereNull('tipo_observacion')
                    ->orWhere('tipo_observacion', '');
            })
            ->update(['clasificacion' => 'Por revisar']);
    }

    public function down(): void
    {
        // No se revierte: la clasificación anterior (SIEMPRE = positiva) era incorrecta.
    }
};
