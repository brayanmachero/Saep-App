<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->where('slug', 'reclutamiento_whatsapp')->update(['activo' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('modulos')->where('slug', 'reclutamiento_whatsapp')->update(['activo' => true, 'updated_at' => now()]);
    }
};
