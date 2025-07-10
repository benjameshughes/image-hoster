<?php

use App\Actions\Upload\Core\ProcessFileAction;
use App\Actions\Upload\UploadContext;
use App\Enums\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new ProcessFileAction();
    Storage::fake('local');
    Storage::fake('spaces');
    Storage::fake('testing');
});

test('process file action has correct metadata', function () {
    expect($this->action->getName())->toBe('process_file')
        ->and($this->action->getDescription())->toBe('Processes and stores the uploaded file')
        ->and($this->action->getPriority())->toBe(50);
});

test('process file action can store file successfully', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1024);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test'
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('File processed successfully')
        ->and($result->shouldContinue())->toBeTrue()
        ->and($result->metadata->get('stored_path'))->not->toBeNull()
        ->and($result->metadata->get('filename'))->not->toBeNull()
        ->and($result->metadata->get('url'))->not->toBeNull()
        ->and($result->metadata->get('disk'))->toBe('local')
        ->and($result->metadata->get('processed_at'))->not->toBeNull();
    
    // Verify file was actually stored
    $storedPath = $result->metadata->get('stored_path');
    expect(Storage::disk('local')->exists($storedPath))->toBeTrue();
});

test('process file action generates random filename by default', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test'
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $filename = $result->metadata->get('filename');
    expect($filename)->not->toBe('test.jpg') // Should be randomized
        ->and($filename)->toEndWith('.jpg'); // Should preserve extension
});

test('process file action can preserve original filename', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('my-photo.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        configuration: ['randomize_filename' => false]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $filename = $result->metadata->get('filename');
    expect($filename)->toBe('my-photo.jpg');
});

test('process file action sanitizes non-random filenames', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('My File! With @#$ Special.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        configuration: [
            'randomize_filename' => false,
            'sanitize_filename' => true
        ]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $filename = $result->metadata->get('filename');
    expect($filename)->not->toContain(' ')
        ->and($filename)->not->toContain('!')
        ->and($filename)->not->toContain('@')
        ->and($filename)->not->toContain('#')
        ->and($filename)->not->toContain('$')
        ->and($filename)->toEndWith('.jpg');
});

test('process file action can skip filename sanitization', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test-file.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        configuration: [
            'randomize_filename' => false,
            'sanitize_filename' => false
        ]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $filename = $result->metadata->get('filename');
    expect($filename)->toBe('test-file.jpg');
});

test('process file action can skip extension preservation', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        configuration: ['preserve_extension' => false]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $filename = $result->metadata->get('filename');
    expect($filename)->not->toEndWith('.jpg'); // No extension
});

test('process file action works with different storage disks', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->metadata->get('disk'))->toBe('spaces');
    
    // Verify file was stored on correct disk
    $storedPath = $result->metadata->get('stored_path');
    expect(Storage::disk('spaces')->exists($storedPath))->toBeTrue();
});

test('process file action handles storage failure gracefully', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    // Use a non-existent disk to simulate failure
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::from('non_existent_disk'),
        directory: 'uploads/test'
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('File processing failed');
});

test('process file action preserves directory structure', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/user123/photos/2024'
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $storedPath = $result->metadata->get('stored_path');
    expect($storedPath)->toStartWith('uploads/user123/photos/2024/');
});

test('process file action generates public URL for public files', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        isPublic: true
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
    
    $url = $result->metadata->get('url');
    expect($url)->not->toBeNull()
        ->and($url)->toBeString();
});

test('process file action can handle context', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test'
    );
    
    expect($this->action->canHandle($context))->toBeTrue();
});

test('process file action respects enabled configuration', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $disabledContext = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        configuration: ['process_file' => false]
    );
    
    expect($this->action->canHandle($disabledContext))->toBeFalse();
});

test('process file action provides configuration options', function () {
    $options = $this->action->getConfigurationOptions();
    
    expect($options)->toBeArray()
        ->and($options)->toHaveCount(3)
        ->and($options['randomize_filename'])->toBeArray()
        ->and($options['randomize_filename']['type'])->toBe('boolean')
        ->and($options['randomize_filename']['default'])->toBeTrue()
        ->and($options['preserve_extension'])->toBeArray()
        ->and($options['sanitize_filename'])->toBeArray();
});

test('process file action handles concurrent filename conflicts', function () {
    $user = User::factory()->create();
    $file1 = UploadedFile::fake()->image('test.jpg');
    $file2 = UploadedFile::fake()->image('test.jpg');
    
    $context1 = new UploadContext(
        file: $file1,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test'
    );
    
    $context2 = new UploadContext(
        file: $file2,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test'
    );
    
    $result1 = $this->action->execute($context1);
    $result2 = $this->action->execute($context2);
    
    expect($result1->success)->toBeTrue()
        ->and($result2->success)->toBeTrue();
    
    // Files should have different paths/names due to randomization
    $path1 = $result1->metadata->get('stored_path');
    $path2 = $result2->metadata->get('stored_path');
    
    expect($path1)->not->toBe($path2);
});