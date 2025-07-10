<?php

use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\Contracts\UploadActionContract;
use App\Actions\Upload\UploadActionRegistry;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Enums\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

// Create test action classes for testing
class TestAction1 extends AbstractUploadAction
{
    protected int $priority = 10;

    public function getName(): string
    {
        return 'test_action_1';
    }

    public function getDescription(): string
    {
        return 'Test Action 1';
    }

    public function execute(UploadContext $context): UploadResult
    {
        return $this->success($context, 'Test action 1 executed');
    }
}

class TestAction2 extends AbstractUploadAction
{
    protected int $priority = 5; // Higher priority (lower number)

    public function getName(): string
    {
        return 'test_action_2';
    }

    public function getDescription(): string
    {
        return 'Test Action 2';
    }

    public function execute(UploadContext $context): UploadResult
    {
        return $this->success($context, 'Test action 2 executed');
    }
}

class DisabledTestAction extends AbstractUploadAction
{
    protected int $priority = 15;
    protected bool $enabled = false;

    public function getName(): string
    {
        return 'disabled_test_action';
    }

    public function getDescription(): string
    {
        return 'Disabled Test Action';
    }

    public function execute(UploadContext $context): UploadResult
    {
        return $this->success($context, 'Disabled action executed');
    }
}

class ConditionalTestAction extends AbstractUploadAction
{
    protected int $priority = 20;

    public function getName(): string
    {
        return 'conditional_test_action';
    }

    public function getDescription(): string
    {
        return 'Conditional Test Action';
    }

    public function canHandle(UploadContext $context): bool
    {
        return $context->isImage() && parent::canHandle($context);
    }

    public function execute(UploadContext $context): UploadResult
    {
        return $this->success($context, 'Conditional action executed');
    }
}

beforeEach(function () {
    // Reset registry for each test
    $this->registry = new UploadActionRegistry();
    $this->registry->reset();
});

test('registry can register actions manually', function () {
    $action1 = new TestAction1();
    $action2 = new TestAction2();
    
    $this->registry->register($action1);
    $this->registry->register($action2);
    
    expect($this->registry->hasAction('test_action_1'))->toBeTrue()
        ->and($this->registry->hasAction('test_action_2'))->toBeTrue()
        ->and($this->registry->hasAction('non_existent'))->toBeFalse();
});

test('registry can retrieve specific actions', function () {
    $action1 = new TestAction1();
    $this->registry->register($action1);
    
    $retrieved = $this->registry->getAction('test_action_1');
    
    expect($retrieved)->toBe($action1)
        ->and($this->registry->getAction('non_existent'))->toBeNull();
});

test('registry returns actions sorted by priority', function () {
    $action1 = new TestAction1(); // Priority 10
    $action2 = new TestAction2(); // Priority 5 (higher priority)
    
    $this->registry->register($action1);
    $this->registry->register($action2);
    
    $actions = $this->registry->getActions();
    
    expect($actions)->toHaveCount(2)
        ->and($actions->first()->getName())->toBe('test_action_2') // Higher priority first
        ->and($actions->last()->getName())->toBe('test_action_1');
});

test('registry can filter actions by context capability', function () {
    $user = User::factory()->create();
    $imageFile = UploadedFile::fake()->image('test.jpg');
    $textFile = UploadedFile::fake()->create('test.txt', 1024, 'text/plain');
    
    $imageContext = new UploadContext(
        file: $imageFile,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    $textContext = new UploadContext(
        file: $textFile,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    $action1 = new TestAction1(); // Can handle anything
    $conditionalAction = new ConditionalTestAction(); // Only images
    $disabledAction = new DisabledTestAction(); // Disabled
    
    $this->registry->register($action1);
    $this->registry->register($conditionalAction);
    $this->registry->register($disabledAction);
    
    $imageActions = $this->registry->getActionsForContext($imageContext);
    $textActions = $this->registry->getActionsForContext($textContext);
    
    expect($imageActions)->toHaveCount(2) // TestAction1 + ConditionalTestAction
        ->and($textActions)->toHaveCount(1); // Only TestAction1
});

test('registry can group actions by namespace', function () {
    $action1 = new TestAction1();
    $action2 = new TestAction2();
    
    $this->registry->register($action1);
    $this->registry->register($action2);
    
    $grouped = $this->registry->getActionsByNamespace();
    
    expect($grouped)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($grouped)->toHaveCount(1); // All test actions are in the same "namespace"
});

test('registry can provide configuration for all actions', function () {
    $action1 = new TestAction1();
    $action2 = new TestAction2();
    
    $this->registry->register($action1);
    $this->registry->register($action2);
    
    $config = $this->registry->getActionsConfiguration();
    
    expect($config)->toBeArray()
        ->and($config)->toHaveCount(2)
        ->and($config['test_action_1'])->toBeArray()
        ->and($config['test_action_1']['name'])->toBe('test_action_1')
        ->and($config['test_action_1']['description'])->toBe('Test Action 1')
        ->and($config['test_action_1']['priority'])->toBe(10)
        ->and($config['test_action_1']['options'])->toBeArray()
        ->and($config['test_action_1']['class'])->toBe(TestAction1::class);
});

test('registry can be reset', function () {
    $action1 = new TestAction1();
    $this->registry->register($action1);
    
    expect($this->registry->hasAction('test_action_1'))->toBeTrue();
    
    $this->registry->reset();
    
    expect($this->registry->hasAction('test_action_1'))->toBeFalse()
        ->and($this->registry->getActions())->toBeEmpty();
});

test('registry handles duplicate action names', function () {
    $action1 = new TestAction1();
    $action1Duplicate = new TestAction1(); // Same name
    
    $this->registry->register($action1);
    $this->registry->register($action1Duplicate);
    
    // Should overwrite the first one
    expect($this->registry->getActions())->toHaveCount(1)
        ->and($this->registry->getAction('test_action_1'))->toBe($action1Duplicate);
});

test('registry returns empty collection when no actions can handle context', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.txt', 1024, 'text/plain');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    // Register only the conditional action that requires images
    $conditionalAction = new ConditionalTestAction();
    $this->registry->register($conditionalAction);
    
    $actions = $this->registry->getActionsForContext($context);
    
    expect($actions)->toBeEmpty();
});

test('registry maintains action order by priority across multiple registrations', function () {
    $lowPriority = new TestAction1(); // Priority 10
    $highPriority = new TestAction2(); // Priority 5
    $mediumPriority = new ConditionalTestAction(); // Priority 20
    
    // Register in random order
    $this->registry->register($mediumPriority);
    $this->registry->register($lowPriority);
    $this->registry->register($highPriority);
    
    $actions = $this->registry->getActions();
    $priorities = $actions->map(fn($action) => $action->getPriority())->toArray();
    
    expect($priorities)->toBe([5, 10, 20]); // Should be sorted by priority
});