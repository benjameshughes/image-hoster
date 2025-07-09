<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // WordPress Import Rate Limits
        RateLimiter::for('wordpress-connection-test', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(10)->by($request->user()->id)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many connection attempts. Please wait before testing again.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                })
                : Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('wordpress-import-start', function (Request $request) {
            return $request->user()
                ? Limit::perHour(3)->by($request->user()->id)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Import limit reached. You can start 3 imports per hour.',
                        'retry_after' => $headers['Retry-After'] ?? 3600,
                    ], 429, $headers);
                })
                : Limit::perHour(1)->by($request->ip());
        });

        RateLimiter::for('wordpress-import-analysis', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(5)->by($request->user()->id)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many analysis requests. Please wait before analyzing again.',
                        'retry_after' => $headers['Retry-After'] ?? 120,
                    ], 429, $headers);
                })
                : Limit::perMinute(2)->by($request->ip());
        });

        // Upload Rate Limits
        RateLimiter::for('file-upload-session', function (Request $request) {
            return $request->user()
                ? Limit::perHour(20)->by($request->user()->id)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Upload session limit reached. You can start 20 upload sessions per hour.',
                        'retry_after' => $headers['Retry-After'] ?? 180,
                    ], 429, $headers);
                })
                : Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('file-upload-batch', function (Request $request) {
            return $request->user()
                ? [
                    Limit::perMinute(50)->by($request->user()->id),
                    Limit::perHour(200)->by($request->user()->id),
                ]->map(function ($limit) {
                    return $limit->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Upload limit reached. Please wait before uploading more files.',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    });
                })
                : [
                    Limit::perMinute(10)->by($request->ip()),
                    Limit::perHour(50)->by($request->ip()),
                ];
        });

        // General API rate limits for Livewire components
        RateLimiter::for('livewire-actions', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                })
                : Limit::perMinute(20)->by($request->ip());
        });

        // Stricter limits for expensive operations
        RateLimiter::for('expensive-operations', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(10)->by($request->user()->id)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Rate limit for resource-intensive operations exceeded.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                })
                : Limit::perMinute(3)->by($request->ip());
        });
    }
}
