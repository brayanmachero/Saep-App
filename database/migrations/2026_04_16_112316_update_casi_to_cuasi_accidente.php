<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accidentes_sst')
            ->whereRaw("LOWER(tipo) = 'casi_accidente'")
            ->update(['tipo' => 'cuasi_accidente']);
    }

    public function down(): void
    {
        DB::table('accidentes_sst')
            ->where('tipo', 'cuasi_accidente')
            ->update(['tipo' => 'casi_accidente']);
    }
};
