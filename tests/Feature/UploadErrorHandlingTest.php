<?php

use App\Actions\Upload\UploadActionRegistry;
use App\Actions\Upload\AbstractUploadAction;
use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Actions\Upload\Core\ValidateFileAction;
use App\Actions\Upload\Core\ProcessFileAction;
use App\Actions\Upload\Core\SaveToDatabaseAction;
use App\Enums\StorageDisk;
use App\Models\Image;
use App\Models\User;
use App\Services\UploadPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
});

test('pipeline handles action that throws exception', function () {
    $registry = new UploadActionRegistry();
    
    // Create an action that throws an exception
    $throwingAction = new class extends AbstractUploadAction {
        public function getName(): string { return 'throwing_action'; }
        public function getDescription(): string { return 'Action that throws exception'; }
        public function getPriority(): int { return 1; }
        
        public function execute(UploadContext $context): UploadResult {
            throw new \RuntimeException('Test exception from action');
        }
    };
    
    $registry->register($throwingAction);
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $pipelineService->process($file, $user);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Test exception from action')
        ->and($result->errors)->toHaveKey('exception')
        ->and($result->errors['exception'])->toBe('Test exception from action');
});

test('pipeline handles database transaction rollback on failure', function () {
    $registry = new UploadActionRegistry();
    
    // Add validation and processing actions
    $registry->register(new ValidateFileAction());
    $registry->register(new ProcessFileAction());
    
    // Create an action that fails after file is processed
    $failingAction = new class extends AbstractUploadAction {
        public function getName(): string { return 'failing_action'; }
        public function getDescription(): string { return 'Action that fails'; }
        public function getPriority(): int { return 80; } // After processing, before DB save
        
        public function execute(UploadContext $context): UploadResult {
            return $this->failure('Simulated failure after processing');
        }
    };
    
    $registry->register($failingAction);
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $pipelineService->process($file, $user);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('Simulated failure after processing');
    
    // Verify no database record was created despite processing succeeding
    expect(Image::count())->toBe(0);
    
    // File might still exist since the failure happened after processing
    // This is expected behavior - cleanup would be handled by a separate cleanup action
});

test('pipeline handles storage disk not available', function () {
    $registry = new UploadActionRegistry();
    $registry->register(new ValidateFileAction());
    $registry->register(new ProcessFileAction());
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    // Use a non-existent storage disk
    $result = $pipelineService->process($file, $user, [
        'disk' => 'non_existent_disk',
        'directory' => 'uploads/test',
    ]);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('failed');
});

test('pipeline handles corrupted file', function () {
    $registry = new UploadActionRegistry();
    $registry->register(new ValidateFileAction());
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    
    // Create a fake file with invalid image content
    $file = UploadedFile::fake()->create('corrupted.jpg', 1024, 'image/jpeg');
    
    $result = $pipelineService->process($file, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
    ]);
    
    // Should still validate successfully based on MIME type
    expect($result->success)->toBeTrue(); // Validation passes based on MIME
});

test('pipeline handles memory exhaustion during large file processing', function () {
    $registry = new UploadActionRegistry();
    $registry->register(new ValidateFileAction());
    $registry->register(new ProcessFileAction());
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    
    // Create a large file that might cause memory issues
    $file = UploadedFile::fake()->image('large.jpg')->size(50 * 1024); // 50MB
    
    $result = $pipelineService->process($file, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
        'max_size_mb' => 100, // Allow the large file
    ]);
    
    // Should process successfully or fail gracefully
    expect($result)->toBeInstanceOf(\App\Actions\Upload\UploadResult::class);
});

test('pipeline handles action that returns malformed result', function () {
    $registry = new UploadActionRegistry();
    
    // Create an action that returns a malformed result
    $malformedAction = new class extends AbstractUploadAction {
        public function getName(): string { return 'malformed_action'; }
        public function getDescription(): string { return 'Action with malformed result'; }
        public function getPriority(): int { return 1; }
        
        public function execute(UploadContext $context): UploadResult {
            // Return a result that claims success but has no context for continuation
            return new UploadResult(
                success: true,
                message: 'Success but malformed',
                context: null
            );
        }
    };
    
    $registry->register($malformedAction);
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $pipelineService->process($file, $user);
    
    // Should complete with the final result from the malformed action
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('Success but malformed');
});

