<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Upload\UploadActionRegistry;
use App\Services\UploadPipelineService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for upload-related services
 */
class UploadServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->app->singleton(UploadActionRegistry::class);
        
        $this->app->singleton(UploadPipelineService::class, function ($app) {
            return new UploadPipelineService(
                $app->make(UploadActionRegistry::class)
            );
        });

        // Register the pipeline service with a friendly alias
        $this->app->alias(UploadPipelineService::class, 'upload.pipeline');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Actions will be auto-discovered by the registry
        // No need to manually register them here
    }
}