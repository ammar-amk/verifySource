<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class VerifySourceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/verifysource.php', 'verifysource'
        );
        
        $this->mergeConfigFrom(
            __DIR__.'/../../config/external_apis.php', 'external_apis'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->publishes([
            __DIR__.'/../../config/verifysource.php' => config_path('verifysource.php'),
            __DIR__.'/../../config/external_apis.php' => config_path('external_apis.php'),
        ], 'verifysource-config');
    }
}
