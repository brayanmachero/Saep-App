<?php

namespace Tests\Feature;

use Tests\TestCase;

class SaepDataStateComponentTest extends TestCase
{
    public function test_it_renders_a_safe_retryable_error_state(): void
    {
        $view = $this->blade(
            '<x-ui.data-state state="error" title="No se pudo sincronizar" message="Intenta nuevamente." retry-event="reload-inventory" />'
        );

        $view->assertSee('saep-data-state', false)
            ->assertSee('is-error', false)
            ->assertSee('No se pudo sincronizar')
            ->assertSee('Intenta nuevamente.')
            ->assertSee('data-saep-state-retry', false)
            ->assertSee('reload-inventory');
    }

    public function test_it_uses_loading_copy_and_accessibility_attributes(): void
    {
        $view = $this->blade('<x-ui.data-state state="loading" compact />');

        $view->assertSee('Cargando información')
            ->assertSee('aria-busy="true"', false)
            ->assertSee('saep-data-state--compact', false)
            ->assertSee('saep-data-state__spinner', false);
    }

    public function test_it_renders_an_empty_table_row_with_an_optional_next_action(): void
    {
        $view = $this->blade(
            '<table><tbody><x-ui.table-empty colspan="4" title="Sin clientes" message="Crea el primero." action-url="/clientes/crear" action-label="Crear cliente" /></tbody></table>'
        );

        $view->assertSee('colspan="4"', false)
            ->assertSee('Sin clientes')
            ->assertSee('Crea el primero.')
            ->assertSee('href="/clientes/crear"', false)
            ->assertSee('Crear cliente');
    }
}
