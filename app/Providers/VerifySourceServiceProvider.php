<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        
        $this->publishes([
            __DIR__.'/../../config/verifysource.php' => config_path('verifysource.php'),
        ], 'verifysource-config');
    }
}
