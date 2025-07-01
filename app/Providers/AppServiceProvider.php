<?php

namespace App\Providers;

use App\Services\UploaderService;
use App\Services\ImageProcessingService;
use App\Services\ProgressTrackingFilesystemManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('upload', function ($app) {
            return $app->make(UploaderService::class);
        });
        
        $this->app->singleton(ImageProcessingService::class);
        $this->app->singleton(ProgressTrackingFilesystemManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
