<?php

namespace App\Modules\Comercial;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class ComercialServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/comercial.php', 'comercial');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Load routes inside the web stack so auth can read the browser session.
        Route::middleware('web')->group(__DIR__.'/routes/web.php');

        // Load views. Project-level views take precedence so the module can be themed with SAEP.
        $viewPaths = [resource_path('views/comercial')];
        $moduleViewPath = __DIR__.'/resources/views';

        if (is_dir($moduleViewPath)) {
            $viewPaths[] = $moduleViewPath;
        }

        $this->loadViewsFrom($viewPaths, 'comercial');

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
