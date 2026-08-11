<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_vehiculo_motivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200)->unique();
            $table->unsignedSmallInteger('orden')->default(100);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });

        $now = now();
        foreach ([
            'Traslado a centro de trabajo',
            'Retiro o entrega de materiales',
            'Visita en terreno',
            'Gestion administrativa',
            'Otro traslado operativo',
        ] as $orden => $nombre) {
            DB::table('reserva_vehiculo_motivos')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'orden' => ($orden + 1) * 10,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_vehiculo_motivos');
    }
};