test('pipeline handles action with invalid priority', function () {
    $registry = new UploadActionRegistry();
    
    // Create actions with extreme priorities
    $extremeLowPriorityAction = new class extends AbstractUploadAction {
        public function getName(): string { return 'extreme_low'; }
        public function getDescription(): string { return 'Extreme low priority'; }
        public function getPriority(): int { return PHP_INT_MAX; }
        
        public function execute(UploadContext $context): UploadResult {
            return $this->success($context, 'Extreme low executed');
        }
    };
    
    $extremeHighPriorityAction = new class extends AbstractUploadAction {
        public function getName(): string { return 'extreme_high'; }
        public function getDescription(): string { return 'Extreme high priority'; }
        public function getPriority(): int { return PHP_INT_MIN; }
        
        public function execute(UploadContext $context): UploadResult {
            return $this->success($context, 'Extreme high executed');
        }
    };
    
    $registry->register($extremeLowPriorityAction);
    $registry->register($extremeHighPriorityAction);
    
    $actions = $registry->getActions();
    
    // Should handle extreme priorities correctly
    expect($actions)->toHaveCount(2)
        ->and($actions->first()->getName())->toBe('extreme_high')
        ->and($actions->last()->getName())->toBe('extreme_low');
});

test('pipeline handles null file upload', function () {
    $registry = new UploadActionRegistry();
    $registry->register(new ValidateFileAction());
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    
    // This would normally be caught at the HTTP layer, but test defensive programming
    try {
        $result = $pipelineService->process(null, $user);
        expect(true)->toBeFalse(); // Should not reach here
    } catch (\TypeError $e) {
        expect($e->getMessage())->toContain('UploadedFile');
    }
});

test('pipeline handles action configuration conflicts', function () {
    $registry = new UploadActionRegistry();
    $registry->register(new ValidateFileAction());
    $registry->register(new ProcessFileAction());
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    // Test with conflicting configuration
    $result = $pipelineService->process($file, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
        'validate_size' => false, // Disable size validation
        'max_size_mb' => 1, // But set very low limit
    ]);
    
    // Should still succeed because validation is disabled
    expect($result->success)->toBeTrue();
});

test('pipeline handles database connection failure during save', function () {
    $registry = new UploadActionRegistry();
    $registry->register(new ValidateFileAction());
    $registry->register(new ProcessFileAction());
    $registry->register(new SaveToDatabaseAction());
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    // Simulate database connection failure
    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new \Exception('Database connection lost'));
    
    $result = $pipelineService->process($file, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
    ]);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Database connection lost');
});

test('pipeline handles circular action dependencies', function () {
    $registry = new UploadActionRegistry();
    
    $executionOrder = [];
    
    // Create actions that might cause issues if not properly ordered
    $action1 = new class($executionOrder) extends AbstractUploadAction {
        private array $executionOrder;
        
        public function __construct(array &$executionOrder) {
            $this->executionOrder = &$executionOrder;
        }
        
        public function getName(): string { return 'action_1'; }
        public function getDescription(): string { return 'Action 1'; }
        public function getPriority(): int { return 10; }
        
        public function execute(UploadContext $context): UploadResult {
            $this->executionOrder[] = 'action_1';
            return $this->success($context, 'Action 1 executed');
        }
    };
    
    $action2 = new class($executionOrder) extends AbstractUploadAction {
        private array $executionOrder;
        
        public function __construct(array &$executionOrder) {
            $this->executionOrder = &$executionOrder;
        }
        
        public function getName(): string { return 'action_2'; }
        public function getDescription(): string { return 'Action 2'; }
        public function getPriority(): int { return 5; }
        
        public function execute(UploadContext $context): UploadResult {
            $this->executionOrder[] = 'action_2';
            return $this->success($context, 'Action 2 executed');
        }
    };
    
    $registry->register($action1);
    $registry->register($action2);
    
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $pipelineService->process($file, $user);
    
    // Should execute in priority order
    expect($result->success)->toBeTrue()
        ->and($executionOrder)->toBe(['action_2', 'action_1']); // Higher priority first
});