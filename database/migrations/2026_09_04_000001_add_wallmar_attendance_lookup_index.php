<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talana_marcas', function (Blueprint $table): void {
            // La API siempre busca por centro y rango de fechas; evita escanear
            // el historial completo al paginar los reportes del cliente.
            $table->index(['centro_costo_nombre', 'fecha'], 'talana_marcas_center_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('talana_marcas', function (Blueprint $table): void {
            $table->dropIndex('talana_marcas_center_date_index');
        });
    }
};
