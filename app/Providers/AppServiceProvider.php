<?php

namespace App\Providers;

use App\Services\ChatService;
use App\Services\CryptoService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CryptoService::class, function ($app) {
            return new CryptoService();
        });
        
        $this->app->singleton(ChatService::class, function ($app) {
            return new ChatService($app->make(CryptoService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enable model broadcasting
        Broadcast::routes();
    }
}
