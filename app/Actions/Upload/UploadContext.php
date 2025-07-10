<?php

declare(strict_types=1);

namespace App\Actions\Upload;

use App\Enums\StorageDisk;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Context object that holds all upload-related data and state
 */
readonly class UploadContext
{
    public function __construct(
        public UploadedFile $file,
        public User $user,
        public StorageDisk $disk,
        public string $directory,
        public bool $isPublic = true,
        public bool $randomizeFilename = true,
        public bool $extractMetadata = true,
        public bool $checkDuplicates = false,
        public int $maxSizeMB = 50,
        public array $allowedMimeTypes = [],
        public Collection $metadata = new Collection(),
        public array $configuration = [],
        public ?string $sessionId = null,
        public array $processingState = [],
    ) {}

    /**
     * Create a new context with updated metadata
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata->put($key, $value);
        
        return new self(
            file: $this->file,
            user: $this->user,
            disk: $this->disk,
            directory: $this->directory,
            isPublic: $this->isPublic,
            randomizeFilename: $this->randomizeFilename,
            extractMetadata: $this->extractMetadata,
            checkDuplicates: $this->checkDuplicates,
            maxSizeMB: $this->maxSizeMB,
            allowedMimeTypes: $this->allowedMimeTypes,
            metadata: $metadata,
            configuration: $this->configuration,
            sessionId: $this->sessionId,
            processingState: $this->processingState,
        );
    }

    /**
     * Create a new context with updated configuration
     */
    public function withConfiguration(array $config): self
    {
        return new self(
            file: $this->file,
            user: $this->user,
            disk: $this->disk,
            directory: $this->directory,
            isPublic: $this->isPublic,
            randomizeFilename: $this->randomizeFilename,
            extractMetadata: $this->extractMetadata,
            checkDuplicates: $this->checkDuplicates,
            maxSizeMB: $this->maxSizeMB,
            allowedMimeTypes: $this->allowedMimeTypes,
            metadata: $this->metadata,
            configuration: array_merge($this->configuration, $config),
            sessionId: $this->sessionId,
            processingState: $this->processingState,
        );
    }

    /**
     * Create a new context with updated processing state
     */
    public function withProcessingState(string $key, mixed $value): self
    {
        $processingState = array_merge($this->processingState, [$key => $value]);
        
        return new self(
            file: $this->file,
            user: $this->user,
            disk: $this->disk,
            directory: $this->directory,
            isPublic: $this->isPublic,
            randomizeFilename: $this->randomizeFilename,
            extractMetadata: $this->extractMetadata,
            checkDuplicates: $this->checkDuplicates,
            maxSizeMB: $this->maxSizeMB,
            allowedMimeTypes: $this->allowedMimeTypes,
            metadata: $this->metadata,
            configuration: $this->configuration,
            sessionId: $this->sessionId,
            processingState: $processingState,
        );
    }

    /**
     * Get the original filename
     */
    public function getOriginalFilename(): string
    {
        return $this->file->getClientOriginalName();
    }

    /**
     * Get the file size in bytes
     */
    public function getFileSize(): int
    {
        return $this->file->getSize();
    }

    /**
     * Get the file MIME type
     */
    public function getMimeType(): string
    {
        return $this->file->getMimeType() ?? 'application/octet-stream';
    }

    /**
     * Get the file extension
     */
    public function getExtension(): string
    {
        return $this->file->getClientOriginalExtension();
    }

    /**
     * Check if the file is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->getMimeType(), 'image/');
    }

    /**
     * Get metadata value or default
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata->get($key, $default);
    }

    /**
     * Get configuration value or default
     */
    public function getConfiguration(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    /**
     * Get processing state value or default
     */
    public function getProcessingState(string $key, mixed $default = null): mixed
    {
        return $this->processingState[$key] ?? $default;
    }
}