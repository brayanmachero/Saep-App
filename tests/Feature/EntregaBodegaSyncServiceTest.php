<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
use App\Models\User;
use App\Services\EntregaBodegaSyncService;
use App\Services\InventarioStockService;
use App\Services\KizeoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class EntregaBodegaSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('inventario_entrega_kizeo_aplicaciones');
        Schema::dropIfExists('entrega_bodega_items');
        Schema::dropIfExists('entregas_bodega');

        Schema::create('entregas_bodega', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 32);
            $table->string('kizeo_form_id', 50)->nullable();
            $table->string('origen_formulario', 120)->nullable();
            $table->string('tipo_operacion', 120)->nullable();
            $table->string('flujo_inventario', 20)->default('SALIDA');
            $table->string('estado_fuente', 40)->default('ACTIVA');
            $table->string('alerta_fuente', 500)->nullable();
            $table->timestamp('fuente_ausente_desde')->nullable();
            $table->unsignedInteger('kizeo_record_number')->nullable();
            $table->timestamp('kizeo_created_at')->nullable();
            $table->timestamp('kizeo_updated_at')->nullable();
            $table->string('registrado_por', 200)->nullable();
            $table->string('centro', 180)->nullable();
            $table->string('rut', 30)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->date('fecha_pedido')->nullable();
            $table->unsignedSmallInteger('lineas_count')->default(0);
            $table->unsignedInteger('unidades_total')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['kizeo_form_id', 'kizeo_data_id']);
        });

        Schema::create('entrega_bodega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_bodega_id')->constrained('entregas_bodega')->cascadeOnDelete();
            $table->unsignedSmallInteger('linea');
            $table->string('articulo', 200)->nullable();
            $table->string('talla', 80)->nullable();
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['entrega_bodega_id', 'linea']);
        });

        Schema::create('inventario_entrega_kizeo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entrega_bodega_id');
            $table->string('estado', 30)->default('APLICADA');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_syncs_the_two_current_kizeo_forms_and_extracts_the_size_from_the_article(): void
    {
        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')->once()->with('1196386', false)->andReturn([
            ['id' => 'mass-1', 'record_number' => 101, 'update_time' => '2026-08-17 11:49:05'],
        ]);
        $kizeo->shouldReceive('getFormData')->once()->with('1195951', false)->andReturn([
            ['id' => 'person-1', 'record_number' => 202, 'update_time' => '2026-08-17 11:47:01'],
            ['id' => 'return-1', 'record_number' => 203, 'update_time' => '2026-08-17 12:00:00'],
        ]);
        $kizeo->shouldReceive('getRecord')->once()->with('1196386', 'mass-1')->andReturn([
            'record_number' => 101,
            'create_time' => '2026-08-17 11:49:05',
            'fields' => [
                'centro_de_costo' => ['value' => 'Centro Norte'],
                'solicitud_de_' => ['value' => 'Reposición del centro'],
                'fecha_y_hora_de_despacho' => ['value' => '2026-08-17 10:30:00'],
                'tipo_de_solicitud' => ['value' => 'Stock de centro'],
                'epp' => ['value' => [[
                    'uniforme_y_epp' => ['value' => 'Casco Activex I Amarillo RAC T-NA'],
                    'cantidad' => ['value' => '4'],
                ]]],
            ],
        ]);
        $kizeo->shouldReceive('getRecord')->once()->with('1195951', 'person-1')->andReturn([
            'record_number' => 202,
            'create_time' => '2026-08-17 11:47:01',
            'fields' => [
                'centro_de_costo1' => ['value' => 'Centro Sur'],
                'rut' => ['value' => '11.111.111-1'],
                'nombre' => ['value' => 'Persona de prueba'],
                'fecha_del_pedido' => ['value' => '2026-08-17 09:00:00'],
                'tipo_de_ingreso' => ['value' => 'Ingreso de Personal'],
                'epi' => ['value' => [[
                    'concepto' => ['value' => 'Botin ST 435 Anticlavo TRECK T-42'],
                    'cantidad' => ['value' => '1'],
                ]]],
            ],
        ]);
        $kizeo->shouldReceive('getRecord')->once()->with('1195951', 'return-1')->andReturn([
            'record_number' => 203,
            'create_time' => '2026-08-17 12:00:00',
            'fields' => [
                'centro_de_costo1' => ['value' => 'Centro Sur'],
                'nombre' => ['value' => 'Persona de prueba'],
                'fecha_del_pedido' => ['value' => '2026-08-17 12:00:00'],
                'tipo_de_ingreso' => ['value' => 'Devolución PT. (entrada de inventario)'],
                'epi' => ['value' => [[
                    'concepto' => ['value' => 'Lente Spy Steelpro IN-Out URZKEN T-NA'],
                    'cantidad' => ['value' => '2'],
                ]]],
            ],
        ]);

        $summary = (new EntregaBodegaSyncService($kizeo))->sync(10);

        $this->assertSame(3, $summary['total_source']);
        $this->assertSame(3, $summary['created']);
        $this->assertSame(0, $summary['failed']);

        $mass = EntregaBodega::query()->with('items')->where('kizeo_data_id', 'mass-1')->firstOrFail();
        $this->assertSame('1196386', $mass->kizeo_form_id);
        $this->assertSame('Entrega de EPP Masiva - CD', $mass->origen_formulario);
        $this->assertSame('SALIDA', $mass->flujo_inventario);
        $this->assertSame('ACTIVA', $mass->estado_fuente);
        $this->assertSame('Casco Activex I Amarillo RAC', $mass->items->first()->articulo);
        $this->assertSame('NA', $mass->items->first()->talla);

        $person = EntregaBodega::query()->with('items')->where('kizeo_data_id', 'person-1')->firstOrFail();
        $this->assertSame('1195951', $person->kizeo_form_id);
        $this->assertSame('Botin ST 435 Anticlavo TRECK', $person->items->first()->articulo);
        $this->assertSame('42', $person->items->first()->talla);

        $return = EntregaBodega::query()->where('kizeo_data_id', 'return-1')->firstOrFail();
        $this->assertSame('ENTRADA', $return->flujo_inventario);
    }

    public function test_marks_a_missing_kizeo_response_as_blocked_without_adjusting_stock(): void
    {
        $delivery = EntregaBodega::create([
            'kizeo_form_id' => '1196386',
            'kizeo_data_id' => 'deleted-response',
            'estado_fuente' => 'ACTIVA',
            'flujo_inventario' => 'SALIDA',
        ]);

        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')->once()->with('1196386', false)->andReturn([]);
        $kizeo->shouldReceive('getFormData')->once()->with('1195951', false)->andReturn([]);

        $summary = (new EntregaBodegaSyncService($kizeo))->sync(10);
        $delivery->refresh();

        $this->assertSame(0, $summary['failed']);
        $this->assertSame('ELIMINADA_EN_KIZEO', $delivery->estado_fuente);
        $this->assertNotNull($delivery->fuente_ausente_desde);
        $this->assertStringContainsString('ya no está disponible', $delivery->alerta_fuente);
    }

    public function test_webhook_signal_synchronizes_its_record_from_kizeo_immediately(): void
    {
        $delivery = EntregaBodega::create([
            'kizeo_form_id' => '1196386',
            'kizeo_data_id' => 'webhook-response',
            'kizeo_updated_at' => '2026-08-17 10:00:00',
            'estado_fuente' => 'ACTIVA',
            'flujo_inventario' => 'SALIDA',
        ]);

        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getRecord')->once()->with('1196386', 'webhook-response')->andReturn([
            'record_number' => 303,
            'create_time' => '2026-08-17 10:00:00',
            'update_time' => '2026-08-17 12:00:00',
            'fields' => [
                'centro_de_costo' => ['value' => 'Centro Norte'],
                'solicitud_de_' => ['value' => 'Reposición de casco'],
                'fecha_y_hora_de_despacho' => ['value' => '2026-08-17 12:00:00'],
                'tipo_de_solicitud' => ['value' => 'Reposición'],
                'epp' => ['value' => [[
                    'uniforme_y_epp' => ['value' => 'Casco Activex I Amarillo RAC T-NA'],
                    'cantidad' => ['value' => '2'],
                ]]],
            ],
        ]);

        $synced = (new EntregaBodegaSyncService($kizeo))->syncSourceRecord('1196386', 'webhook-response', [
            'update_answer_time' => '2026-08-17 12:00:00',
        ]);

        $delivery->refresh();
        $this->assertSame($delivery->id, $synced->id);
        $this->assertSame(303, $delivery->kizeo_record_number);
        $this->assertSame('Reposición de casco', $delivery->nombre);
        $this->assertSame(2, $delivery->unidades_total);
        $this->assertSame('ACTIVA', $delivery->estado_fuente);
    }

    public function test_marks_an_applied_delivery_for_review_when_kizeo_changes_it(): void
    {
        $delivery = EntregaBodega::create([
            'kizeo_form_id' => '1196386',
            'kizeo_data_id' => 'changed-response',
            'kizeo_updated_at' => '2026-08-17 10:00:00',
            'estado_fuente' => 'ACTIVA',
            'flujo_inventario' => 'SALIDA',
        ]);
        \Illuminate\Support\Facades\DB::table('inventario_entrega_kizeo_aplicaciones')->insert([
            'entrega_bodega_id' => $delivery->id,
            'estado' => 'APLICADA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')->once()->with('1196386', false)->andReturn([
            ['id' => 'changed-response', 'record_number' => 400, 'update_time' => '2026-08-17 12:00:00'],
        ]);
        $kizeo->shouldReceive('getFormData')->once()->with('1195951', false)->andReturn([]);
        $kizeo->shouldReceive('getRecord')->once()->with('1196386', 'changed-response')->andReturn([
            'record_number' => 400,
            'create_time' => '2026-08-17 10:00:00',
            'update_time' => '2026-08-17 12:00:00',
            'fields' => [
                'centro_de_costo' => ['value' => 'Centro Norte'],
                'tipo_de_solicitud' => ['value' => 'Reposición'],
                'epp' => ['value' => [[
                    'uniforme_y_epp' => ['value' => 'Casco Activex I Amarillo RAC T-NA'],
                    'cantidad' => ['value' => '1'],
                ]]],
            ],
        ]);

        (new EntregaBodegaSyncService($kizeo))->sync(10);
        $delivery->refresh();

        $this->assertSame('REQUIERE_REVISION', $delivery->estado_fuente);
        $this->assertStringContainsString('actualizado en Kizeo', $delivery->alerta_fuente);
    }

    public function test_marks_an_applied_delivery_for_review_when_its_source_is_deleted(): void
    {
        $delivery = EntregaBodega::create([
            'kizeo_form_id' => '1195951',
            'kizeo_data_id' => 'deleted-after-application',
            'estado_fuente' => 'ACTIVA',
            'flujo_inventario' => 'SALIDA',
        ]);
        \Illuminate\Support\Facades\DB::table('inventario_entrega_kizeo_aplicaciones')->insert([
            'entrega_bodega_id' => $delivery->id,
            'estado' => 'APLICADA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')->once()->with('1196386', false)->andReturn([]);
        $kizeo->shouldReceive('getFormData')->once()->with('1195951', false)->andReturn([]);

        (new EntregaBodegaSyncService($kizeo))->sync(10);
        $delivery->refresh();

        $this->assertSame('REQUIERE_REVISION', $delivery->estado_fuente);
        $this->assertNotNull($delivery->fuente_ausente_desde);
        $this->assertStringContainsString('revérsala manualmente', $delivery->alerta_fuente);
    }

    public function test_restores_an_unapplied_delivery_when_its_kizeo_response_returns(): void
    {
        $delivery = EntregaBodega::create([
            'kizeo_form_id' => '1196386',
            'kizeo_data_id' => 'restored-response',
            'estado_fuente' => 'ELIMINADA_EN_KIZEO',
            'fuente_ausente_desde' => now()->subHour(),
            'flujo_inventario' => 'SALIDA',
        ]);

        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')->once()->with('1196386', false)->andReturn([
            ['id' => 'restored-response', 'record_number' => 500, 'update_time' => '2026-08-17 13:00:00'],
        ]);
        $kizeo->shouldReceive('getFormData')->once()->with('1195951', false)->andReturn([]);
        $kizeo->shouldReceive('getRecord')->once()->with('1196386', 'restored-response')->andReturn([
            'record_number' => 500,
            'create_time' => '2026-08-17 12:00:00',
            'update_time' => '2026-08-17 13:00:00',
            'fields' => [
                'centro_de_costo' => ['value' => 'Centro Norte'],
                'tipo_de_solicitud' => ['value' => 'Reposición'],
                'epp' => ['value' => [[
                    'uniforme_y_epp' => ['value' => 'Casco Activex I Amarillo RAC T-NA'],
                    'cantidad' => ['value' => '1'],
                ]]],
            ],
        ]);

        (new EntregaBodegaSyncService($kizeo))->sync(10);
        $delivery->refresh();

        $this->assertSame('ACTIVA', $delivery->estado_fuente);
        $this->assertNull($delivery->fuente_ausente_desde);
        $this->assertNull($delivery->alerta_fuente);
    }

    public function test_a_return_cannot_be_applied_as_a_stock_deduction(): void
    {
        $delivery = new EntregaBodega(['flujo_inventario' => 'ENTRADA']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('corresponde a una devolución');

        (new InventarioStockService)->applyKizeoDelivery($delivery, 1, [], new User);
    }

    public function test_a_removed_kizeo_source_cannot_be_applied_as_a_stock_deduction(): void
    {
        $delivery = new EntregaBodega([
            'flujo_inventario' => 'SALIDA',
            'estado_fuente' => 'ELIMINADA_EN_KIZEO',
            'alerta_fuente' => 'El registro ya no está disponible en Kizeo.',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ya no está disponible');

        (new InventarioStockService)->applyKizeoDelivery($delivery, 1, [], new User);
    }
}
