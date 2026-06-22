<?php

use App\Modules\Comercial\database\seeders\ComercialSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comercial_modalidades') || ! Schema::hasTable('comercial_parametros')) {
            return;
        }

        (new ComercialSeeder())->run();
    }

    public function down(): void
    {
        //
    }
};
