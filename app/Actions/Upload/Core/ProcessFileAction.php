<?php

declare(strict_types=1);

namespace App\Actions\Upload\Core;

use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Services\CloudUploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Core file processing action that handles the actual file storage
 */
class ProcessFileAction extends AbstractUploadAction
{
    protected int $priority = 50; // Mid priority - process after validation

    protected array $configurationOptions = [
        'randomize_filename' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Generate a random filename to prevent conflicts',
        ],
        'preserve_extension' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Preserve the original file extension',
        ],
        'sanitize_filename' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Sanitize filename to remove unsafe characters',
        ],
    ];

    public function getName(): string
    {
        return 'process_file';
    }

    public function getDescription(): string
    {
        return 'Processes and stores the uploaded file';
    }

    public function execute(UploadContext $context): UploadResult
    {
        $this->log('Starting file processing', [
            'filename' => $context->getOriginalFilename(),
            'disk' => $context->disk->value,
            'directory' => $context->directory,
        ]);

        try {
            // Generate filename
            $filename = $this->generateFilename($context);
            $diskConfig = config("filesystems.disks.{$context->disk->value}");
            
            // Check if this is an S3-compatible disk for progress tracking
            if ($diskConfig && $diskConfig['driver'] === 's3') {
                return $this->uploadToCloudWithProgress($context, $filename);
            } else {
                return $this->uploadToLocalDisk($context, $filename);
            }

        } catch (\Exception $e) {
            return $this->failure(
                'File processing failed: ' . $e->getMessage(),
                ['exception' => $e->getMessage()],
                $context
            );
        }
    }

    /**
     * Upload to cloud storage with progress tracking
     */
    private function uploadToCloudWithProgress(UploadContext $context, string $filename): UploadResult
    {
        $cloudService = app(CloudUploadService::class);
        
        try {
            $result = $cloudService->uploadWithProgress(
                $context->file,
                $context->disk->value,
                $context->directory,
                $filename,
                $context->user->id,
                $context->sessionId,
                $context->isPublic ? 'public' : 'private'
            );

            $this->log('File uploaded to cloud successfully', [
                'stored_path' => $result['path'],
                'filename' => $filename,
                'url' => $result['url'],
            ]);

            return $this->success(
                $context,
                'File uploaded to cloud successfully',
                [
                    'stored_path' => $result['path'],
                    'filename' => $filename,
                    'url' => $result['url'],
                    'disk' => $context->disk->value,
                    'processed_at' => now()->toISOString(),
                    'etag' => $result['etag'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            return $this->failure(
                'Cloud upload failed: ' . $e->getMessage(),
                ['exception' => $e->getMessage()],
                $context
            );
        }
    }

    /**
     * Upload to local disk (fallback for non-S3 disks)
     */
    private function uploadToLocalDisk(UploadContext $context, string $filename): UploadResult
    {
        $disk = Storage::disk($context->disk->value);
        $storedPath = $disk->putFileAs(
            $context->directory,
            $context->file,
            $filename,
            $context->isPublic ? 'public' : 'private'
        );

        if (!$storedPath) {
            return $this->failure(
                'Failed to store file',
                ['storage_error' => 'Could not write file to storage'],
                $context
            );
        }

        // Generate URL
        $url = $context->isPublic 
            ? $disk->url($storedPath)
            : $disk->temporaryUrl($storedPath, now()->addHour());

        $this->log('File processed successfully', [
            'stored_path' => $storedPath,
            'filename' => $filename,
            'url' => $url,
        ]);

        return $this->success(
            $context,
            'File processed successfully',
            [
                'stored_path' => $storedPath,
                'filename' => $filename,
                'url' => $url,
                'disk' => $context->disk->value,
                'processed_at' => now()->toISOString(),
            ]
        );
    }

    /**
     * Generate a filename based on context configuration
     */
    private function generateFilename(UploadContext $context): string
    {
        $originalName = $context->getOriginalFilename();
        $extension = $context->getExtension();

        if ($context->getConfiguration('randomize_filename', true)) {
            $name = Str::uuid()->toString();
        } else {
            $name = pathinfo($originalName, PATHINFO_FILENAME);
            
            if ($context->getConfiguration('sanitize_filename', true)) {
                $name = $this->sanitizeFilename($name);
            }
        }

        if ($context->getConfiguration('preserve_extension', true) && $extension) {
            return $name . '.' . $extension;
        }

        return $name;
    }

    /**
     * Sanitize filename to remove unsafe characters
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace unsafe characters
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $filename);
        
        // Remove consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Remove leading/trailing underscores
        return trim($filename, '_');
    }

    public function canHandle(UploadContext $context): bool
    {
        return parent::canHandle($context) && $this->isEnabled($context);
    }
}