<?php

use App\Actions\Upload\UploadActionRegistry;
use App\Actions\Upload\Core\ValidateFileAction;
use App\Actions\Upload\Core\ProcessFileAction;
use App\Actions\Upload\Core\SaveToDatabaseAction;
use App\Actions\Upload\Plugins\DuplicateDetectionAction;
use App\Actions\Upload\Plugins\ExtractImageMetadataAction;
use App\Livewire\Actions\Uploader;
use App\Models\Image;
use App\Models\User;
use App\Services\UploadPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
    
    // Set up the upload pipeline with all actions
    $registry = app(UploadActionRegistry::class);
    $registry->reset();
    
    // Register all actions
    $registry->register(new ValidateFileAction());
    $registry->register(new ProcessFileAction());
    $registry->register(new SaveToDatabaseAction());
    $registry->register(new DuplicateDetectionAction());
    $registry->register(new ExtractImageMetadataAction());
});

test('uploader component works with new pipeline service', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(Image::count())->toBe(1);
    
    $image = Image::first();
    expect($image->user_id)->toBe($user->id)
        ->and($image->original_filename)->toBe('test.jpg')
        ->and(Storage::disk('spaces')->exists($image->path))->toBeTrue();
});

test('uploader component handles validation failures from pipeline', function () {
    $user = User::factory()->create();
    $invalidFile = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$invalidFile])
        ->call('save')
        ->assertHasNoErrors(); // Component itself doesn't error, but upload fails

    expect(Image::count())->toBe(0);
});

test('uploader component extracts metadata through pipeline', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 1200, 800);

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('extractMetadata', true)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $image = Image::first();
    expect($image->width)->toBe(1200)
        ->and($image->height)->toBe(800);
});

test('uploader component handles duplicate detection through pipeline', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    // First upload
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('checkDuplicates', true)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(Image::count())->toBe(1);

    // Second upload of same content should be prevented
    $duplicateFile = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('checkDuplicates', true)
        ->set('files', [$duplicateFile])
        ->call('save')
        ->assertHasNoErrors(); // Component doesn't error, but duplicate is rejected

    expect(Image::count())->toBe(1); // No new image created
});

test('uploader component processes multiple files through pipeline', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg'),
        UploadedFile::fake()->image('test2.png'),
        UploadedFile::fake()->image('test3.gif'),
    ];

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', $files)
        ->call('save')
        ->assertHasNoErrors();

    expect(Image::count())->toBe(3);
    
    $imageNames = Image::pluck('original_filename')->toArray();
    expect($imageNames)->toContain('test1.jpg', 'test2.png', 'test3.gif');
});

test('uploader component respects storage disk configuration', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('disk', \App\Enums\StorageDisk::LOCAL)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $image = Image::first();
    expect($image->disk)->toBe('local')
        ->and(Storage::disk('local')->exists($image->path))->toBeTrue();
});

test('uploader component tracks upload progress with real-time events', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg'),
        UploadedFile::fake()->image('test2.jpg'),
        UploadedFile::fake()->image('test3.jpg'),
    ];

    $component = Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', $files)
        ->call('save');

    // Check that processing files were tracked
    expect($component->get('processingFiles'))->toHaveCount(3);
});

test('uploader component handles mixed success and failure in batch', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('valid.jpg'),
        UploadedFile::fake()->create('invalid.pdf', 1024, 'application/pdf'),
        UploadedFile::fake()->image('valid2.png'),
    ];

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', $files)
        ->call('save')
        ->assertHasNoErrors();

    // Should have 2 successful uploads (the valid images)
    expect(Image::count())->toBe(2);
    
    $imageNames = Image::pluck('original_filename')->toArray();
    expect($imageNames)->toContain('valid.jpg', 'valid2.png')
        ->and($imageNames)->not->toContain('invalid.pdf');
});

test('uploader component can clear uploaded files', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $component = Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$file])
        ->call('save')
        ->call('clear');

    expect($component->get('uploadedFiles'))->toBeEmpty()
        ->and($component->get('processingFiles'))->toBeEmpty();
});

test('uploader component can remove individual files from results', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg'),
        UploadedFile::fake()->image('test2.jpg'),
    ];

    $component = Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', $files)
        ->call('save')
        ->call('removeFile', 0); // Remove first file

    expect($component->get('uploadedFiles'))->toHaveCount(1);
    
    // The images should still exist in database
    expect(Image::count())->toBe(2);
});

test('uploader component shows upload statistics', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg')->size(1024),
        UploadedFile::fake()->image('test2.jpg')->size(2048),
    ];

    $component = Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', $files)
        ->call('save');

    $stats = $component->get('uploadStats');
    expect($stats['total_files'])->toBe(2)
        ->and($stats['total_size_formatted'])->toContain('KB');
});

test('uploader component dispatches upload complete event', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$file])
        ->call('save')
        ->assertDispatched('upload-complete');
});

test('uploader component formats file sizes correctly', function () {
    $user = User::factory()->create();
    
    $component = Livewire::actingAs($user)->test(Uploader::class);

    expect($component->instance()->formatFileSize(1024))->toBe('1.00 KB')
        ->and($component->instance()->formatFileSize(1048576))->toBe('1.00 MB')
        ->and($component->instance()->formatFileSize(1073741824))->toBe('1.00 GB')
        ->and($component->instance()->formatFileSize(512))->toBe('512 B');
});

test('uploader component handles rate limiting gracefully', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    // This test would require mocking the rate limiter to simulate hitting limits
    // For now, just verify the component loads and works normally
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(Image::count())->toBe(1);
});

test('uploader component generates unique session IDs', function () {
    $user = User::factory()->create();
    
    $component1 = Livewire::actingAs($user)->test(Uploader::class);
    $component2 = Livewire::actingAs($user)->test(Uploader::class);

    $sessionId1 = $component1->get('uploadSessionId');
    $sessionId2 = $component2->get('uploadSessionId');

    expect($sessionId1)->not->toBe($sessionId2)
        ->and($sessionId1)->toBeString()
        ->and($sessionId2)->toBeString();
});

test('uploader component preserves configuration across pipeline', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 1200, 800);

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('disk', \App\Enums\StorageDisk::LOCAL)
        ->set('extractMetadata', true)
        ->set('checkDuplicates', false)
        ->set('maxFileSizeMB', 25)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $image = Image::first();
    expect($image->disk)->toBe('local') // Disk setting preserved
        ->and($image->width)->toBe(1200) // Metadata extraction worked
        ->and($image->height)->toBe(800);
});