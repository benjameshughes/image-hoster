<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Upload\UploadActionRegistry;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Enums\StorageDisk;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service that orchestrates the upload pipeline using actions
 */
class UploadPipelineService
{
    public function __construct(
        private UploadActionRegistry $registry,
    ) {}

    /**
     * Process a file through the upload pipeline
     */
    public function process(
        UploadedFile $file,
        User $user,
        array $configuration = []
    ): UploadResult {
        // Create the upload context
        $context = $this->createContext($file, $user, $configuration);

        // Get actions that can handle this context
        $actions = $this->registry->getActionsForContext($context);

        if ($actions->isEmpty()) {
            return UploadResult::failure(
                'No upload actions available to process this file',
                ['available_actions' => 0],
                $context
            );
        }

        // Execute actions in priority order
        $currentContext = $context;
        $executedActions = [];

        foreach ($actions as $action) {
            try {
                logger()->debug("Executing upload action: {$action->getName()}", [
                    'file' => $file->getClientOriginalName(),
                    'action' => get_class($action),
                ]);

                $result = $action->execute($currentContext);
                $executedActions[] = $action->getName();

                if (!$result->success) {
                    logger()->error("Upload action failed: {$action->getName()}", [
                        'file' => $file->getClientOriginalName(),
                        'message' => $result->message,
                        'errors' => $result->errors,
                    ]);

                    return $result;
                }

                // If the action returned a final result (not continuing), return it
                if (!$result->shouldContinue()) {
                    logger()->info("Upload pipeline completed with final result", [
                        'file' => $file->getClientOriginalName(),
                        'executed_actions' => $executedActions,
                        'final_action' => $action->getName(),
                    ]);

                    return $result;
                }

                // Update context for next action
                $currentContext = $result->getUpdatedContext() ?? $currentContext;

            } catch (\Exception $e) {
                logger()->error("Upload action threw exception: {$action->getName()}", [
                    'file' => $file->getClientOriginalName(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return UploadResult::failure(
                    "Action '{$action->getName()}' failed: {$e->getMessage()}",
                    ['exception' => $e->getMessage()],
                    $currentContext
                );
            }
        }

        // If we get here, no action provided a final result
        logger()->warning("Upload pipeline completed without final result", [
            'file' => $file->getClientOriginalName(),
            'executed_actions' => $executedActions,
        ]);

        return UploadResult::failure(
            'Upload pipeline completed but no action provided a final result',
            ['executed_actions' => $executedActions],
            $currentContext
        );
    }

    /**
     * Process multiple files
     */
    public function processMany(
        array $files,
        User $user,
        array $configuration = []
    ): Collection {
        return collect($files)->map(function ($file) use ($user, $configuration) {
            return $this->process($file, $user, $configuration);
        });
    }

    /**
     * Get available actions for configuration
     */
    public function getAvailableActions(): array
    {
        return $this->registry->getActionsConfiguration();
    }

    /**
     * Create upload context from parameters
     */
    private function createContext(
        UploadedFile $file,
        User $user,
        array $configuration
    ): UploadContext {
        $disk = StorageDisk::tryFrom($configuration['disk'] ?? 'spaces') ?? StorageDisk::SPACES;
        $directory = $configuration['directory'] ?? $this->getDefaultDirectory($user);
        $sessionId = $configuration['session_id'] ?? Str::uuid()->toString();

        return new UploadContext(
            file: $file,
            user: $user,
            disk: $disk,
            directory: $directory,
            isPublic: $configuration['is_public'] ?? true,
            randomizeFilename: $configuration['randomize_filename'] ?? true,
            extractMetadata: $configuration['extract_metadata'] ?? true,
            checkDuplicates: $configuration['check_duplicates'] ?? false,
            maxSizeMB: $configuration['max_size_mb'] ?? 50,
            allowedMimeTypes: $configuration['allowed_mime_types'] ?? [],
            metadata: collect($configuration['metadata'] ?? []),
            configuration: $configuration,
            sessionId: $sessionId,
        );
    }

    /**
     * Get default upload directory for user
     */
    private function getDefaultDirectory(User $user): string
    {
        $userId = $user->id;
        $date = now()->format('Y/m');

        return "uploads/{$userId}/{$date}";
    }

    /**
     * Validate configuration
     */
    public function validateConfiguration(array $configuration): array
    {
        $errors = [];

        // Validate disk
        if (isset($configuration['disk'])) {
            $disk = StorageDisk::tryFrom($configuration['disk']);
            if (!$disk) {
                $errors['disk'] = 'Invalid storage disk specified';
            }
        }

        // Validate max size
        if (isset($configuration['max_size_mb'])) {
            $maxSize = $configuration['max_size_mb'];
            if (!is_numeric($maxSize) || $maxSize <= 0 || $maxSize > 100) {
                $errors['max_size_mb'] = 'Max size must be between 1 and 100 MB';
            }
        }

        // Validate MIME types
        if (isset($configuration['allowed_mime_types']) && !is_array($configuration['allowed_mime_types'])) {
            $errors['allowed_mime_types'] = 'Allowed MIME types must be an array';
        }

        return $errors;
    }
}