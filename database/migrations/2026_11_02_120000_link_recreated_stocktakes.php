<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_conteos', function (Blueprint $table) {
            $table->foreignId('conteo_origen_id')->nullable()->constrained('inventario_conteos')->restrictOnDelete();
        });
        foreach (DB::table('inventario_conteos')->get() as $count) {
            if (preg_match('/^Recreado desde (CNT-[A-Z0-9-]+) /', $count->observacion ?? '', $match)) {
                $source = DB::table('inventario_conteos')->where('codigo', $match[1])->value('id');
                if ($source && $source !== $count->id) {
                    DB::table('inventario_conteos')->where('id', $count->id)->update(['conteo_origen_id' => $source]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('inventario_conteos', fn (Blueprint $table) => $table->dropConstrainedForeignId('conteo_origen_id'));
    }
};
