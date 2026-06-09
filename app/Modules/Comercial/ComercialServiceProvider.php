<?php

namespace App\Modules\Comercial;

use Illuminate\Support\ServiceProvider;

class ComercialServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/comercial.php', 'comercial');

        $this->app->register(ComercialEventServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        // Load views. Project-level views take precedence so the module can be themed with SAEP.
        $this->loadViewsFrom([
            resource_path('views/comercial'),
            __DIR__.'/resources/views',
        ], 'comercial');

        // Publish config
        $this->publishes([
            __DIR__.'/config/comercial.php' => config_path('comercial.php'),
        ], 'comercial-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/database/migrations' => database_path('migrations'),
        ], 'comercial-migrations');
    }
}
