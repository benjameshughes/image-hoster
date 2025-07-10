<?php

declare(strict_types=1);

namespace App\Actions\Upload;

use App\Actions\Upload\Contracts\UploadActionContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Registry for discovering and managing upload actions
 */
class UploadActionRegistry
{
    private Collection $actions;
    private bool $loaded = false;

    public function __construct()
    {
        $this->actions = collect();
    }

    /**
     * Register an action instance
     */
    public function register(UploadActionContract $action): self
    {
        $this->actions->put($action->getName(), $action);
        return $this;
    }

    /**
     * Get all registered actions sorted by priority
     */
    public function getActions(): Collection
    {
        $this->loadActionsIfNeeded();
        
        return $this->actions->sortBy(fn($action) => $action->getPriority());
    }

    /**
     * Get actions that can handle the given context
     */
    public function getActionsForContext(UploadContext $context): Collection
    {
        return $this->getActions()
            ->filter(fn($action) => $action->canHandle($context));
    }

    /**
     * Get a specific action by name
     */
    public function getAction(string $name): ?UploadActionContract
    {
        $this->loadActionsIfNeeded();
        
        return $this->actions->get($name);
    }

    /**
     * Check if an action is registered
     */
    public function hasAction(string $name): bool
    {
        $this->loadActionsIfNeeded();
        
        return $this->actions->has($name);
    }

    /**
     * Get action names grouped by namespace
     */
    public function getActionsByNamespace(): Collection
    {
        $this->loadActionsIfNeeded();
        
        return $this->actions->groupBy(function ($action) {
            $class = get_class($action);
            $parts = explode('\\', $class);
            return $parts[count($parts) - 2] ?? 'Unknown'; // Get the namespace part
        });
    }

    /**
     * Auto-discover and load actions from the filesystem
     */
    private function loadActionsIfNeeded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->autoDiscoverActions();
        $this->loaded = true;
    }

    /**
     * Auto-discover actions in the Actions/Upload directory
     */
    private function autoDiscoverActions(): void
    {
        $actionsPath = app_path('Actions/Upload');
        
        if (!File::exists($actionsPath)) {
            return;
        }

        $this->discoverActionsInDirectory($actionsPath, 'App\\Actions\\Upload');
    }

    /**
     * Recursively discover actions in a directory
     */
    private function discoverActionsInDirectory(string $directory, string $namespace): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file, $namespace);
            
            if ($this->isValidActionClass($className)) {
                try {
                    $action = app($className);
                    $this->register($action);
                } catch (\Exception $e) {
                    logger()->warning("Failed to register upload action: {$className}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Get the fully qualified class name from a file
     */
    private function getClassNameFromFile(\SplFileInfo $file, string $baseNamespace): string
    {
        $relativePath = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        return $baseNamespace . '\\' . $relativePath;
    }

    /**
     * Check if a class is a valid upload action
     */
    private function isValidActionClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);
        
        return !$reflection->isAbstract() 
            && !$reflection->isInterface()
            && $reflection->implementsInterface(UploadActionContract::class);
    }

    /**
     * Get configuration for all actions
     */
    public function getActionsConfiguration(): array
    {
        $this->loadActionsIfNeeded();
        
        return $this->actions->mapWithKeys(function ($action) {
            return [$action->getName() => [
                'name' => $action->getName(),
                'description' => $action->getDescription(),
                'priority' => $action->getPriority(),
                'options' => $action->getConfigurationOptions(),
                'class' => get_class($action),
            ]];
        })->toArray();
    }

    /**
     * Reset the registry (useful for testing)
     */
    public function reset(): void
    {
        $this->actions = collect();
        $this->loaded = false;
    }
}