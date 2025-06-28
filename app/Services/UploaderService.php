<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Closure;

class UploaderService
{
    private string|Closure $disk = 'spaces';
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

    // Validation properties
    private array $allowedMimeTypes = [];
    private ?int $maxFileSize = null;
    private array $allowedExtensions = [];
    private bool $sanitizeFilename = true;
    private int $randomFilenameLength = 10;

    /**
     * Create a new instance (for static method chaining)
     */
    public static function make(): static
    {
        return new static();
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
    public function disk(string|Closure $disk): static
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
     * Set maximum file size in bytes
     */
    public function maxSize(int $bytes): static
    {
        $this->maxFileSize = $bytes;
        return $this;
    }

    /**
     * Set allowed file extensions
     */
    public function allowedExtensions(array $extensions): static
    {
        $this->allowedExtensions = array_map(
            fn($ext) => strtolower(ltrim($ext, '.')),
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
     * Process the file upload
     */
    public function process(UploadedFile $file = null): array
    {
        if ($file) {
            $this->file($file);
        }

        if (!$this->file) {
            throw new \InvalidArgumentException('No file provided for upload');
        }

        // Validate the file
        $this->validateFile();

        $finalFilename = null;
        $disk = null;

        try {
            // Determine the filename
            $finalFilename = $this->determineFilename();

            // Get the disk to use
            $disk = is_callable($this->disk) ? call_user_func($this->disk) : $this->disk;

            // Store the file based on visibility
            $storedPath = $this->public
                ? $this->file->storePubliclyAs($this->directory, $finalFilename, $disk)
                : $this->file->storeAs($this->directory, $finalFilename, $disk);

            if (!$storedPath) {
                throw new \RuntimeException('Failed to store file on disk');
            }

            // Build a result array
            $result = [
                'filename' => $finalFilename,
                'path' => $storedPath,
                'url' => $this->public ? Storage::disk($disk)->url($storedPath) : null,
                'disk' => $disk,
                'directory' => $this->directory,
                'mime_type' => $this->file->getMimeType(),
                'size' => $this->file->getSize(),
                'is_public' => $this->public,
                'original_name' => $this->file->getClientOriginalName(),
            ];

            // Store in the database if needed
            if ($this->storeInDatabase) {
                $result['record'] = $this->createDatabaseRecord($result, $disk);
            }

            // Run after upload callback if set
            if ($this->afterUpload) {
                call_user_func($this->afterUpload, $result);
            }

            return $result;

        } catch (\Exception $e) {
            // Clean up any partially uploaded files
            $this->cleanup($finalFilename, $disk);
            throw $e;
        }
    }

    /**
     * Validate the uploaded file
     */
    private function validateFile(): void
    {
        // Check if file is valid
        if (!$this->file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is not valid.'
            ]);
        }

        // Check file size
        if ($this->maxFileSize && $this->file->getSize() > $this->maxFileSize) {
            $maxSizeMB = number_format($this->maxFileSize / 1024 / 1024, 2);
            throw ValidationException::withMessages([
                'file' => "File size exceeds maximum allowed size of {$maxSizeMB}MB"
            ]);
        }

        // Check MIME type
        if (!empty($this->allowedMimeTypes)) {
            $fileMimeType = $this->file->getMimeType();
            if (!in_array($fileMimeType, $this->allowedMimeTypes)) {
                throw ValidationException::withMessages([
                    'file' => 'File type not allowed. Allowed types: ' .
                        implode(', ', $this->allowedMimeTypes)
                ]);
            }
        }

        // Check extension
        if (!empty($this->allowedExtensions)) {
            $extension = strtolower($this->file->getClientOriginalExtension());
            if (!in_array($extension, $this->allowedExtensions)) {
                throw ValidationException::withMessages([
                    'file' => 'File extension not allowed. Allowed extensions: ' .
                        implode(', ', $this->allowedExtensions)
                ]);
            }
        }
    }

    /**
     * Determine the final filename
     */
    private function determineFilename(): string
    {
        $name = '';

        if ($this->filename) {
            $name = $this->filename;
        } elseif ($this->preserveOriginalFilename) {
            $name = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
        } elseif ($this->generateRandomFilename) {
            $name = Str::random($this->randomFilenameLength);
        } else {
            // Default: slugified original name with timestamp
            $originalName = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
            $name = Str::slug($originalName) . '_' . time();
        }

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
            $name = 'file_' . time();
        }

        return $name . ($extension ? '.' . $extension : '');
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
            $filename = 'file_' . time();
        }

        return $filename;
    }

    /**
     * Create a database record
     */
    private function createDatabaseRecord(array $result, string $disk): mixed
    {
        $model = new $this->model;

        try {
            return $model::create([
                'name' => $result['filename'],
                'path' => $result['path'],
                'original_name' => $result['original_name'],
                'mime_type' => $result['mime_type'],
                'size' => $result['size'],
                'disk' => $result['disk'],
                'is_public' => $this->public,
                'directory' => $this->directory,
            ]);
        } catch (\Exception $e) {
            // If database storage fails, we should clean up the uploaded file
            $this->cleanup($result['filename'], $disk);
            throw new \RuntimeException('Failed to store file information in database: ' . $e->getMessage());
        }
    }

    /**
     * Clean up files in case of errors
     */
    private function cleanup(?string $filename, ?string $disk): void
    {
        if ($filename && $disk) {
            try {
                $path = $this->directory ? $this->directory . '/' . $filename : $filename;
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Exception $e) {
                // Log the cleanup failure but don't throw
                logger()->warning('Failed to cleanup uploaded file', [
                    'filename' => $filename,
                    'disk' => $disk,
                    'path' => $path ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get file information without uploading (useful for validation)
     */
    public function getFileInfo(UploadedFile $file = null): array
    {
        if ($file) {
            $this->file($file);
        }

        if (!$this->file) {
            throw new \InvalidArgumentException('No file provided');
        }

        return [
            'original_name' => $this->file->getClientOriginalName(),
            'size' => $this->file->getSize(),
            'mime_type' => $this->file->getMimeType(),
            'extension' => $this->file->getClientOriginalExtension(),
            'is_valid' => $this->file->isValid(),
            'proposed_filename' => $this->determineFilename(),
        ];
    }

    /**
     * Validate a file without uploading
     */
    public function validate(UploadedFile $file = null): bool
    {
        if ($file) {
            $this->file($file);
        }

        try {
            $this->validateFile();
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }
}
