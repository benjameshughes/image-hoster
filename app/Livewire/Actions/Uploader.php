<?php

namespace App\Livewire\Actions;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Events\UploadCompleted;
use App\Events\UploadFileProcessed;
use App\Events\UploadProgressUpdated;
use App\Exceptions\UploadException;
use App\Models\Image;
use App\Services\UploaderService;
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
        $sessionLimiter = RateLimiter::for('file-upload-session', fn () => $request);
        if (! $sessionLimiter->attempt($request)) {
            $this->dispatch('upload-error', [
                'message' => 'Upload session limit reached. Please wait before starting another upload.',
                'retry_after' => $sessionLimiter->availableIn($request),
            ]);

            return;
        }

        // Check file batch rate limit
        $batchLimiter = RateLimiter::for('file-upload-batch', fn () => $request);
        if (! $batchLimiter->attempt($request, count($this->files))) {
            $this->dispatch('upload-error', [
                'message' => 'File upload limit reached. Please wait before uploading more files.',
                'retry_after' => $batchLimiter->availableIn($request),
            ]);

            return;
        }

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
            ];
        }

        // Dispatch initial progress update
        UploadProgressUpdated::dispatch(
            $userId,
            $this->uploadSessionId,
            collect($this->processingFiles),
            0,
            $totalFiles
        );

        foreach ($this->files as $index => $file) {
            try {
                // Update to processing status
                $this->processingFiles[$index]['status'] = 'processing';

                // Dispatch progress update for current file
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
                    ]
                );

                $uploader = UploaderService::forUser($userId)
                    ->disk($this->disk)
                    ->directory($this->getUploadDirectory())
                    ->randomFilename()
                    ->public()
                    ->maxSizeMB($this->maxFileSizeMB)
                    ->allowedImageTypes(...AllowedImageType::cases())
                    ->extractMetadata($this->extractMetadata)
                    ->checkDuplicates($this->checkDuplicates);

                $result = $uploader->process($file);

                // Mark as complete
                $this->processingFiles[$index]['status'] = 'complete';
                $successfulFiles++;

                $fileData = [
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
                ];

                $uploadedFiles->push($fileData);

                // Dispatch file processed event
                UploadFileProcessed::dispatch(
                    $userId,
                    $this->uploadSessionId,
                    $index,
                    $file->getClientOriginalName(),
                    'complete',
                    null,
                    $fileData
                );

            } catch (UploadException $e) {
                $this->processingFiles[$index]['status'] = 'error';
                $this->processingFiles[$index]['error'] = $e->getMessage();
                $failedFiles++;

                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'type' => 'upload_error',
                ];

                // Dispatch file processed event with error
                UploadFileProcessed::dispatch(
                    $userId,
                    $this->uploadSessionId,
                    $index,
                    $file->getClientOriginalName(),
                    'error',
                    $e->getMessage()
                );

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
                UploadFileProcessed::dispatch(
                    $userId,
                    $this->uploadSessionId,
                    $index,
                    $file->getClientOriginalName(),
                    'error',
                    $errorMessage
                );

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
        UploadCompleted::dispatch(
            $userId,
            $this->uploadSessionId,
            $totalFiles,
            $successfulFiles,
            $failedFiles,
            $uploadedFiles->toArray()
        );

        // Dispatch event to hide upload status and show results
        $this->dispatch('upload-complete');
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
}
