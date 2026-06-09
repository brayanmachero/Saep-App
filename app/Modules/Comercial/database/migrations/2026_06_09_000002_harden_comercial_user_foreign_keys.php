<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setUserForeignKeyToNullOnDelete('comercial_cotizaciones');
        $this->setUserForeignKeyToNullOnDelete('comercial_cotizacion_auditorias');
    }

    public function down(): void
    {
        $this->setUserForeignKeyToCascade('comercial_cotizaciones');
        $this->setUserForeignKeyToCascade('comercial_cotizacion_auditorias');
    }

    private function setUserForeignKeyToNullOnDelete(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'usuario_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $table->dropForeign("{$tableName}_usuario_id_foreign");
            $table->foreignId('usuario_id')->nullable()->change();
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function setUserForeignKeyToCascade(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'usuario_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $table->dropForeign("{$tableName}_usuario_id_foreign");
            $table->foreignId('usuario_id')->nullable(false)->change();
            $table->foreign('usuario_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
