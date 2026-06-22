<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        // La migracion base ya crea actualizado_por nullable con nullOnDelete.
    }

    public function down(): void
    {
        //
    }
};
