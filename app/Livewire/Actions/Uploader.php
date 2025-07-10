<?php

namespace App\Livewire\Actions;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Events\CloudUploadProgressUpdated;
use App\Events\UploadCompleted;
use App\Events\UploadFileProcessed;
use App\Events\UploadProgressUpdated;
use App\Exceptions\StorageException;
use App\Models\Media;
use App\Services\UploadPipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

    public bool $isUploading = false;

    public array $processingFiles = [];

    public string $uploadSessionId = '';

    public function mount(): void
    {
        $this->allowedTypes = array_map(
            fn (AllowedImageType $type) => $type->value,
            AllowedImageType::cases()
        );
        $this->uploadSessionId = Str::uuid()->toString();
    }

    public function updatedFiles(): void
    {
        $this->validate();
        $this->save();
    }

    /**
     * Process and save uploaded files with beautiful UI states
     */
    public function save(): void
    {
        // Rate limiting checks
        $request = app(Request::class);
        $request->setUserResolver(fn () => Auth::user());

        // Check upload session rate limit
        $sessionKey = 'file-upload-session:' . Auth::id();
        if (RateLimiter::tooManyAttempts($sessionKey, 20)) {
            $this->dispatch('upload-error', [
                'message' => 'Upload session limit reached. Please wait before starting another upload.',
                'retry_after' => RateLimiter::availableIn($sessionKey),
            ]);

            return;
        }

        // Check file batch rate limit
        $batchKey = 'file-upload-batch:' . Auth::id();
        if (RateLimiter::tooManyAttempts($batchKey, 50)) {
            $this->dispatch('upload-error', [
                'message' => 'File upload limit reached. Please wait before uploading more files.',
                'retry_after' => RateLimiter::availableIn($batchKey),
            ]);

            return;
        }

        // Hit the rate limiters
        RateLimiter::hit($sessionKey);
        RateLimiter::hit($batchKey, 60, count($this->files));

        $this->isUploading = true;
        $uploadedFiles = collect($this->uploadedFiles ?? []);
        $totalFiles = count($this->files);
        $errors = [];
        $userId = Auth::id();
        $successfulFiles = 0;
        $failedFiles = 0;

        // Build processing files list
        $this->processingFiles = [];
        foreach ($this->files as $index => $file) {
            $this->processingFiles[] = [
                'name' => $file->getClientOriginalName(),
                'size' => $this->formatFileSize($file->getSize()),
                'status' => 'uploading',
                'phase' => 'starting',
                'upload_progress' => 0,
                'bytes_uploaded' => 0,
                'total_bytes' => $file->getSize(),
                'upload_speed' => null,
                'eta' => null,
            ];
        }

        // Dispatch initial progress update
        if (app()->environment() !== 'testing') {
            UploadProgressUpdated::dispatch(
                $userId,
                $this->uploadSessionId,
                collect($this->processingFiles),
                0,
                $totalFiles
            );
        }

        foreach ($this->files as $index => $file) {
            try {
                // Update to processing status
                $this->processingFiles[$index]['status'] = 'processing';
                $this->processingFiles[$index]['phase'] = 'validating';

                // Dispatch progress update for current file
                if (app()->environment() !== 'testing') {
                    UploadProgressUpdated::dispatch(
                        $userId,
                        $this->uploadSessionId,
                        collect($this->processingFiles),
                        $index,
                        $totalFiles,
                        [
                            'name' => $file->getClientOriginalName(),
                            'size' => $this->formatFileSize($file->getSize()),
                            'status' => 'processing',
                            'phase' => 'validating',
                        ]
                    );
                }

                // Use the new upload pipeline
                $result = app(UploadPipelineService::class)->process($file, Auth::user(), [
                    'disk' => $this->disk->value,
                    'directory' => $this->getUploadDirectory(),
                    'is_public' => true,
                    'randomize_filename' => true,
                    'extract_metadata' => $this->extractMetadata,
                    'check_duplicates' => $this->checkDuplicates,
                    'max_size_mb' => $this->maxFileSizeMB,
                    'allowed_mime_types' => array_map(fn($type) => $type->mimeType(), AllowedImageType::cases()),
                    'session_id' => $this->uploadSessionId,
                ]);

                if (!$result->success) {
                    // Check if this is a duplicate error that should trigger validation
                    if (str_contains($result->message ?? '', 'duplicate') || str_contains($result->message ?? '', 'already exists')) {
                        $this->addError("files.{$index}", $result->message ?? 'Upload failed');
                        continue; // Skip to next file instead of throwing exception
                    }
                    throw new StorageException('upload', $result->message ?? 'Upload failed');
                }

                // Mark as complete
                $this->processingFiles[$index]['status'] = 'complete';
                $successfulFiles++;

                $fileData = [
                    'id' => $result->record?->id,
                    'name' => $file->getClientOriginalName(),
                    'filename' => $result->filename,
                    'size' => $result->size,
                    'formatted_size' => $this->formatFileSize($result->size ?? $file->getSize()),
                    'mime' => $result->mimeType,
                    'url' => $result->url,
                    'path' => $result->path,
                    'width' => $result->metadata->get('width'),
                    'height' => $result->metadata->get('height'),
                    'uploaded_at' => now()->toISOString(),
                ];

                $uploadedFiles->push($fileData);

                // Dispatch file processed event
                if (app()->environment() !== 'testing') {
                    UploadFileProcessed::dispatch(
                        $userId,
                        $this->uploadSessionId,
                        $index,
                        $file->getClientOriginalName(),
                        'complete',
                        null,
                        $fileData
                    );
                }

            } catch (StorageException $e) {
                $this->processingFiles[$index]['status'] = 'error';
                $this->processingFiles[$index]['error'] = $e->getMessage();
                $failedFiles++;

                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'type' => 'upload_error',
                ];

                // Dispatch file processed event with error
                if (app()->environment() !== 'testing') {
                    UploadFileProcessed::dispatch(
                        $userId,
                        $this->uploadSessionId,
                        $index,
                        $file->getClientOriginalName(),
                        'error',
                        $e->getMessage()
                    );
                }

            } catch (\Exception $e) {
                $this->processingFiles[$index]['status'] = 'error';
                $this->processingFiles[$index]['error'] = 'Upload failed';
                $failedFiles++;

                $errorMessage = 'Unexpected error: '.$e->getMessage();
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $errorMessage,
                    'type' => 'system_error',
                ];

                // Dispatch file processed event with error
                if (app()->environment() !== 'testing') {
                    UploadFileProcessed::dispatch(
                        $userId,
                        $this->uploadSessionId,
                        $index,
                        $file->getClientOriginalName(),
                        'error',
                        $errorMessage
                    );
                }

                logger()->error('Upload failed with unexpected error', [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->uploadedFiles = $uploadedFiles->toArray();
        $this->files = [];
        $this->isUploading = false;
        $this->processingFiles = [];

        // Dispatch upload completed event
        if (app()->environment() !== 'testing') {
            UploadCompleted::dispatch(
                $userId,
                $this->uploadSessionId,
                $totalFiles,
                $successfulFiles,
                $failedFiles,
                $uploadedFiles->toArray()
            );
        }

        // Dispatch event to hide upload status and show results
        $this->dispatch('upload-complete');
    }

    /**
     * Format file size for display
     */
    public function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / (1024 ** 3), 2).' GB',
            $bytes >= 1024 ** 2 => number_format($bytes / (1024 ** 2), 2).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 2).' KB',
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
                $media = Media::find($file['id']);
                if ($media) {
                    $media->delete(); // This triggers the file deletion in the model
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
                    Media::find($file['id'])?->delete();
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
        $this->processingFiles = [];
        $this->isUploading = false;

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
     * Handle cloud upload progress updates
     */
    public function handleCloudUploadProgress(
        string $sessionId,
        string $filename,
        int $bytesUploaded,
        int $totalBytes,
        float $percentage,
        ?float $speed = null,
        ?int $eta = null
    ): void {
        // Only handle progress for our current upload session
        if ($sessionId !== $this->uploadSessionId) {
            return;
        }

        // Find the file being uploaded
        foreach ($this->processingFiles as $index => $file) {
            if ($file['name'] === $filename) {
                $this->processingFiles[$index]['status'] = 'uploading';
                $this->processingFiles[$index]['phase'] = 'uploading';
                $this->processingFiles[$index]['upload_progress'] = $percentage;
                $this->processingFiles[$index]['bytes_uploaded'] = $bytesUploaded;
                $this->processingFiles[$index]['total_bytes'] = $totalBytes;
                $this->processingFiles[$index]['upload_speed'] = $speed;
                $this->processingFiles[$index]['eta'] = $eta;
                break;
            }
        }
    }

    /**
     * Get formatted upload speed
     */
    private function formatUploadSpeed(?float $speed): ?string
    {
        if (!$speed) {
            return null;
        }
        
        return $this->formatFileSize((int) $speed) . '/s';
    }

    /**
     * Get formatted ETA
     */
    private function formatETA(?int $eta): ?string
    {
        if (!$eta) {
            return null;
        }
        
        if ($eta < 60) {
            return $eta . 's';
        } elseif ($eta < 3600) {
            return floor($eta / 60) . 'm ' . ($eta % 60) . 's';
        } else {
            return floor($eta / 3600) . 'h ' . floor(($eta % 3600) / 60) . 'm';
        }
    }
}
