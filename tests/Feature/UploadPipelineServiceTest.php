<?php

use App\Actions\Upload\UploadActionRegistry;
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

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
    
    // Create registry and service
    $this->registry = new UploadActionRegistry();
    $this->registry->reset();
    
    // Register core actions manually for testing
    $this->registry->register(new ValidateFileAction());
    $this->registry->register(new ProcessFileAction());
    $this->registry->register(new SaveToDatabaseAction());
    
    $this->pipelineService = new UploadPipelineService($this->registry);
});

test('pipeline service can process a file through all actions', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1024);
    
    $result = $this->pipelineService->process($file, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
        'extract_metadata' => false, // Disable metadata to simplify test
    ]);
    
    expect($result->success)->toBeTrue()
        ->and($result->record)->toBeInstanceOf(Image::class)
        ->and($result->path)->not->toBeNull()
        ->and($result->url)->not->toBeNull()
        ->and($result->filename)->not->toBeNull()
        ->and($result->size)->toBe(1024)
        ->and($result->mimeType)->toBe('image/jpeg');
    
    // Verify database record was created
    expect(Image::count())->toBe(1);
    
    $image = Image::first();
    expect($image->user_id)->toBe($user->id)
        ->and($image->original_filename)->toBe('test.jpg')
        ->and($image->size)->toBe(1024)
        ->and($image->mime_type)->toBe('image/jpeg');
    
    // Verify file was stored
    expect(Storage::disk('local')->exists($image->path))->toBeTrue();
});

test('pipeline service handles validation failures', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    
    $result = $this->pipelineService->process($file, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
        'allowed_mime_types' => ['image/jpeg', 'image/png'],
    ]);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('validation failed')
        ->and($result->record)->toBeNull();
    
    // Verify no database record was created
    expect(Image::count())->toBe(0);
    
    // Verify no file was stored
    $files = Storage::disk('local')->allFiles();
    expect($files)->toBeEmpty();
});

test('pipeline service handles storage failures', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    // Use a non-existent disk to simulate failure
    $result = $this->pipelineService->process($file, $user, [
        'disk' => 'non_existent_disk',
        'directory' => 'uploads/test',
    ]);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('processing failed')
        ->and($result->record)->toBeNull();
    
    // Verify no database record was created
    expect(Image::count())->toBe(0);
});

test('pipeline service can process multiple files', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg', 800, 600),
        UploadedFile::fake()->image('test2.png', 1200, 800),
        UploadedFile::fake()->image('test3.gif', 400, 300),
    ];
    
    $results = $this->pipelineService->processMany($files, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
        'extract_metadata' => false,
    ]);
    
    expect($results)->toHaveCount(3);
    
    foreach ($results as $result) {
        expect($result->success)->toBeTrue()
            ->and($result->record)->toBeInstanceOf(Image::class);
    }
    
    // Verify all database records were created
    expect(Image::count())->toBe(3);
});

test('pipeline service handles mixed success and failure in batch', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('valid.jpg'),
        UploadedFile::fake()->create('invalid.pdf', 1024, 'application/pdf'),
        UploadedFile::fake()->image('valid2.png'),
    ];
    
    $results = $this->pipelineService->processMany($files, $user, [
        'disk' => 'local',
        'directory' => 'uploads/test',
        'allowed_mime_types' => ['image/jpeg', 'image/png'],
        'extract_metadata' => false,
    ]);
    
    expect($results)->toHaveCount(3)
        ->and($results[0]->success)->toBeTrue() // valid.jpg
        ->and($results[1]->success)->toBeFalse() // invalid.pdf
        ->and($results[2]->success)->toBeTrue(); // valid2.png
    
    // Verify only successful uploads created database records
    expect(Image::count())->toBe(2);
});

test('pipeline service uses default configuration when none provided', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $this->pipelineService->process($file, $user);
    
    expect($result->success)->toBeTrue();
    
    // Check that default directory was used
    $image = Image::first();
    $expectedPrefix = "uploads/{$user->id}/" . now()->format('Y/m');
    expect($image->path)->toStartWith($expectedPrefix);
});

