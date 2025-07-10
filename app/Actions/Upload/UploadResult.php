<?php

declare(strict_types=1);

namespace App\Actions\Upload;

use App\Models\Media;
use Illuminate\Support\Collection;

/**
 * Result object that holds the outcome of an upload action
 */
readonly class UploadResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?Media $record = null,
        public ?string $path = null,
        public ?string $url = null,
        public ?string $filename = null,
        public ?int $size = null,
        public ?string $mimeType = null,
        public Collection $metadata = new Collection(),
        public array $errors = [],
        public ?UploadContext $context = null,
    ) {}

    /**
     * Create a successful result
     */
    public static function success(
        ?string $message = null,
        ?Media $record = null,
        ?string $path = null,
        ?string $url = null,
        ?string $filename = null,
        ?int $size = null,
        ?string $mimeType = null,
        Collection $metadata = new Collection(),
        ?UploadContext $context = null,
    ): self {
        return new self(
            success: true,
            message: $message,
            record: $record,
            path: $path,
            url: $url,
            filename: $filename,
            size: $size,
            mimeType: $mimeType,
            metadata: $metadata,
            context: $context,
        );
    }

    /**
     * Create a failed result
     */
    public static function failure(
        string $message,
        array $errors = [],
        ?UploadContext $context = null,
    ): self {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
            context: $context,
        );
    }

    /**
     * Create a result that continues processing
     */
    public static function continue(
        UploadContext $context,
        ?string $message = null,
        Collection $metadata = new Collection(),
    ): self {
        return new self(
            success: true,
            message: $message,
            metadata: $metadata,
            context: $context,
        );
    }

    /**
     * Check if the result indicates processing should continue
     */
    public function shouldContinue(): bool
    {
        return $this->success && $this->context !== null;
    }

    /**
     * Get the updated context for continued processing
     */
    public function getUpdatedContext(): ?UploadContext
    {
        if (!$this->context) {
            return null;
        }

        // Merge metadata back into context
        $updatedContext = $this->context;
        foreach ($this->metadata as $key => $value) {
            $updatedContext = $updatedContext->withMetadata($key, $value);
        }

        return $updatedContext;
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'record' => $this->record?->toArray(),
            'path' => $this->path,
            'url' => $this->url,
            'filename' => $this->filename,
            'size' => $this->size,
            'mime_type' => $this->mimeType,
            'metadata' => $this->metadata->toArray(),
            'errors' => $this->errors,
        ];
    }
}