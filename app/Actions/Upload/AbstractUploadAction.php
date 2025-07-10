<?php

declare(strict_types=1);

namespace App\Actions\Upload;

use App\Actions\Upload\Contracts\UploadActionContract;

/**
 * Abstract base class for upload actions with common functionality
 */
abstract class AbstractUploadAction implements UploadActionContract
{
    /**
     * Default priority for actions (lower number = higher priority)
     */
    protected int $priority = 100;

    /**
     * Whether this action is enabled by default
     */
    protected bool $enabled = true;

    /**
     * Configuration options for this action
     */
    protected array $configurationOptions = [];

    /**
     * Get the action priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if this action can handle the given context
     * Override this method to add specific handling logic
     */
    public function canHandle(UploadContext $context): bool
    {
        return $this->enabled;
    }

    /**
     * Get configuration options for this action
     */
    public function getConfigurationOptions(): array
    {
        return $this->configurationOptions;
    }

    /**
     * Check if the action is enabled in the context configuration
     */
    protected function isEnabled(UploadContext $context): bool
    {
        $configKey = $this->getConfigurationKey();
        return $context->getConfiguration($configKey, $this->enabled);
    }

    /**
     * Get the configuration key for this action
     */
    protected function getConfigurationKey(): string
    {
        return strtolower(str_replace('Action', '', class_basename($this)));
    }

    /**
     * Log action execution
     */
    protected function log(string $message, array $context = []): void
    {
        logger()->info("[{$this->getName()}] {$message}", $context);
    }

    /**
     * Log action errors
     */
    protected function logError(string $message, array $context = []): void
    {
        logger()->error("[{$this->getName()}] {$message}", $context);
    }

    /**
     * Create a success result with context continuation
     */
    protected function success(
        UploadContext $context,
        ?string $message = null,
        array $metadata = [],
    ): UploadResult {
        $updatedContext = $context;
        foreach ($metadata as $key => $value) {
            $updatedContext = $updatedContext->withMetadata($key, $value);
        }

        return UploadResult::continue(
            context: $updatedContext,
            message: $message ?? "Action '{$this->getName()}' completed successfully",
            metadata: collect($metadata),
        );
    }

    /**
     * Create a failure result
     */
    protected function failure(
        string $message,
        array $errors = [],
        ?UploadContext $context = null,
    ): UploadResult {
        $this->logError($message, ['errors' => $errors]);

        return UploadResult::failure(
            message: $message,
            errors: $errors,
            context: $context,
        );
    }

    /**
     * Validate file size
     */
    protected function validateFileSize(UploadContext $context): bool
    {
        $maxSizeBytes = $context->maxSizeMB * 1024 * 1024;
        return $context->getFileSize() <= $maxSizeBytes;
    }

    /**
     * Validate MIME type
     */
    protected function validateMimeType(UploadContext $context): bool
    {
        if (empty($context->allowedMimeTypes)) {
            return true;
        }

        return in_array($context->getMimeType(), $context->allowedMimeTypes);
    }

    /**
     * Format file size for display
     */
    protected function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / (1024 ** 3), 2) . ' GB',
            $bytes >= 1024 ** 2 => round($bytes / (1024 ** 2), 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }
}