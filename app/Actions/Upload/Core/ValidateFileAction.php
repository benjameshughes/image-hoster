<?php

declare(strict_types=1);

namespace App\Actions\Upload\Core;

use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Enums\AllowedImageType;

/**
 * Core validation action for uploaded files
 */
class ValidateFileAction extends AbstractUploadAction
{
    protected int $priority = 10; // High priority - validate first

    protected array $configurationOptions = [
        'validate_size' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Validate file size against maximum allowed size',
        ],
        'validate_mime_type' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Validate file MIME type against allowed types',
        ],
        'validate_extension' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Validate file extension',
        ],
    ];

    public function getName(): string
    {
        return 'validate_file';
    }

    public function getDescription(): string
    {
        return 'Validates uploaded file size, type, and extension';
    }

    public function execute(UploadContext $context): UploadResult
    {
        $this->log('Starting file validation', [
            'filename' => $context->getOriginalFilename(),
            'size' => $context->getFileSize(),
            'mime_type' => $context->getMimeType(),
        ]);

        $errors = [];

        // Validate file size
        if ($context->getConfiguration('validate_size', true)) {
            if (!$this->validateFileSize($context)) {
                $errors[] = sprintf(
                    'File size (%s) exceeds maximum allowed size (%dMB)',
                    $this->formatFileSize($context->getFileSize()),
                    $context->maxSizeMB
                );
            }
        }

        // Validate MIME type
        if ($context->getConfiguration('validate_mime_type', true)) {
            if (!$this->validateMimeType($context)) {
                $allowedTypes = empty($context->allowedMimeTypes) 
                    ? array_map(fn($type) => $type->mimeType(), AllowedImageType::cases())
                    : $context->allowedMimeTypes;
                
                $errors[] = sprintf(
                    'File type (%s) is not allowed. Allowed types: %s',
                    $context->getMimeType(),
                    implode(', ', $allowedTypes)
                );
            }
        }

        // Validate extension
        if ($context->getConfiguration('validate_extension', true)) {
            $extension = strtolower($context->getExtension());
            $allowedExtensions = collect(AllowedImageType::cases())
                ->flatMap(fn($type) => $type->getExtensions())
                ->unique()
                ->toArray();

            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = sprintf(
                    'File extension (%s) is not allowed. Allowed extensions: %s',
                    $extension,
                    implode(', ', $allowedExtensions)
                );
            }
        }

        if (!empty($errors)) {
            return $this->failure(
                'File validation failed',
                $errors,
                $context
            );
        }

        return $this->success(
            $context,
            'File validation passed',
            [
                'validated_at' => now()->toISOString(),
                'file_size' => $context->getFileSize(),
                'file_size_formatted' => $this->formatFileSize($context->getFileSize()),
                'mime_type' => $context->getMimeType(),
                'extension' => $context->getExtension(),
            ]
        );
    }

    public function canHandle(UploadContext $context): bool
    {
        return parent::canHandle($context) && $this->isEnabled($context);
    }
}