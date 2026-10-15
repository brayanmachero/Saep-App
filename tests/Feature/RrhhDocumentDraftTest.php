<?php

namespace Tests\Feature;

use App\Models\TalanaContrato;
use App\Services\RrhhDocumentDraftService;
use App\Services\RrhhDocumentReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RrhhDocumentDraftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        foreach (['rrhh_document_batch_events', 'rrhh_document_batch_items', 'rrhh_document_batches', 'rrhh_document_batteries', 'talana_contratos', 'modulos'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('modulos', function (Blueprint $t) {
            $t->id();
            foreach (['slug', 'nombre', 'descripcion', 'icono', 'grupo'] as $f) {
                $t->string($f);
            }$t->integer('orden');
            $t->boolean('activo');
            $t->timestamps();
        });
        Schema::create('talana_contratos', function (Blueprint $t) {
            $t->id();
            foreach (['talana_id', 'persona_talana_id', 'empresa_id'] as $f) {
                $t->integer($f);
            }foreach (['persona_nombre', 'persona_rut', 'empresa_nombre', 'cargo_nombre', 'centro_costo_nombre', 'tipo_contrato_nombre'] as $f) {
                $t->string($f)->nullable();
            }$t->boolean('finiquitado');
            $t->date('desde')->nullable();
            $t->date('hasta')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_15_200000_create_rrhh_document_drafts.php'))->up();
        (require base_path('database/migrations/2026_10_15_210000_add_document_batch_review.php'))->up();
        DB::table('rrhh_document_batteries')->insert(['id' => 1, 'nombre' => 'Prueba', 'version' => 1, 'documentos' => '["Contrato","Anexo"]', 'reglas' => '{}', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function contract(int $n, array $extra = []): TalanaContrato
    {
        return TalanaContrato::create(array_merge(['talana_id' => $n, 'persona_talana_id' => $n, 'empresa_id' => 1039, 'empresa_nombre' => 'SAEP SUB', 'persona_nombre' => 'Persona '.$n, 'persona_rut' => '12345678-9', 'cargo_nombre' => 'Operario', 'centro_costo_nombre' => 'CCU', 'tipo_contrato_nombre' => 'Indefinido', 'finiquitado' => false, 'desde' => today()->subMonth(), 'synced_at' => now()], $extra));
    }

    private function data(array $extra = []): array
    {
        return array_merge(['nombre' => 'Lote prueba', 'request_id' => (string) Str::uuid(), 'battery_id' => 1, 'seleccion' => 'todos'], $extra);
    }

    public function test_selects_all_results_beyond_page_and_excludes_expired_future_terminated(): void
    {
        for ($i = 1; $i <= 65; $i++) {
            $this->contract($i);
        }
        $this->contract(66, ['finiquitado' => true]);
        $this->contract(67, ['hasta' => today()->subDay()]);
        $this->contract(68, ['desde' => today()->addDay()]);
        $service = new RrhhDocumentDraftService;
        $data = $this->data();
        $id = $service->create($data, 1);
        $batch = DB::table('rrhh_document_batches')->find($id);
        $this->assertSame(65, $batch->trabajadores);
        $this->assertSame(130, $batch->documentos);
        $this->assertSame(0, $batch->observados);
        $this->assertSame(65, DB::table('rrhh_document_batch_items')->count());
        $this->assertSame($id, $service->create($data, 1));
        $this->assertSame(1, DB::table('rrhh_document_batches')->count());
        Http::assertNothingSent();
    }

    public function test_manual_selection_cannot_include_other_filter_results(): void
    {
        $c = $this->contract(1, ['empresa_id' => 1081]);
        $this->expectException(ValidationException::class);
        (new RrhhDocumentDraftService)->create($this->data(['seleccion' => 'manual', 'contratos' => [$c->id], 'empresa_id' => 1039]), 1);
    }

    public function test_snapshot_rules_stale_data_and_duplicate_battery_are_visible(): void
    {
        $c = $this->contract(1, ['synced_at' => now()->subDays(3)]);
        DB::table('rrhh_document_batteries')->where('id', 1)->update(['reglas' => '{"cargo_nombre":"Supervisor"}']);
        $s = new RrhhDocumentDraftService;
        $id = $s->create($this->data(), 1);
        $batch = DB::table('rrhh_document_batches')->find($id);
        $this->assertSame(1, $batch->observados);
        $item = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->first();
        $this->assertCount(2, json_decode($item->observaciones, true));
        $c->update(['persona_nombre' => 'Nombre cambiado']);
        DB::table('rrhh_document_batteries')->where('id', 1)->update(['documentos' => '["Otra definición"]']);
        $this->assertSame('Persona 1', json_decode($item->snapshot, true)['persona_nombre']);
        $this->assertCount(2, json_decode($batch->battery_snapshot, true)['documentos']);
        $second = $s->create($this->data(), 1);
        $this->assertStringContainsString('otro borrador', DB::table('rrhh_document_batch_items')->where('batch_id', $second)->value('observaciones'));
        Http::assertNothingSent();
    }

    public function test_review_preserves_snapshot_recalculates_and_locks_cancelled_batch(): void
    {
        $this->contract(1);
        $this->contract(2, ['synced_at' => now()->subDays(3)]);
        $id = (new RrhhDocumentDraftService)->create($this->data(), 1);
        $item = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->orderByDesc('id')->first();
        $review = new RrhhDocumentReviewService;
        $review->update($id, ['accion' => 'retirar', 'item_id' => $item->id, 'motivo' => 'Fuera de alcance'], 1);
        $b = DB::table('rrhh_document_batches')->find($id);
        $this->assertSame(1, $b->trabajadores);
        $this->assertSame(2, $b->documentos);
        $this->assertSame(0, $b->observados);
        $this->assertSame($item->snapshot, DB::table('rrhh_document_batch_items')->find($item->id)->snapshot);
        $review->update($id, ['accion' => 'restaurar', 'item_id' => $item->id, 'motivo' => 'Corresponde al lote'], 1);
        $this->assertSame(2, DB::table('rrhh_document_batches')->find($id)->trabajadores);
        $review->update($id, ['accion' => 'cancelar', 'motivo' => 'Solicitud cancelada'], 1);
        $this->assertSame('CANCELADO', DB::table('rrhh_document_batches')->find($id)->estado);
        $this->assertSame(3, DB::table('rrhh_document_batch_events')->count());
        $this->expectException(ValidationException::class);
        $review->update($id, ['accion' => 'retirar', 'item_id' => $item->id, 'motivo' => 'No permitido'], 1);
    }

    public function test_cannot_change_item_from_another_batch(): void
    {
        $this->contract(1);
        $service = new RrhhDocumentDraftService;
        $id = $service->create($this->data(), 1);
        $other = $service->create($this->data(), 1);
        $item = DB::table('rrhh_document_batch_items')->where('batch_id', $other)->first();
        try {
            (new RrhhDocumentReviewService)->update($id, ['accion' => 'retirar', 'item_id' => $item->id, 'motivo' => 'Otro lote'], 1);
            $this->fail('Expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertSame(0,DB::table('rrhh_document_batch_events')->count());
    }
}
