<?php

declare(strict_types=1);

namespace App\Jobs\WordPress;

use App\Enums\ImportStatus;
use App\Exceptions\WordPressApiException;
use App\Models\Import;
use App\Services\WordPress\WordPressApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeWordPressMediaJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $backoff = 60; // 1 minute

    public function __construct(
        public readonly Import $import
    ) {}

    public function handle(): void
    {
        Log::info('Starting WordPress media analysis', [
            'import_id' => $this->import->id,
            'import_name' => $this->import->name,
        ]);

        try {
            // Mark import as running
            $this->import->markAsStarted();

            // Create WordPress API service
            $config = $this->import->config;
            $apiService = WordPressApiService::make(
                $config['wordpress_url'],
                $config['username'],
                $config['password']
            )->timeout(60)->retries(3);

            // Test connection first
            if (! $apiService->testConnection()) {
                throw new WordPressApiException('Failed to connect to WordPress site');
            }

            // Get media statistics
            $stats = $apiService->getMediaStatistics();
            $totalItems = $stats['total'];

            Log::info('WordPress media statistics', [
                'import_id' => $this->import->id,
                'total_items' => $totalItems,
                'by_type' => $stats['by_type'] ?? [],
            ]);

            // Update import with total items
            $this->import->update(['total_items' => $totalItems]);

            if ($totalItems === 0) {
                $this->import->markAsCompleted();
                Log::info('No media found in WordPress site', ['import_id' => $this->import->id]);
                return;
            }

            // Fetch all media and create import items with filtering
            $itemsCreated = 0;
            $itemsSkipped = 0;
            $batchSize = 50; // Process in batches for better performance
            $maxItems = $config['max_items'] ?? null;

            foreach ($apiService->getAllMedia() as $mediaData) {
                // Apply filters
                if (!$this->shouldImportMedia($mediaData, $config)) {
                    $itemsSkipped++;
                    continue;
                }
                
                // Check max items limit
                if ($maxItems && $itemsCreated >= $maxItems) {
                    Log::info('Reached maximum items limit', [
                        'import_id' => $this->import->id,
                        'max_items' => $maxItems,
                        'items_created' => $itemsCreated,
                    ]);
                    break;
                }

                // Create import item
                $this->import->items()->create([
                    'source_id' => (string) $mediaData['id'],
                    'source_url' => $mediaData['source_url'],
                    'title' => $mediaData['title'],
                    'source_metadata' => $mediaData,
                    'file_size' => $mediaData['file_size'],
                    'mime_type' => $mediaData['mime_type'],
                ]);

                $itemsCreated++;

                // Dispatch import jobs in batches
                if ($itemsCreated % $batchSize === 0) {
                    $this->dispatchPendingImportJobs();
                    
                    Log::info('Created batch of import items', [
                        'import_id' => $this->import->id,
                        'items_created' => $itemsCreated,
                        'total_items' => $totalItems,
                    ]);
                }

                // Check if import was cancelled
                $this->import->refresh();
                if ($this->import->status === ImportStatus::CANCELLED) {
                    Log::info('Import was cancelled during analysis', ['import_id' => $this->import->id]);
                    return;
                }
            }

            // Dispatch remaining import jobs
            if ($itemsCreated % $batchSize !== 0) {
                $this->dispatchPendingImportJobs();
            }

            Log::info('WordPress media analysis completed', [
                'import_id' => $this->import->id,
                'items_created' => $itemsCreated,
                'items_skipped' => $itemsSkipped,
                'total_items' => $totalItems,
            ]);

            // Update import with actual items to process
            $this->import->update([
                'total_items' => $itemsCreated, // Update with filtered count
                'summary' => [
                    'analysis_completed_at' => now()->toISOString(),
                    'total_items_discovered' => $itemsCreated,
                    'items_skipped_by_filters' => $itemsSkipped,
                    'original_total_items' => $totalItems,
                    'media_breakdown' => $stats['by_type'] ?? [],
                    'applied_filters' => $this->getAppliedFilters($config),
                ],
            ]);

        } catch (WordPressApiException $e) {
            Log::error('WordPress API error during analysis', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
                'endpoint' => $e->endpoint,
            ]);

            $this->import->markAsFailed($e->getMessage());
            throw $e;

        } catch (\Exception $e) {
            Log::error('Unexpected error during WordPress analysis', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->import->markAsFailed('Unexpected error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Dispatch import jobs for pending items
     */
    private function dispatchPendingImportJobs(): void
    {
        $pendingItems = $this->import->items()
            ->where('status', 'pending')
            ->limit(50)
            ->get();

        foreach ($pendingItems as $item) {
            // Check if import is still active
            $this->import->refresh();
            if (! $this->import->status->isActive()) {
                break;
            }

            ImportMediaItemJob::dispatch($item)
                ->onQueue('wordpress-import')
                ->delay(now()->addSeconds(rand(1, 5))); // Stagger jobs slightly
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WordPress media analysis job failed', [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->import->markAsFailed(
            'Media analysis failed: ' . $exception->getMessage()
        );
    }

    /**
     * Get unique job ID for preventing duplicates
     */
    public function uniqueId(): string
    {
        return "analyze-wp-media-{$this->import->id}";
    }

    /**
     * Determine retry delay
     */
    public function backoff(): array
    {
        return [60, 120, 300]; // 1 min, 2 min, 5 min
    }

    /**
     * Calculate job timeout based on expected items
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
    
    /**
     * Determine if media should be imported based on filters
     */
    private function shouldImportMedia(array $mediaData, array $config): bool
    {
        // Filter by media types
        $allowedTypes = $config['media_types'] ?? ['image', 'video', 'audio', 'document'];
        $mediaType = $this->getMediaTypeFromMimeType($mediaData['mime_type']);
        
        if (!in_array($mediaType, $allowedTypes)) {
            return false;
        }
        
        // Filter by date range
        if (!empty($config['from_date']) || !empty($config['to_date'])) {
            $mediaDate = \Carbon\Carbon::parse($mediaData['date']);
            
            if (!empty($config['from_date'])) {
                $fromDate = \Carbon\Carbon::parse($config['from_date']);
                if ($mediaDate->lt($fromDate)) {
                    return false;
                }
            }
            
            if (!empty($config['to_date'])) {
                $toDate = \Carbon\Carbon::parse($config['to_date']);
                if ($mediaDate->gt($toDate)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get media type from MIME type
     */
    private function getMediaTypeFromMimeType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video', 
            str_starts_with($mimeType, 'audio/') => 'audio',
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'text/csv'
            ]) => 'document',
            default => 'other'
        };
    }
    
    /**
     * Get summary of applied filters for logging
     */
    private function getAppliedFilters(array $config): array
    {
        $filters = [];
        
        if (!empty($config['media_types'])) {
            $filters['media_types'] = $config['media_types'];
        }
        
        if (!empty($config['from_date'])) {
            $filters['from_date'] = $config['from_date'];
        }
        
        if (!empty($config['to_date'])) {
            $filters['to_date'] = $config['to_date'];
        }
        
        if (!empty($config['max_items'])) {
            $filters['max_items'] = $config['max_items'];
        }
        
        return $filters;
    }
}