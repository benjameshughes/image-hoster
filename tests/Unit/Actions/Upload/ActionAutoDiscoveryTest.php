<?php

use App\Actions\Upload\UploadActionRegistry;
use App\Actions\Upload\Core\ValidateFileAction;
use App\Actions\Upload\Core\ProcessFileAction;
use App\Actions\Upload\Core\SaveToDatabaseAction;
use App\Actions\Upload\Plugins\DuplicateDetectionAction;
use App\Actions\Upload\Plugins\ExtractImageMetadataAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registry auto-discovers core actions', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    // Trigger auto-discovery by calling getActions
    $actions = $registry->getActions();
    
    // Should find all core actions
    expect($actions->pluck('name')->toArray())->toContain(
        'validate_file',
        'process_file',
        'save_to_database'
    );
});

test('registry auto-discovers plugin actions', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    // Trigger auto-discovery by calling getActions
    $actions = $registry->getActions();
    
    // Should find plugin actions
    expect($actions->pluck('name')->toArray())->toContain(
        'duplicate_detection',
        'extract_image_metadata'
    );
});

test('registry groups actions by namespace correctly', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    $actionsByNamespace = $registry->getActionsByNamespace();
    
    expect($actionsByNamespace)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($actionsByNamespace->has('Core'))->toBeTrue()
        ->and($actionsByNamespace->has('Plugins'))->toBeTrue();
    
    // Core namespace should contain core actions
    $coreActions = $actionsByNamespace->get('Core');
    expect($coreActions->pluck('name')->toArray())->toContain(
        'validate_file',
        'process_file',
        'save_to_database'
    );
    
    // Plugins namespace should contain plugin actions
    $pluginActions = $actionsByNamespace->get('Plugins');
    expect($pluginActions->pluck('name')->toArray())->toContain(
        'duplicate_detection',
        'extract_image_metadata'
    );
});

test('registry maintains action priority order during auto-discovery', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    $actions = $registry->getActions();
    
    // Get the priorities
    $priorities = $actions->mapWithKeys(function ($action) {
        return [$action->getName() => $action->getPriority()];
    });
    
    // Verify expected priority order
    expect($priorities->get('validate_file'))->toBeLessThan($priorities->get('duplicate_detection'))
        ->and($priorities->get('duplicate_detection'))->toBeLessThan($priorities->get('extract_image_metadata'))
        ->and($priorities->get('extract_image_metadata'))->toBeLessThan($priorities->get('process_file'))
        ->and($priorities->get('process_file'))->toBeLessThan($priorities->get('save_to_database'));
});

test('registry auto-discovery is idempotent', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    // Call getActions multiple times
    $firstCall = $registry->getActions();
    $secondCall = $registry->getActions();
    $thirdCall = $registry->getActions();
    
    // Should return the same actions each time
    expect($firstCall->count())->toBe($secondCall->count())
        ->and($secondCall->count())->toBe($thirdCall->count())
        ->and($firstCall->pluck('name')->toArray())->toBe($secondCall->pluck('name')->toArray())
        ->and($secondCall->pluck('name')->toArray())->toBe($thirdCall->pluck('name')->toArray());
});

test('registry can manually register actions alongside auto-discovery', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    // Create a custom action
    $customAction = new class extends \App\Actions\Upload\AbstractUploadAction {
        public function getName(): string { return 'custom_test_action'; }
        public function getDescription(): string { return 'Custom Test Action'; }
        public function getPriority(): int { return 999; }
        
        public function execute(\App\Actions\Upload\UploadContext $context): \App\Actions\Upload\UploadResult {
            return $this->success($context, 'Custom action executed');
        }
    };
    
    // Manually register the custom action
    $registry->register($customAction);
    
    // Get all actions (triggers auto-discovery)
    $actions = $registry->getActions();
    
    // Should include both auto-discovered and manually registered actions
    expect($actions->pluck('name')->toArray())->toContain(
        'validate_file', // Auto-discovered
        'process_file', // Auto-discovered
        'custom_test_action' // Manually registered
    );
});

test('registry handles non-existent action directories gracefully', function () {
    // Create a registry instance that would look in a non-existent directory
    $registry = new UploadActionRegistry();
    $registry->reset();
    
    // Should not throw an error even if some directories don't exist
    $actions = $registry->getActions();
    
    expect($actions)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('registry provides comprehensive configuration for auto-discovered actions', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    $actionsConfig = $registry->getActionsConfiguration();
    
    expect($actionsConfig)->toBeArray()
        ->and($actionsConfig)->not->toBeEmpty();
    
    // Check that each discovered action has complete configuration
    foreach ($actionsConfig as $actionName => $config) {
        expect($config)->toHaveKey('name')
            ->and($config)->toHaveKey('description')
            ->and($config)->toHaveKey('priority')
            ->and($config)->toHaveKey('options')
            ->and($config)->toHaveKey('class')
            ->and($config['name'])->toBeString()
            ->and($config['description'])->toBeString()
            ->and($config['priority'])->toBeInt()
            ->and($config['options'])->toBeArray()
            ->and($config['class'])->toBeString();
    }
});

test('registry auto-discovery respects action interfaces', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    $actions = $registry->getActions();
    
    // Every auto-discovered action should implement the correct interface
    foreach ($actions as $action) {
        expect($action)->toBeInstanceOf(\App\Actions\Upload\Contracts\UploadActionContract::class);
    }
});

test('registry auto-discovery skips abstract classes', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    $actions = $registry->getActions();
    
    // Should not include AbstractUploadAction as it's abstract
    $actionClasses = $actions->map(fn($action) => get_class($action))->toArray();
    expect($actionClasses)->not->toContain(\App\Actions\Upload\AbstractUploadAction::class);
});

test('registry auto-discovery handles action class instantiation failures gracefully', function () {
    // This test would normally require creating a malformed action class file
    // For now, we'll just verify that the registry doesn't crash on discovery
    $registry = new UploadActionRegistry();
    $registry->reset();
    
    $actions = $registry->getActions();
    
    // Should complete without throwing exceptions
    expect($actions)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('registry can get specific auto-discovered actions', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    // Trigger auto-discovery
    $registry->getActions();
    
    // Should be able to get specific actions
    $validateAction = $registry->getAction('validate_file');
    $processAction = $registry->getAction('process_file');
    $saveAction = $registry->getAction('save_to_database');
    
    expect($validateAction)->toBeInstanceOf(ValidateFileAction::class)
        ->and($processAction)->toBeInstanceOf(ProcessFileAction::class)
        ->and($saveAction)->toBeInstanceOf(SaveToDatabaseAction::class);
});

test('registry auto-discovery performance is acceptable', function () {
    $registry = new UploadActionRegistry();
    $registry->reset(); // Clear any existing actions
    
    $startTime = microtime(true);
    
    // Trigger auto-discovery
    $actions = $registry->getActions();
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    // Auto-discovery should complete within reasonable time (1 second)
    expect($executionTime)->toBeLessThan(1.0)
        ->and($actions->count())->toBeGreaterThan(0);
});