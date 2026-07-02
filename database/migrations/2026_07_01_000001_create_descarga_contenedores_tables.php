<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descarga_contenedor_cargas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160)->nullable();
            $table->string('origen', 40)->default('pegado');
            $table->unsignedInteger('filas_detectadas')->default(0);
            $table->unsignedInteger('filas_creadas')->default(0);
            $table->unsignedInteger('filas_con_alertas')->default(0);
            $table->json('raw_payload')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('descarga_contenedor_tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('cliente', 80)->default('WM');
            $table->string('codigo', 40);
            $table->string('proceso', 180);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->decimal('pago_colaborador', 12, 2)->nullable();
            $table->boolean('requiere_revision')->default(false);
            $table->string('observaciones', 400)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['cliente', 'codigo']);
        });

        Schema::create('descarga_contenedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->nullable()->constrained('descarga_contenedor_cargas')->nullOnDelete();
            $table->string('estado', 30)->default('borrador')->index();
            $table->string('origen', 40)->default('manual')->index();
            $table->string('operacion', 120)->nullable()->index();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
            $table->string('bodega', 160)->nullable()->index();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_nombre', 200)->nullable();
            $table->string('facturacion_mes', 60)->nullable();
            $table->date('fecha')->nullable()->index();
            $table->string('contenedor', 120)->nullable()->index();
            $table->string('equipo_descarga', 120)->nullable()->index();
            $table->time('hora_cita')->nullable();
            $table->time('hora_inicio_descarga')->nullable();
            $table->time('hora_termino_descarga')->nullable();
            $table->unsignedInteger('item')->nullable();
            $table->unsignedInteger('cajas')->nullable();
            $table->decimal('pallets', 10, 2)->nullable();
            $table->string('producto', 260)->nullable();
            $table->string('fact_codigo', 40)->nullable()->index();
            $table->text('observacion')->nullable();
            $table->json('raw_row')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('descarga_contenedor_participantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('descarga_contenedor_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre_snapshot', 220);
            $table->string('rut_snapshot', 30)->nullable();
            $table->string('cargo_snapshot', 200)->nullable();
            $table->unsignedBigInteger('centro_costo_id_snapshot')->nullable();
            $table->string('centro_costo_snapshot', 180)->nullable();
            $table->string('rol_en_descarga', 60)->default('descargador');
            $table->decimal('monto_calculado', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['descarga_contenedor_id', 'user_id'], 'dc_participantes_registro_user_idx');
            $table->foreign('descarga_contenedor_id', 'dc_participantes_descarga_fk')
                ->references('id')->on('descarga_contenedores')->cascadeOnDelete();
            $table->foreign('centro_costo_id_snapshot', 'dc_participantes_centro_snapshot_fk')
                ->references('id')->on('centros_costo')->nullOnDelete();
        });

        $this->seedTarifasIniciales();
    }

    public function down(): void
    {
        Schema::dropIfExists('descarga_contenedor_participantes');
        Schema::dropIfExists('descarga_contenedores');
        Schema::dropIfExists('descarga_contenedor_tarifas');
        Schema::dropIfExists('descarga_contenedor_cargas');
    }

    private function seedTarifasIniciales(): void
    {
        $now = now();
        $tarifas = [
            ['WM', 'ACE001', 'ACEITES', 75000, 36000, false, 'Lo que no se encuentre en esta glosa favor consultar.'],
            ['WM', 'BRTR001', 'DESCARGA BH/TR', 22000, 10560, false, null],
            ['WM', 'CAJ001', 'CAJA DE CARNE', 97, 36, false, null],
            ['WM', 'CAJ002', 'FERIADO/DOMINGO CAJA', 194, 72, false, null],
            ['WM', 'CNT000', 'SIN PRODUCCION', 0, 0, false, null],
            ['WM', 'CNT001', 'CONTENEDOR ESTANDAR', 75000, 36000, false, null],
            ['WM', 'CNT002', 'CONTENEDOR DOBLE', 150000, 36000, false, null],
            ['WM', 'CNT002', 'FERIADO/DOMINGO CONT', 150000, 72000, false, null],
            ['WM', 'CNT003', 'CONTENEDOR >4.000 CAJAS', 150000, 75000, false, 'Peñon/Quilicura es 4k'],
            ['WM', 'CNT004', 'CONTENEDOR >10 ITEMS', 150000, 75000, false, null],
            ['WM', 'CNT100', 'OTRAS FAENAS', null, null, true, 'Se revisa cotización'],
            ['WM', 'CNTMC001', 'CONTENEDORES 50 MC', 112500, 54000, false, null],
            ['WM', 'DESV001', 'DESVALIJADO X CAJA', 220, 105.6, false, null],
            ['WM', 'ETI001', 'ETIQUETADO X CAJA', 160, 76.8, false, null],
            ['WM', 'HUE001', 'HUEVOS 50 MC', 112500, 54000, false, null],
            ['WM', 'HUE002', 'HUEVOS 40 MC', 75000, 36000, false, null],
            ['WM', 'LEC001', 'LECHES', 75000, 36000, false, null],
            ['WM', 'PAS001', 'PASTAS', 75000, 36000, false, null],
            ['WM', 'REP001', 'REPALETIZADO X PALLET', 1400, 672, false, null],
            ['SMU', 'CNT001', 'CONTENEDOR ESTANDAR 20', 50000, 25500, false, 'Lo que no se encuentre en esta glosa favor consultar.'],
            ['SMU', 'CNT002', 'CONTENEDOR ESTANDAR 40', 70000, 36000, false, null],
            ['SMU', 'CNT003', 'CONTENEDOR ESTANDAR 50', 112500, 57375, false, null],
            ['SMU', 'CNT004', 'FERIADO/DOMINGO CONT', 150000, 72000, false, null],
            ['SMU', 'REP001', 'REPALETIZADO POR PALLET', 1400, 714, false, null],
        ];

        foreach ($tarifas as [$cliente, $codigo, $proceso, $costo, $pago, $requiereRevision, $observaciones]) {
            DB::table('descarga_contenedor_tarifas')->insert([
                'cliente' => $cliente,
                'codigo' => $codigo,
                'proceso' => $proceso,
                'costo_unitario' => $costo,
                'pago_colaborador' => $pago,
                'requiere_revision' => $requiereRevision,
                'observaciones' => $observaciones,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
