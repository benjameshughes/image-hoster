<?php

declare(strict_types=1);

namespace App\Actions\Upload\Core;

use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Core action to save file information to the database
 */
class SaveToDatabaseAction extends AbstractUploadAction
{
    protected int $priority = 90; // Low priority - save after all processing

    protected array $configurationOptions = [
        'save_to_database' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Save file information to database',
        ],
        'include_metadata' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Include metadata in database record',
        ],
    ];

    public function getName(): string
    {
        return 'save_to_database';
    }

    public function getDescription(): string
    {
        return 'Saves file information and metadata to the database';
    }

    public function execute(UploadContext $context): UploadResult
    {
        if (!$this->isEnabled($context)) {
            return $this->success($context, 'Database saving disabled');
        }

        $this->log('Starting database save', [
            'filename' => $context->getOriginalFilename(),
            'user_id' => $context->user->id,
        ]);

        try {
            return DB::transaction(function () use ($context) {
                // Get required data from context metadata
                $storedPath = $context->getMetadata('stored_path');
                $filename = $context->getMetadata('filename');
                $url = $context->getMetadata('url');
                $disk = $context->getMetadata('disk');

                if (!$storedPath || !$filename || !$url) {
                    return $this->failure(
                        'Missing required file information for database save',
                        ['missing_data' => 'stored_path, filename, or url not found in context'],
                        $context
                    );
                }

                // Prepare media data (using Media model field names)
                $mediaData = [
                    'user_id' => $context->user->id,
                    'name' => $filename,
                    'original_name' => $context->getOriginalFilename(),
                    'path' => $storedPath,
                    'directory' => $context->directory,
                    'disk' => $disk ?? $context->disk->value,
                    'size' => $context->getFileSize(),
                    'mime_type' => $context->getMimeType(),
                    'is_public' => $context->isPublic,
                ];

                // Add metadata if enabled
                if ($context->getConfiguration('include_metadata', true)) {
                    $metadata = $context->metadata->except([
                        'stored_path', 'filename', 'url', 'disk', 'processed_at'
                    ])->toArray();
                    
                    if (!empty($metadata)) {
                        $mediaData['metadata'] = $metadata;
                    }
                }

                // Add dimensions if available
                if ($context->getMetadata('width') && $context->getMetadata('height')) {
                    $mediaData['width'] = $context->getMetadata('width');
                    $mediaData['height'] = $context->getMetadata('height');
                }

                // Add file hash if available
                if ($context->getMetadata('file_hash')) {
                    $mediaData['file_hash'] = $context->getMetadata('file_hash');
                }

                // Create the media record
                $media = Media::create($mediaData);

                $this->log('Database record created successfully', [
                    'media_id' => $media->id,
                    'filename' => $filename,
                ]);

                return UploadResult::success(
                    message: 'File saved to database successfully',
                    record: $media,
                    path: $storedPath,
                    url: $url,
                    filename: $filename,
                    size: $context->getFileSize(),
                    mimeType: $context->getMimeType(),
                    metadata: $context->metadata,
                    // Don't pass context - this is the final action
                );
            });

        } catch (\Exception $e) {
            return $this->failure(
                'Database save failed: ' . $e->getMessage(),
                ['exception' => $e->getMessage()],
                $context
            );
        }
    }

    public function canHandle(UploadContext $context): bool
    {
        return parent::canHandle($context) && $this->isEnabled($context);
    }
}