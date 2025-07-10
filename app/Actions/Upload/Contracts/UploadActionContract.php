<?php

declare(strict_types=1);

namespace App\Actions\Upload\Contracts;

use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;

/**
 * Contract for upload actions that can be plugged into the upload pipeline
 */
interface UploadActionContract
{
    /**
     * Execute the upload action
     */
    public function execute(UploadContext $context): UploadResult;

    /**
     * Get the action name/identifier
     */
    public function getName(): string;

    /**
     * Get the action description
     */
    public function getDescription(): string;

    /**
     * Get the action priority (lower number = higher priority)
     */
    public function getPriority(): int;

    /**
     * Check if this action can handle the given context
     */
    public function canHandle(UploadContext $context): bool;

    /**
     * Get configuration options for this action
     */
    public function getConfigurationOptions(): array;
}