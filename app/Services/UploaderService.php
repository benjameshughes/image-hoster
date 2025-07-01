<?php

namespace App\Services;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Exceptions\DuplicateFileException;
use App\Exceptions\FileSizeLimitException;
use App\Exceptions\InvalidFileTypeException;
use App\Exceptions\StorageException;
use App\Models\Image;
use App\Services\ProgressTrackingFilesystemManager;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UploaderService
{
    private StorageDisk|string|Closure $disk = StorageDisk::SPACES;

    private bool $public = false;

    private string $directory = '';

    private ?string $filename = null;

    private ?string $extension = null;

    private ?UploadedFile $file = null;

    private bool $generateRandomFilename = false;

    private bool $preserveOriginalFilename = false;

    private bool $preserveOriginalExtension = true;

    private ?Closure $afterUpload = null;

    private bool $storeInDatabase = true;

    private string $model = Image::class;

    private array $allowedMimeTypes = [];

    private ?int $maxFileSize = null;

    private array $allowedExtensions = [];

    private bool $sanitizeFilename = true;

    private int $randomFilenameLength = 10;

    private bool $checkDuplicates = false;

    private bool $extractMetadata = true;

    private bool $processImages = true;

    private bool $generateThumbnails = true;

    private bool $compressImages = true;

    private ?int $userId = null;

    private ?Closure $progressCallback = null;

    private ?string $uploadSession = null;

    /**
     * Create a new instance (for static method chaining)
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Create instance with user context
     */
    public static function forUser(?int $userId): static
    {
        return (new static)->setUserId($userId);
    }

    /**
     * Set the file to be uploaded
     */
    public function file(UploadedFile $file): static
    {
        $this->file = $file;
        $this->extension = $file->getClientOriginalExtension();

        return $this;
    }

    /**
     * Set the storage disk
     */
    public function disk(StorageDisk|string|Closure $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set the file to public or private
     */
    public function public(bool $public = true): static
    {
        $this->public = $public;

        return $this;
    }

    /**
     * Set the file to private
     */
    public function private(): static
    {
        return $this->public(false);
    }

    /**
     * Set the directory where the file will be stored
     */
    public function directory(string $directory): static
    {
        $this->directory = trim($directory, '/');

        return $this;
    }

    /**
     * Set a specific filename
     */
    public function filename(string $filename): static
    {
        $this->filename = $filename;
        $this->generateRandomFilename = false;
        $this->preserveOriginalFilename = false;

        return $this;
    }

    /**
     * Generate a random filename
     */
    public function randomFilename(int $length = 10): static
    {
        $this->generateRandomFilename = true;
        $this->randomFilenameLength = $length;
        $this->preserveOriginalFilename = false;

        return $this;
    }

    /**
     * Use the original filename
     */
    public function useOriginalFilename(): static
    {
        $this->preserveOriginalFilename = true;
        $this->generateRandomFilename = false;

        return $this;
    }

    /**
     * Set a specific extension
     */
    public function extension(string $extension): static
    {
        $this->extension = ltrim($extension, '.');
        $this->preserveOriginalExtension = false;

        return $this;
    }

    /**
     * Don't store the file in the database
     */
    public function skipDatabase(): static
    {
        $this->storeInDatabase = false;

        return $this;
    }

    /**
     * Set a callback to run after upload
     */
    public function after(Closure $callback): static
    {
        $this->afterUpload = $callback;

        return $this;
    }

    /**
     * Set the model to use for database storage
     */
    public function model(string $modelClass): static
    {
        $this->model = $modelClass;

        return $this;
    }

    /**
     * Set allowed MIME types for validation
     */
    public function allowedMimeTypes(array $mimeTypes): static
    {
        $this->allowedMimeTypes = $mimeTypes;

        return $this;
    }

    /**
     * Set allowed image types using enum
     */
    public function allowedImageTypes(AllowedImageType ...$types): static
    {
        $mimeTypes = array_map(fn ($type) => $type->mimeType(), $types);

        return $this->allowedMimeTypes($mimeTypes);
    }

    /**
     * Set maximum file size in bytes
     */
    public function maxSize(int $bytes): static
    {
        $this->maxFileSize = $bytes;

        return $this;
    }

    /**
     * Set maximum file size in megabytes
     */
    public function maxSizeMB(int $megabytes): static
    {
        return $this->maxSize($megabytes * 1024 * 1024);
    }

    /**
     * Set allowed file extensions
     */
    public function allowedExtensions(array $extensions): static
    {
        $this->allowedExtensions = array_map(
            fn ($ext) => strtolower(ltrim($ext, '.')),
            $extensions
        );

        return $this;
    }

    /**
     * Enable/disable filename sanitization
     */
    public function sanitizeFilename(bool $sanitize = true): static
    {
        $this->sanitizeFilename = $sanitize;

        return $this;
    }

    /**
     * Enable duplicate checking
     */
    public function checkDuplicates(bool $check = true): static
    {
        $this->checkDuplicates = $check;

        return $this;
    }

    /**
     * Enable/disable metadata extraction
     */
    public function extractMetadata(bool $extract = true): static
    {
        $this->extractMetadata = $extract;

        return $this;
    }

    /**
     * Enable/disable image processing (thumbnails and compression)
     */
    public function processImages(bool $process = true): static
    {
        $this->processImages = $process;

        return $this;
    }

    /**
     * Enable/disable thumbnail generation
     */
    public function generateThumbnails(bool $generate = true): static
    {
        $this->generateThumbnails = $generate;

        return $this;
    }

    /**
     * Enable/disable image compression
     */
    public function compressImages(bool $compress = true): static
    {
        $this->compressImages = $compress;

        return $this;
    }

    /**
     * Set user ID for the upload
     */
    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Set progress callback for upload tracking
     */
    public function onProgress(Closure $callback): static
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Set upload session ID for tracking
     */
    public function uploadSession(string $sessionId): static
    {
        $this->uploadSession = $sessionId;

        return $this;
    }

    /**
     * Process the file upload
     */
    public function process(?UploadedFile $file = null): array
    {
        if ($file) {
            $this->file($file);
        }

        if (! $this->file) {
            throw new InvalidArgumentException('No file provided for upload');
        }

        return DB::transaction(function () {
            // Validate the file
            $this->validateFile();

            // Check for duplicates if enabled
            if ($this->checkDuplicates) {
                $hash = hash_file('sha256', $this->file->getRealPath());
                $existing = Image::where('file_hash', $hash)->first();
                if ($existing) {
                    throw new DuplicateFileException($hash);
                }
            }

            $finalFilename = $this->determineFilename();
            $diskName = $this->resolveDiskName();

            try {
                // Report upload start
                $this->reportProgress('upload_start', [
                    'filename' => $finalFilename,
                    'disk' => $diskName,
                    'size' => $this->file->getSize(),
                ]);

                // Store the file based on visibility with progress tracking
                $storedPath = $this->storeFileWithProgress($finalFilename, $diskName);

                if (! $storedPath) {
                    throw new StorageException('store', 'Failed to store file on disk');
                }

                // Report upload completion
                $this->reportProgress('upload_complete', [
                    'filename' => $finalFilename,
                    'disk' => $diskName,
                    'path' => $storedPath,
                ]);

                // Extract metadata if enabled
                $metadata = $this->extractMetadata ? $this->extractFileMetadata() : [];

                // Build result array
                $result = [
                    'filename' => $finalFilename,
                    'path' => $storedPath,
                    'url' => $this->public ? Storage::disk($diskName)->url($storedPath) : null,
                    'disk' => $diskName,
                    'directory' => $this->directory,
                    'mime_type' => $this->file->getMimeType(),
                    'size' => $this->file->getSize(),
                    'is_public' => $this->public,
                    'original_name' => $this->file->getClientOriginalName(),
                    'metadata' => $metadata,
                    'file_hash' => $this->checkDuplicates ? hash_file('sha256', $this->file->getRealPath()) : null,
                ];

                // Store in the database if needed
                if ($this->storeInDatabase) {
                    $result['record'] = $this->createDatabaseRecord($result);
                    
                    // Process image after database record is created
                    if ($this->processImages && $this->isImageFile($result['mime_type'])) {
                        $this->processImageAsync($result['record']);
                    }
                }

                // Run after upload callback if set
                if ($this->afterUpload) {
                    call_user_func($this->afterUpload, $result);
                }

                return $result;

            } catch (\Exception $e) {
                // Clean up any partially uploaded files
                $this->cleanup($finalFilename, $diskName);
                throw $e;
            }
        });
    }

    /**
     * Validate the uploaded file
     */
    private function validateFile(): void
    {
        // Check if file is valid
        if (! $this->file->isValid()) {
            throw new InvalidFileTypeException('Invalid file', ['valid files']);
        }

        // Check file size
        if ($this->maxFileSize && $this->file->getSize() > $this->maxFileSize) {
            throw new FileSizeLimitException($this->file->getSize(), $this->maxFileSize);
        }

        // Check MIME type
        if (! empty($this->allowedMimeTypes)) {
            $fileMimeType = $this->file->getMimeType();
            if (! in_array($fileMimeType, $this->allowedMimeTypes)) {
                throw new InvalidFileTypeException($fileMimeType, $this->allowedMimeTypes);
            }
        }

        // Check extension
        if (! empty($this->allowedExtensions)) {
            $extension = strtolower($this->file->getClientOriginalExtension());
            if (! in_array($extension, $this->allowedExtensions)) {
                throw new InvalidFileTypeException($extension, $this->allowedExtensions);
            }
        }
    }

    /**
     * Determine the final filename
     */
    private function determineFilename(): string
    {
        $name = match (true) {
            ! empty($this->filename) => $this->filename,
            $this->preserveOriginalFilename => pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME),
            $this->generateRandomFilename => Str::random($this->randomFilenameLength),
            default => Str::slug(pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME)).'_'.time(),
        };

        // Sanitize filename to prevent security issues
        if ($this->sanitizeFilename) {
            $name = $this->sanitizeFilenameString($name);
        }

        // Determine extension
        $extension = $this->preserveOriginalExtension
            ? $this->file->getClientOriginalExtension()
            : $this->extension;

        // Ensure we have a valid filename
        if (empty($name)) {
            $name = 'file_'.time();
        }

        return $name.($extension ? '.'.$extension : '');
    }

    /**
     * Sanitize filename to prevent security issues
     */
    private function sanitizeFilenameString(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = str_replace(['../', '../', '..\\', '..'], '', $filename);

        // Remove potentially dangerous characters but keep basic ones
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Remove leading/trailing underscores and dots
        $filename = trim($filename, '_.');

        // Ensure it's not empty and not too long
        if (empty($filename) || strlen($filename) > 100) {
            $filename = 'file_'.time();
        }

        return $filename;
    }

    /**
     * Create a database record
     */
    private function createDatabaseRecord(array $result): mixed
    {
        $model = new $this->model;

        $data = [
            'name' => $result['filename'],
            'path' => $result['path'],
            'original_name' => $result['original_name'],
            'mime_type' => $result['mime_type'],
            'image_type' => AllowedImageType::fromMimeType($result['mime_type']),
            'size' => $result['size'],
            'disk' => $result['disk'],
            'is_public' => $this->public,
            'directory' => $this->directory,
            'metadata' => $result['metadata'] ?? [],
            'file_hash' => $result['file_hash'],
        ];

        // Add dimensions if available
        if (isset($result['metadata']['width'], $result['metadata']['height'])) {
            $data['width'] = $result['metadata']['width'];
            $data['height'] = $result['metadata']['height'];
        }

        // Add user ID if set
        if ($this->userId) {
            $data['user_id'] = $this->userId;
        }

        try {
            return $model::create($data);
        } catch (\Exception $e) {
            throw new StorageException('database', $e->getMessage());
        }
    }

    /**
     * Clean up files in case of errors
     */
    private function cleanup(?string $filename, ?string $disk): void
    {
        if ($filename && $disk) {
            try {
                $path = $this->directory ? $this->directory.'/'.$filename : $filename;
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Exception $e) {
                // Log the cleanup failure but don't throw
                logger()->warning('Failed to cleanup uploaded file', [
                    'filename' => $filename,
                    'disk' => $disk,
                    'path' => $path ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Extract file metadata
     */
    private function extractFileMetadata(): array
    {
        $metadata = [];

        // Get image dimensions for image files
        if (str_starts_with($this->file->getMimeType(), 'image/')) {
            $imageInfo = getimagesize($this->file->getRealPath());
            if ($imageInfo) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['aspect_ratio'] = $imageInfo[0] / $imageInfo[1];
            }

            // Get EXIF data if available
            if (function_exists('exif_read_data') && in_array($this->file->getMimeType(), ['image/jpeg', 'image/tiff'])) {
                try {
                    $exif = @exif_read_data($this->file->getRealPath());
                    if ($exif) {
                        $metadata['exif'] = [
                            'camera' => $exif['Model'] ?? null,
                            'taken_at' => $exif['DateTimeOriginal'] ?? null,
                            'iso' => $exif['ISOSpeedRatings'] ?? null,
                            'aperture' => $exif['COMPUTED']['ApertureFNumber'] ?? null,
                            'exposure' => $exif['ExposureTime'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    // EXIF extraction failed, continue without it
                }
            }
        }

        return $metadata;
    }

    /**
     * Resolve the disk name
     */
    private function resolveDiskName(): string
    {
        return match (true) {
            $this->disk instanceof StorageDisk => $this->disk->value,
            is_callable($this->disk) => call_user_func($this->disk),
            default => $this->disk,
        };
    }

    /**
     * Get file information without uploading (useful for validation)
     */
    public function getFileInfo(?UploadedFile $file = null): array
    {
        if ($file) {
            $this->file($file);
        }

        if (! $this->file) {
            throw new InvalidArgumentException('No file provided');
        }

        return [
            'original_name' => $this->file->getClientOriginalName(),
            'size' => $this->file->getSize(),
            'mime_type' => $this->file->getMimeType(),
            'extension' => $this->file->getClientOriginalExtension(),
            'is_valid' => $this->file->isValid(),
            'proposed_filename' => $this->determineFilename(),
            'formatted_size' => $this->formatFileSize($this->file->getSize()),
            'image_type' => AllowedImageType::fromMimeType($this->file->getMimeType()),
        ];
    }

    /**
     * Validate a file without uploading
     */
    public function validate(?UploadedFile $file = null): bool
    {
        if ($file) {
            $this->file($file);
        }

        try {
            $this->validateFile();

            return true;
        } catch (InvalidFileTypeException|FileSizeLimitException $e) {
            return false;
        }
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2).' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2).' MB',
            $bytes >= 1024 => round($bytes / 1024, 2).' KB',
            default => $bytes.' B',
        };
    }

    /**
     * Check if file is an image
     */
    private function isImageFile(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Process image asynchronously (in a real app, this would be dispatched to a queue)
     */
    private function processImageAsync(Image $image): void
    {
        try {
            // For now, process synchronously
            // In a production app, you'd dispatch this to a queue
            $processingService = app(ImageProcessingService::class);
            $processingService->processImage($image);
        } catch (\Exception $e) {
            \Log::error('Image processing failed during upload', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store file with real progress tracking
     */
    private function storeFileWithProgress(string $filename, string $diskName): ?string
    {
        // Report storage start
        $this->reportProgress('storage_start', [
            'disk' => $diskName,
            'provider' => $this->getStorageProviderName($diskName),
        ]);

        try {
            $fileSize = $this->file->getSize();
            
            // For S3-compatible storage (spaces, r2, s3), use real progress tracking
            if (in_array($diskName, ['spaces', 'r2', 's3'])) {
                return $this->storeWithRealProgress($filename, $diskName, $fileSize);
            }

            // For local storage, use standard method
            $storedPath = $this->public
                ? $this->file->storePubliclyAs($this->directory, $filename, $diskName)
                : $this->file->storeAs($this->directory, $filename, $diskName);

            // Report completion for local storage
            $this->reportProgress('storage_complete', [
                'disk' => $diskName,
                'provider' => $this->getStorageProviderName($diskName),
                'path' => $storedPath,
            ]);

            return $storedPath;

        } catch (\Exception $e) {
            // Report storage error
            $this->reportProgress('storage_error', [
                'disk' => $diskName,
                'provider' => $this->getStorageProviderName($diskName),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Store file with real cloud storage progress tracking
     */
    private function storeWithRealProgress(string $filename, string $diskName, int $fileSize): ?string
    {
        try {
            // Create progress-tracking filesystem manager
            $progressManager = new ProgressTrackingFilesystemManager(app());
            
            // Set up progress callback
            $progressCallback = function ($progressData) use ($diskName) {
                if (isset($progressData['error'])) {
                    $this->reportProgress('storage_error', [
                        'disk' => $diskName,
                        'provider' => $this->getStorageProviderName($diskName),
                        'error' => $progressData['error'],
                    ]);
                } else {
                    $this->reportProgress('storage_progress', [
                        'disk' => $diskName,
                        'provider' => $this->getStorageProviderName($diskName),
                        'progress' => round($progressData['progress'] ?? 0, 1),
                        'uploaded_bytes' => $progressData['uploaded'] ?? 0,
                        'total_bytes' => $progressData['total'] ?? $fileSize,
                    ]);
                }
            };

            // Get progress-tracking disk instance
            $disk = $progressManager->progressDisk($diskName, $progressCallback);
            
            // Build the full path
            $fullPath = $this->directory ? $this->directory . '/' . $filename : $filename;
            
            // Store the file with real progress tracking
            $fileContents = file_get_contents($this->file->getRealPath());
            
            // Use writeStream for larger files (>1MB) to enable multipart upload
            if ($fileSize > 1024 * 1024) {
                $stream = fopen($this->file->getRealPath(), 'r');
                $disk->writeStream($fullPath, $stream, [
                    'ACL' => $this->public ? 'public-read' : 'private',
                    'ContentType' => $this->file->getMimeType(),
                ]);
                fclose($stream);
            } else {
                $disk->write($fullPath, $fileContents, [
                    'ACL' => $this->public ? 'public-read' : 'private',
                    'ContentType' => $this->file->getMimeType(),
                ]);
            }

            // Report completion
            $this->reportProgress('storage_complete', [
                'disk' => $diskName,
                'provider' => $this->getStorageProviderName($diskName),
                'path' => $fullPath,
            ]);

            return $fullPath;

        } catch (\Exception $e) {
            $this->reportProgress('storage_error', [
                'disk' => $diskName,
                'provider' => $this->getStorageProviderName($diskName),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Report progress via callback
     */
    private function reportProgress(string $stage, array $data = []): void
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, [
                'stage' => $stage,
                'session' => $this->uploadSession,
                'timestamp' => now()->toISOString(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Get human-readable storage provider name
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
}
