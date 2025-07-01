<?php

namespace App\Livewire\Actions;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Exceptions\UploadException;
use App\Models\Image;
use App\Services\UploaderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Uploader extends Component
{
    use WithFileUploads;

    #[Rule([
        'files.*' => [
            'required',
            'file',
            'mimes:jpeg,jpg,png,gif,webp,svg,bmp,tiff',
            'max:51200', // 50MB
        ],
    ])]
    public array $files = [];

    public array $uploadedFiles = [];

    public StorageDisk $disk = StorageDisk::SPACES;

    public bool $extractMetadata = true;

    public bool $checkDuplicates = false;

    public int $maxFileSizeMB = 50;

    public array $allowedTypes = [];

    public array $uploadProgress = [];

    public string $uploadSessionId = '';

    public function mount(): void
    {
        $this->allowedTypes = array_map(
            fn (AllowedImageType $type) => $type->value,
            AllowedImageType::cases()
        );

        $this->uploadSessionId = Str::random(16);
    }

    public function updatedFiles(): void
    {
        $this->validate();
        $this->save();
    }

    /**
     * Process and save uploaded files with progress tracking
     */
    public function save(): void
    {
        $uploadedFiles = collect($this->uploadedFiles ?? []);
        $totalFiles = count($this->files);
        $processedFiles = 0;
        $errors = [];
        $userId = Auth::id();

        // Dispatch start event
        $this->dispatch('upload-started', ['total' => $totalFiles]);

        foreach ($this->files as $index => $file) {
            try {
                // Initialize progress for this file
                $fileId = 'file_' . $index;
                $this->uploadProgress[$fileId] = [
                    'filename' => $file->getClientOriginalName(),
                    'disk' => $this->disk->value,
                    'provider' => $this->getStorageProviderName($this->disk->value),
                    'stage' => 'preparing',
                    'progress' => 0,
                    'size' => $this->formatFileSize($file->getSize()),
                ];

                // Update general progress
                $this->dispatch('upload-progress', [
                    'current' => $processedFiles + 1,
                    'total' => $totalFiles,
                    'filename' => $file->getClientOriginalName(),
                    'size' => $this->formatFileSize($file->getSize()),
                ]);

                // Update storage-specific progress
                $this->dispatch('storage-progress-update', [
                    'fileId' => $fileId,
                    'progress' => $this->uploadProgress,
                ]);

                $uploader = UploaderService::forUser($userId)
                    ->disk($this->disk)
                    ->directory($this->getUploadDirectory())
                    ->randomFilename()
                    ->public()
                    ->maxSizeMB($this->maxFileSizeMB)
                    ->allowedImageTypes(...AllowedImageType::cases())
                    ->extractMetadata($this->extractMetadata)
                    ->checkDuplicates($this->checkDuplicates)
                    ->uploadSession($this->uploadSessionId)
                    ->onProgress(function ($progressData) use ($fileId) {
                        $this->updateFileProgress($fileId, $progressData);
                    });

                $result = $uploader->process($file);

                $uploadedFiles->push([
                    'id' => $result['record']->id ?? null,
                    'name' => $file->getClientOriginalName(),
                    'filename' => $result['filename'],
                    'size' => $result['size'],
                    'formatted_size' => $this->formatFileSize($result['size']),
                    'mime' => $result['mime_type'],
                    'url' => $result['url'],
                    'path' => $result['path'],
                    'width' => $result['metadata']['width'] ?? null,
                    'height' => $result['metadata']['height'] ?? null,
                    'uploaded_at' => now()->toISOString(),
                ]);

                $processedFiles++;

            } catch (UploadException $e) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'type' => 'upload_error',
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => 'Unexpected error: '.$e->getMessage(),
                    'type' => 'system_error',
                ];

                logger()->error('Upload failed with unexpected error', [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->uploadedFiles = $uploadedFiles->toArray();
        $this->files = [];

        // Dispatch completion event
        $this->dispatch('upload-completed', [
            'successful' => $processedFiles,
            'failed' => count($errors),
            'errors' => $errors,
            'total' => $totalFiles,
        ]);
    }

    /**
     * Format file size for display
     */
    public function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2).' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2).' MB',
            $bytes >= 1024 => round($bytes / 1024, 2).' KB',
            default => $bytes.' B',
        };
    }

    /**
     * Get the upload directory based on user and date
     */
    private function getUploadDirectory(): string
    {
        $userId = Auth::id() ?? 'guest';
        $date = now()->format('Y/m');

        return "uploads/{$userId}/{$date}";
    }

    /**
     * Remove a specific uploaded file
     */
    public function removeFile(int $index): void
    {
        if (! isset($this->uploadedFiles[$index])) {
            return;
        }

        $file = $this->uploadedFiles[$index];
        $success = false;

        try {
            // Remove from database first (this will also delete the file via model events)
            if (isset($file['id'])) {
                $image = Image::find($file['id']);
                if ($image) {
                    $image->delete(); // This triggers the file deletion in the model
                    $success = true;
                }
            } else {
                // Fallback: delete from storage directly if no database record
                if (isset($file['path'])) {
                    Storage::disk($this->disk->value)->delete($file['path']);
                    $success = true;
                }
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to delete file', [
                'file' => $file,
                'error' => $e->getMessage(),
            ]);
        }

        // Remove from array regardless of deletion success
        unset($this->uploadedFiles[$index]);
        $this->uploadedFiles = array_values($this->uploadedFiles);

        $this->dispatch('file-removed', [
            'filename' => $file['name'],
            'success' => $success,
        ]);
    }

    /**
     * Clear all uploaded files
     */
    public function clear(): void
    {
        $deletedCount = 0;
        $totalCount = count($this->uploadedFiles);

        foreach ($this->uploadedFiles as $file) {
            try {
                if (isset($file['id'])) {
                    Image::find($file['id'])?->delete();
                    $deletedCount++;
                } elseif (isset($file['path'])) {
                    Storage::disk($this->disk->value)->delete($file['path']);
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                logger()->warning('Failed to delete file during clear', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->uploadedFiles = [];
        $this->files = [];
        $this->clearProgress();

        $this->dispatch('files-cleared', [
            'deleted' => $deletedCount,
            'total' => $totalCount,
        ]);
    }

    /**
     * Get computed property for upload statistics
     */
    #[Computed]
    public function uploadStats(): array
    {
        $files = collect($this->uploadedFiles);

        return [
            'total_files' => $files->count(),
            'total_size' => $files->sum('size'),
            'total_size_formatted' => $this->formatFileSize($files->sum('size')),
            'average_size' => $files->count() > 0 ? $files->avg('size') : 0,
            'largest_file' => $files->max('size'),
            'smallest_file' => $files->min('size'),
            'file_types' => $files->groupBy('mime')->keys()->toArray(),
        ];
    }

    /**
     * Validate if file type is allowed
     */
    public function isFileTypeAllowed(string $mimeType): bool
    {
        return AllowedImageType::fromMimeType($mimeType) !== null;
    }

    /**
     * Get maximum file size in bytes for validation
     */
    public function getMaxFileSizeBytes(): int
    {
        return $this->maxFileSizeMB * 1024 * 1024;
    }

    /**
     * Set storage disk
     */
    public function setDisk(StorageDisk $disk): void
    {
        $this->disk = $disk;
    }

    /**
     * Toggle duplicate checking
     */
    public function toggleDuplicateCheck(): void
    {
        $this->checkDuplicates = ! $this->checkDuplicates;
    }

    /**
     * Toggle metadata extraction
     */
    public function toggleMetadataExtraction(): void
    {
        $this->extractMetadata = ! $this->extractMetadata;
    }

    /**
     * Update file upload progress
     */
    private function updateFileProgress(string $fileId, array $progressData): void
    {
        if (!isset($this->uploadProgress[$fileId])) {
            return;
        }

        $stage = $progressData['stage'];
        $data = $progressData['data'] ?? [];

        switch ($stage) {
            case 'upload_start':
                $this->uploadProgress[$fileId]['stage'] = 'uploading';
                $this->uploadProgress[$fileId]['progress'] = 0;
                break;

            case 'storage_start':
                $this->uploadProgress[$fileId]['stage'] = 'storing';
                $this->uploadProgress[$fileId]['provider'] = $data['provider'] ?? '';
                break;

            case 'storage_progress':
                $this->uploadProgress[$fileId]['stage'] = 'storing';
                $this->uploadProgress[$fileId]['progress'] = $data['progress'] ?? 0;
                $this->uploadProgress[$fileId]['uploaded_bytes'] = $data['uploaded_bytes'] ?? 0;
                $this->uploadProgress[$fileId]['total_bytes'] = $data['total_bytes'] ?? 0;
                break;

            case 'storage_complete':
                $this->uploadProgress[$fileId]['stage'] = 'processing';
                $this->uploadProgress[$fileId]['progress'] = 100;
                break;

            case 'upload_complete':
                $this->uploadProgress[$fileId]['stage'] = 'complete';
                $this->uploadProgress[$fileId]['progress'] = 100;
                break;

            case 'storage_error':
                $this->uploadProgress[$fileId]['stage'] = 'error';
                $this->uploadProgress[$fileId]['error'] = $data['error'] ?? 'Unknown error';
                break;
        }

        // Dispatch updated progress to frontend
        $this->dispatch('storage-progress-update', [
            'fileId' => $fileId,
            'progress' => $this->uploadProgress,
        ]);
    }

    /**
     * Get storage provider display name
     */
    private function getStorageProviderName(string $diskName): string
    {
        return match ($diskName) {
            'spaces' => 'DigitalOcean Spaces',
            'r2' => 'Cloudflare R2',
            's3' => 'Amazon S3',
            'local' => 'Local Storage',
            default => ucfirst($diskName),
        };
    }

    /**
     * Clear upload progress
     */
    public function clearProgress(): void
    {
        $this->uploadProgress = [];
        $this->uploadSessionId = Str::random(16);
    }
}
