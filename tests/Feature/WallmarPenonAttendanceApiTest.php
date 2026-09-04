<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WallmarPenonAttendanceApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.wallmar_attendance', [
            'api_key' => 'wallmar-test-key',
            'center_codes' => ['LTS PEÑON EST', 'LTS FLEX PEÑON EST'],
            'center_label' => 'LTS FLEX PEÑON EST',
            'minimum_date' => '2026-08-01',
            'max_days_per_request' => 31,
            'max_page_size' => 100,
        ]);

        Schema::create('talana_marcas', function (Blueprint $table): void {
            $table->id();
            $table->integer('persona_talana_id');
            $table->string('persona_nombre', 200)->nullable();
            $table->string('persona_rut', 20)->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('tipo', 5)->nullable();
            $table->string('centro_costo_nombre', 150)->nullable();
            $table->timestamp('raw_ts')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('talana_marcas');

        parent::tearDown();
    }

    public function test_requires_a_dedicated_api_key(): void
    {
        $this->getJson('/api/v1/wallmar/penon/asistencia?desde=2026-09-01&hasta=2026-09-01')
            ->assertUnauthorized();
    }

    public function test_exposes_only_penon_marks_and_normalizes_the_response(): void
    {
        $this->createMark([
            'persona_rut' => '21060862-1',
            'persona_nombre' => 'Benjamín Alejandro Orellana',
            'centro_costo_nombre' => 'LTS PEÑON EST',
            'fecha' => '2026-09-03',
            'hora' => '05:57:31',
            'tipo' => 'E',
        ]);
        $this->createMark([
            'persona_talana_id' => 999,
            'centro_costo_nombre' => 'LTS QUILICURA EST',
            'fecha' => '2026-09-03',
            'hora' => '08:00:00',
        ]);

        $this->withHeader('X-SAEP-API-Key', 'wallmar-test-key')
            ->getJson('/api/v1/wallmar/penon/asistencia?desde=2026-09-03&hasta=2026-09-03')
            ->assertOk()
            ->assertJsonPath('meta.solo_lectura', true)
            ->assertJsonPath('meta.total_registros', 1)
            ->assertJsonPath('data.0.rut', '21060862-1')
            ->assertJsonPath('data.0.centro_costo', 'LTS FLEX PEÑON EST')
            ->assertJsonPath('data.0.direccion', 'Entrada')
            ->assertJsonMissing(['persona_talana_id']);
    }

    public function test_rejects_ranges_before_august_and_over_the_limit(): void
    {
        $this->withHeader('X-SAEP-API-Key', 'wallmar-test-key')
            ->getJson('/api/v1/wallmar/penon/asistencia?desde=2026-07-31&hasta=2026-08-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('desde');

        $this->withHeader('X-SAEP-API-Key', 'wallmar-test-key')
            ->getJson('/api/v1/wallmar/penon/asistencia?desde=2026-08-01&hasta=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hasta');
    }

    private function createMark(array $attributes = []): void
    {
        DB::table('talana_marcas')->insert(array_merge([
            'persona_talana_id' => 123,
            'persona_rut' => '11111111-1',
            'persona_nombre' => 'Persona de prueba',
            'fecha' => '2026-09-03',
            'hora' => '14:50:02',
            'tipo' => 'S',
            'centro_costo_nombre' => 'LTS PEÑON EST',
            'raw_ts' => '2026-09-03 14:50:02',
            'synced_at' => '2026-09-04 06:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
