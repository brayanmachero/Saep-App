<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comercial_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->string('titulo')->nullable();
            $table->string('cargo')->nullable();
            $table->foreignId('cliente_id')->constrained('comercial_clientes')->cascadeOnDelete();
            $table->foreignId('centro_costo_id')->constrained('comercial_centros_costo')->cascadeOnDelete();
            $table->foreignId('modalidad_id')->constrained('comercial_modalidades')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('estado', ['en_cotizacion', 'aprobada', 'vigente', 'no_vigente', 'rechazada', 'cancelada'])->default('en_cotizacion');
            $table->integer('version')->default(1);
            $table->foreignId('cotizacion_anterior_id')->nullable()->constrained('comercial_cotizaciones')->nullOnDelete();

            $table->date('fecha_cotizacion');
            $table->date('fecha_vigencia_desde');
            $table->date('fecha_vigencia_hasta');
            $table->text('observaciones')->nullable();

            $table->decimal('total_remuneraciones', 15, 2);
            $table->decimal('total_cotizaciones', 15, 2);
            $table->decimal('total_provisiones', 15, 2);
            $table->decimal('total_gastos', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('margen', 15, 2);
            $table->decimal('precio_venta', 15, 2);

            $table->json('datos_calculo')->comment('Almacena todos los cálculos y fórmulas aplicadas');
            $table->json('detalles_json')->nullable()->comment('Copia de detalles para búsqueda rápida');

            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamp('fecha_vigencia')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('numero');
            $table->index('cliente_id');
            $table->index('estado');
            $table->index('fecha_cotizacion');
            $table->index('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_cotizaciones');
    }
};
