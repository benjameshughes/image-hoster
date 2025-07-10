<?php

declare(strict_types=1);

namespace App\Actions\Upload\Plugins;

use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Models\Media;

/**
 * Plugin action to detect duplicate files based on content hash
 */
class DuplicateDetectionAction extends AbstractUploadAction
{
    protected int $priority = 25; // High priority - check before processing

    protected array $configurationOptions = [
        'hash_algorithm' => [
            'type' => 'select',
            'options' => ['sha256', 'md5', 'sha1'],
            'default' => 'sha256',
            'description' => 'Hashing algorithm to use for duplicate detection',
        ],
        'scope' => [
            'type' => 'select',
            'options' => ['user', 'global'],
            'default' => 'user',
            'description' => 'Scope for duplicate detection (user-specific or global)',
        ],
        'action_on_duplicate' => [
            'type' => 'select',
            'options' => ['reject', 'skip', 'rename'],
            'default' => 'reject',
            'description' => 'Action to take when a duplicate is found',
        ],
    ];

    public function getName(): string
    {
        return 'duplicate_detection';
    }

    public function getDescription(): string
    {
        return 'Detects duplicate files based on content hash to prevent storage waste';
    }

    public function canHandle(UploadContext $context): bool
    {
        return parent::canHandle($context) 
            && $context->checkDuplicates 
            && $this->isEnabled($context);
    }

    public function execute(UploadContext $context): UploadResult
    {
        $this->log('Starting duplicate detection', [
            'filename' => $context->getOriginalFilename(),
            'user_id' => $context->user->id,
        ]);

        try {
            // Calculate file hash
            $hash = $this->calculateFileHash($context);
            if (!$hash) {
                return $this->failure(
                    'Failed to calculate file hash for duplicate detection',
                    ['hash_calculation_failed' => true],
                    $context
                );
            }

            // Check for existing file with same hash
            $existingMedia = $this->findExistingMedia($context, $hash);
            
            if ($existingMedia) {
                return $this->handleDuplicate($context, $existingMedia, $hash);
            }

            // No duplicate found, continue with hash metadata
            $this->log('No duplicate found, continuing processing', [
                'filename' => $context->getOriginalFilename(),
                'hash' => $hash,
            ]);

            return $this->success(
                $context,
                'No duplicate found',
                [
                    'file_hash' => $hash,
                    'hash_algorithm' => $context->getConfiguration('hash_algorithm', 'sha256'),
                    'duplicate_check_passed' => true,
                ]
            );

        } catch (\Exception $e) {
            return $this->failure(
                'Duplicate detection failed: ' . $e->getMessage(),
                ['exception' => $e->getMessage()],
                $context
            );
        }
    }

    /**
     * Calculate file hash using specified algorithm
     */
    private function calculateFileHash(UploadContext $context): ?string
    {
        try {
            $algorithm = $context->getConfiguration('hash_algorithm', 'sha256');
            $filePath = $context->file->getPathname();

            if (!in_array($algorithm, hash_algos())) {
                $this->logError("Unsupported hash algorithm: {$algorithm}");
                return null;
            }

            return hash_file($algorithm, $filePath);

        } catch (\Exception $e) {
            $this->logError('Failed to calculate file hash: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find existing image with the same hash
     */
    private function findExistingMedia(UploadContext $context, string $hash): ?Media
    {
        $scope = $context->getConfiguration('scope', 'user');
        
        $query = Media::where('file_hash', $hash);
        
        if ($scope === 'user') {
            $query->where('user_id', $context->user->id);
        }

        return $query->first();
    }

    /**
     * Handle duplicate file based on configuration
     */
    private function handleDuplicate(
        UploadContext $context, 
        Media $existingMedia, 
        string $hash
    ): UploadResult {
        $action = $context->getConfiguration('action_on_duplicate', 'reject');
        
        $this->log('Duplicate file detected', [
            'filename' => $context->getOriginalFilename(),
            'existing_media_id' => $existingMedia->id,
            'hash' => $hash,
            'action' => $action,
        ]);

        return match ($action) {
            'reject' => $this->rejectDuplicate($context, $existingMedia),
            'skip' => $this->skipDuplicate($context, $existingMedia),
            'rename' => $this->renameDuplicate($context, $existingMedia),
            default => $this->rejectDuplicate($context, $existingMedia),
        };
    }

    /**
     * Reject the duplicate file upload
     */
    private function rejectDuplicate(UploadContext $context, Media $existingMedia): UploadResult
    {
        return $this->failure(
            'File already exists (duplicate detected)',
            [
                'duplicate_detected' => true,
                'existing_file' => [
                    'id' => $existingMedia->id,
                    'filename' => $existingMedia->name,
                    'original_filename' => $existingMedia->original_name,
                    'url' => $existingMedia->url,
                    'uploaded_at' => $existingMedia->created_at->toISOString(),
                ],
            ],
            $context
        );
    }

    /**
     * Skip duplicate but return success with existing file info
     */
    private function skipDuplicate(UploadContext $context, Media $existingMedia): UploadResult
    {
        return UploadResult::success(
            message: 'Duplicate file skipped, returning existing file',
            record: $existingMedia,
            path: $existingMedia->path,
            url: $existingMedia->url,
            filename: $existingMedia->name,
            size: $existingMedia->size,
            mimeType: $existingMedia->mime_type,
            metadata: collect([
                'duplicate_skipped' => true,
                'existing_file_id' => $existingMedia->id,
                'file_hash' => $existingMedia->file_hash,
            ]),
            context: $context,
        );
    }

    /**
     * Allow duplicate but with renamed filename
     */
    private function renameDuplicate(UploadContext $context, Media $existingMedia): UploadResult
    {
        // Generate a unique suffix
        $timestamp = now()->format('YmdHis');
        $suffix = "duplicate_{$timestamp}";
        
        // Update context to use renamed file
        $originalName = pathinfo($context->getOriginalFilename(), PATHINFO_FILENAME);
        $extension = pathinfo($context->getOriginalFilename(), PATHINFO_EXTENSION);
        $newName = "{$originalName}_{$suffix}.{$extension}";

        // Create new context with updated configuration
        $updatedContext = $context->withConfiguration([
            'force_filename' => $newName,
            'duplicate_renamed' => true,
            'original_duplicate_id' => $existingMedia->id,
        ]);

        return $this->success(
            $updatedContext,
            'Duplicate detected, proceeding with renamed file',
            [
                'duplicate_renamed' => true,
                'original_duplicate_id' => $existingMedia->id,
                'new_filename' => $newName,
                'file_hash' => $existingMedia->file_hash,
            ]
        );
    }
}