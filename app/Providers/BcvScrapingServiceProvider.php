<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\BcvScrapingService;

class BcvScrapingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar el servicio de scraping como singleton
        $this->app->singleton(BcvScrapingService::class, function ($app) {
            return new BcvScrapingService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