test('pipeline service can validate configuration', function () {
    $validConfig = [
        'disk' => 'local',
        'max_size_mb' => 50,
        'allowed_mime_types' => ['image/jpeg'],
    ];
    
    $invalidConfig = [
        'disk' => 'invalid_disk',
        'max_size_mb' => -1,
        'allowed_mime_types' => 'not_an_array',
    ];
    
    $validErrors = $this->pipelineService->validateConfiguration($validConfig);
    $invalidErrors = $this->pipelineService->validateConfiguration($invalidConfig);
    
    expect($validErrors)->toBeEmpty()
        ->and($invalidErrors)->not->toBeEmpty()
        ->and($invalidErrors)->toHaveKey('disk')
        ->and($invalidErrors)->toHaveKey('max_size_mb')
        ->and($invalidErrors)->toHaveKey('allowed_mime_types');
});

test('pipeline service can get available actions configuration', function () {
    $actions = $this->pipelineService->getAvailableActions();
    
    expect($actions)->toBeArray()
        ->and($actions)->toHaveCount(3) // Our test actions
        ->and($actions)->toHaveKey('validate_file')
        ->and($actions)->toHaveKey('process_file')
        ->and($actions)->toHaveKey('save_to_database');
    
    expect($actions['validate_file'])->toHaveKey('name')
        ->and($actions['validate_file'])->toHaveKey('description')
        ->and($actions['validate_file'])->toHaveKey('priority')
        ->and($actions['validate_file'])->toHaveKey('options')
        ->and($actions['validate_file'])->toHaveKey('class');
});

test('pipeline service fails gracefully when no actions available', function () {
    // Create empty registry
    $emptyRegistry = new UploadActionRegistry();
    $emptyPipeline = new UploadPipelineService($emptyRegistry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $emptyPipeline->process($file, $user);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('No upload actions available');
});

test('pipeline service handles action exceptions gracefully', function () {
    // Create a mock action that throws an exception
    $mockAction = new class extends \App\Actions\Upload\AbstractUploadAction {
        public function getName(): string { return 'throwing_action'; }
        public function getDescription(): string { return 'Test Action That Throws'; }
        public function getPriority(): int { return 1; }
        public function execute(\App\Actions\Upload\UploadContext $context): \App\Actions\Upload\UploadResult {
            throw new \Exception('Test exception');
        }
    };
    
    $registry = new UploadActionRegistry();
    $registry->register($mockAction);
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $result = $pipelineService->process($file, $user);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Test exception')
        ->and($result->errors)->toHaveKey('exception');
});

test('pipeline service respects action priority order', function () {
    // Create actions with different priorities
    $executionOrder = [];
    
    $lowPriorityAction = new class($executionOrder) extends \App\Actions\Upload\AbstractUploadAction {
        private array $executionOrder;
        
        public function __construct(array &$executionOrder) {
            $this->executionOrder = &$executionOrder;
        }
        
        public function getName(): string { return 'low_priority'; }
        public function getDescription(): string { return 'Low Priority Action'; }
        public function getPriority(): int { return 100; }
        
        public function execute(\App\Actions\Upload\UploadContext $context): \App\Actions\Upload\UploadResult {
            $this->executionOrder[] = 'low_priority';
            return $this->success($context, 'Low priority executed');
        }
    };
    
    $highPriorityAction = new class($executionOrder) extends \App\Actions\Upload\AbstractUploadAction {
        private array $executionOrder;
        
        public function __construct(array &$executionOrder) {
            $this->executionOrder = &$executionOrder;
        }
        
        public function getName(): string { return 'high_priority'; }
        public function getDescription(): string { return 'High Priority Action'; }
        public function getPriority(): int { return 1; }
        
        public function execute(\App\Actions\Upload\UploadContext $context): \App\Actions\Upload\UploadResult {
            $this->executionOrder[] = 'high_priority';
            return $this->success($context, 'High priority executed');
        }
    };
    
    $registry = new UploadActionRegistry();
    $registry->register($lowPriorityAction);
    $registry->register($highPriorityAction);
    $pipelineService = new UploadPipelineService($registry);
    
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $pipelineService->process($file, $user);
    
    expect($executionOrder)->toBe(['high_priority', 'low_priority']);
});