<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RetiredRecruitmentWhatsappTest extends TestCase
{
    public function test_retired_entrypoints_and_dispatch_are_absent_but_shared_modules_remain(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('reclutamiento/whatsapp', $route->uri());
        }
        $commands = Artisan::all();
        $this->assertArrayNotHasKey('whatsapp:despachar-campanias', $commands);
        $this->assertArrayNotHasKey('whatsapp:sync-plantillas', $commands);
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertStringNotContainsString('whatsapp:despachar-campanias', $event->command ?? '');
        }
        $this->assertTrue(Route::has('rrhh-documentos.index'));
        $this->assertTrue(Route::has('contratacion.index'));
        $this->assertTrue(Route::has('reclutamiento.catalogo'));
    }
}
