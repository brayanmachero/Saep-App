<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        // Las migraciones base ya crean usuario_id nullable con nullOnDelete.
    }

    public function down(): void
    {
        //
    }
};
