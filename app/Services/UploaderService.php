<?php

namespace App\Services;

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Exceptions\DuplicateFileException;
use App\Exceptions\FileSizeLimitException;
use App\Exceptions\InvalidFileTypeException;
use App\Exceptions\StorageException;
use App\Models\Image;
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

    private ?int $userId = null;

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
     * Set user ID for the upload
     */
    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

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
                // Store the file based on visibility
                $storedPath = $this->public
                    ? $this->file->storePubliclyAs($this->directory, $finalFilename, $diskName)
                    : $this->file->storeAs($this->directory, $finalFilename, $diskName);

                if (! $storedPath) {
                    throw new StorageException('store', 'Failed to store file on disk');
                }

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
}
