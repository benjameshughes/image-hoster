<?php

declare(strict_types=1);

namespace App\Jobs\WordPress;

use App\Enums\MediaType;
use App\Exceptions\WordPressApiException;
use App\Models\ImportItem;
use App\Models\Media;
use App\Services\UploaderService;
use App\Services\WordPress\WordPressApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportMediaItemJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 180; // 3 minutes per file
    public int $tries = 3;
    public int $maxExceptions = 3;

    public function __construct(
        public readonly ImportItem $importItem
    ) {}

    public function handle(): void
    {
        Log::info('Starting media item import', [
            'import_item_id' => $this->importItem->id,
            'import_id' => $this->importItem->import_id,
            'source_id' => $this->importItem->source_id,
            'source_url' => $this->importItem->source_url,
        ]);

        try {
            // Mark item as processing
            $this->importItem->markAsProcessing();

            // Check if import is still active
            $import = $this->importItem->import;
            if (! $import->status->isActive()) {
                Log::info('Import is no longer active, skipping item', [
                    'import_item_id' => $this->importItem->id,
                    'import_status' => $import->status->value,
                ]);
                return;
            }

            // Create WordPress API service
            $config = $import->config;
            $apiService = WordPressApiService::make(
                $config['wordpress_url'],
                $config['username'],
                $config['password']
            )->timeout(120)->retries(2);

            // Download the file from WordPress
            $downloadResult = $apiService->downloadMediaFile(
                $this->importItem->source_url,
                $this->extractFilenameFromMetadata()
            );

            Log::info('File downloaded successfully', [
                'import_item_id' => $this->importItem->id,
                'temp_path' => $downloadResult['temp_path'],
                'file_size' => $downloadResult['size'],
                'mime_type' => $downloadResult['mime_type'],
            ]);

            // Create UploadedFile instance from the downloaded file
            $uploadedFile = $this->createUploadedFileFromTemp($downloadResult);

            // Handle duplicates based on strategy
            $config = $import->config;
            $duplicateStrategy = $config['duplicate_strategy'] ?? 'skip';
            
            if ($duplicateStrategy !== 'rename') {
                $hash = hash_file('sha256', $uploadedFile->getRealPath());
                $existingMedia = Media::where('file_hash', $hash)
                    ->where('user_id', $import->user_id)
                    ->first();

                if ($existingMedia) {
                    if ($duplicateStrategy === 'skip') {
                        Log::info('Exact duplicate found, skipping import', [
                            'import_item_id' => $this->importItem->id,
                            'existing_media_id' => $existingMedia->id,
                            'file_hash' => $hash,
                        ]);

                        $this->importItem->markAsCompleted($existingMedia->id);
                        $import->incrementProcessed(successful: true, duplicate: true);
                        
                        // Clean up temp file
                        $apiService->cleanupTempFile($downloadResult['temp_path']);
                        return;
                    } elseif ($duplicateStrategy === 'replace') {
                        // Delete existing media and continue with import
                        Log::info('Replacing existing duplicate media', [
                            'import_item_id' => $this->importItem->id,
                            'existing_media_id' => $existingMedia->id,
                            'file_hash' => $hash,
                        ]);
                        
                        $existingMedia->delete();
                    }
                }
            }

            // Use UploaderService to store the file
            $uploader = UploaderService::forUser($import->user_id)
                ->file($uploadedFile)
                ->disk($config['storage_disk'] ?? 'spaces')
                ->directory($this->generateStorageDirectory())
                ->useOriginalFilename()
                ->checkDuplicates($config['skip_duplicates'] ?? true)
                ->extractMetadata(true)
                ->processImages($config['process_images'] ?? false)
                ->generateThumbnails($config['generate_thumbnails'] ?? false)
                ->compressImages($config['compress_images'] ?? false)
                ->allowAllMimeTypes(); // Allow all MIME types for WordPress imports

            // Configure for different media types and apply naming strategy
            $mediaType = MediaType::fromMimeType($uploadedFile->getMimeType());
            if ($mediaType === MediaType::IMAGE) {
                $uploader->allowedImageTypes(...array_values(\App\Enums\AllowedImageType::cases()));
            }
            
            // Apply filename strategy for duplicates
            if ($duplicateStrategy === 'rename') {
                $uploader->checkDuplicates(false); // Let the system handle naming
            }

            // Process the upload
            $uploadResult = $uploader->process();

            // Update the media record with WordPress metadata
            $media = $uploadResult['record'];
            $this->updateMediaWithWordPressData($media, $uploadResult);

            Log::info('Media item imported successfully', [
                'import_item_id' => $this->importItem->id,
                'media_id' => $media->id,
                'file_path' => $uploadResult['path'],
            ]);

            // Mark item as completed
            $this->importItem->markAsCompleted($media->id);
            $import->incrementProcessed(successful: true);

            // Dispatch duplicate detection job
            DetectDuplicatesJob::dispatch($media)
                ->onQueue('duplicate-detection')
                ->delay(now()->addSeconds(rand(5, 15)));

            // Clean up temp file
            $apiService->cleanupTempFile($downloadResult['temp_path']);

        } catch (WordPressApiException $e) {
            Log::error('WordPress API error importing media item', [
                'import_item_id' => $this->importItem->id,
                'error' => $e->getMessage(),
                'endpoint' => $e->endpoint,
            ]);

            $this->handleImportFailure($e->getMessage());

        } catch (\Exception $e) {
            Log::error('Unexpected error importing media item', [
                'import_item_id' => $this->importItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleImportFailure('Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Handle import failure
     */
    private function handleImportFailure(string $error): void
    {
        $this->importItem->markAsFailed($error);
        $this->importItem->import->incrementProcessed(successful: false);

        // If too many retries, mark as failed
        if ($this->attempts() >= $this->tries) {
            Log::warning('Media item import failed after all retries', [
                'import_item_id' => $this->importItem->id,
                'attempts' => $this->attempts(),
                'final_error' => $error,
            ]);
        }
    }

    /**
     * Extract filename from WordPress metadata
     */
    private function extractFilenameFromMetadata(): ?string
    {
        $metadata = $this->importItem->source_metadata;
        
        // Try to get original filename from media_details
        if (isset($metadata['media_details']['file'])) {
            return basename($metadata['media_details']['file']);
        }

        // Fallback to source URL
        if (isset($metadata['source_url'])) {
            return basename(parse_url($metadata['source_url'], PHP_URL_PATH));
        }

        return null;
    }

    /**
     * Create UploadedFile from downloaded temp file
     */
    private function createUploadedFileFromTemp(array $downloadResult): UploadedFile
    {
        $tempPath = Storage::disk('local')->path($downloadResult['temp_path']);
        
        return new UploadedFile(
            path: $tempPath,
            originalName: $downloadResult['filename'],
            mimeType: $downloadResult['mime_type'],
            error: UPLOAD_ERR_OK, // Explicitly set to OK
            test: true // Mark as test to bypass some validation
        );
    }

    /**
     * Generate storage directory for the file
     */
    private function generateStorageDirectory(): string
    {
        $import = $this->importItem->import;
        $config = $import->config;
        
        // Get the custom path pattern or use default
        $pathPattern = $config['import_path'] ?? 'wordpress/{year}/{month}';
        
        // Get media upload date from WordPress metadata or use current date
        $metadata = $this->importItem->source_metadata;
        $uploadDate = isset($metadata['date']) 
            ? \Carbon\Carbon::parse($metadata['date'])
            : now();
        
        // Replace placeholders in the path pattern
        $directory = str_replace([
            '{year}',
            '{month}',
            '{day}',
            '{user_id}',
        ], [
            $uploadDate->format('Y'),
            $uploadDate->format('m'),
            $uploadDate->format('d'),
            $import->user_id,
        ], $pathPattern);
        
        return "imports/{$import->user_id}/{$directory}";
    }

    /**
     * Update media record with WordPress-specific data
     */
    private function updateMediaWithWordPressData(Media $media, array $uploadResult): void
    {
        $metadata = $this->importItem->source_metadata;
        
        $updates = [
            'source' => 'wordpress',
            'source_id' => $this->importItem->source_id,
            'source_metadata' => $metadata,
            'alt_text' => $metadata['alt_text'] ?? null,
            'description' => $this->extractDescription($metadata),
            'tags' => $this->extractTags($metadata),
        ];

        // Add WordPress-specific metadata
        if (isset($metadata['caption']['rendered'])) {
            $updates['metadata'] = array_merge(
                $uploadResult['metadata'] ?? [],
                [
                    'wordpress' => [
                        'post_id' => $metadata['id'],
                        'caption' => $metadata['caption']['rendered'],
                        'link' => $metadata['link'] ?? null,
                        'date_uploaded' => $metadata['date'] ?? null,
                        'author_id' => $metadata['author'] ?? null,
                    ],
                ]
            );
        }

        // Handle media-specific fields
        $mediaType = MediaType::fromMimeType($metadata['mime_type']);
        
        if ($mediaType === MediaType::VIDEO && isset($metadata['media_details']['length'])) {
            $updates['duration'] = $metadata['media_details']['length'];
        }

        if ($mediaType === MediaType::AUDIO && isset($metadata['media_details']['length'])) {
            $updates['duration'] = $metadata['media_details']['length'];
        }

        $media->update($updates);
    }

    /**
     * Extract description from WordPress metadata
     */
    private function extractDescription(array $metadata): ?string
    {
        if (isset($metadata['description']['rendered']) && ! empty($metadata['description']['rendered'])) {
            return strip_tags($metadata['description']['rendered']);
        }

        if (isset($metadata['caption']['rendered']) && ! empty($metadata['caption']['rendered'])) {
            return strip_tags($metadata['caption']['rendered']);
        }

        return null;
    }

    /**
     * Extract tags from WordPress metadata
     */
    private function extractTags(array $metadata): array
    {
        $tags = [];

        // Add media type as tag
        if (isset($metadata['media_type'])) {
            $tags[] = $metadata['media_type'];
        }

        // Add WordPress as source tag
        $tags[] = 'wordpress-import';

        // Add date-based tags
        if (isset($metadata['date'])) {
            $date = \Carbon\Carbon::parse($metadata['date']);
            $tags[] = $date->format('Y');
            $tags[] = $date->format('F'); // Month name
        }

        return array_unique($tags);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Media item import job failed', [
            'import_item_id' => $this->importItem->id,
            'import_id' => $this->importItem->import_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->handleImportFailure('Job failed: ' . $exception->getMessage());
    }

    /**
     * Get unique job ID
     */
    public function uniqueId(): string
    {
        return "import-media-item-{$this->importItem->id}";
    }

    /**
     * Retry backoff
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 sec, 1 min, 2 min
    }

    /**
     * Retry until
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(15);
    }
}