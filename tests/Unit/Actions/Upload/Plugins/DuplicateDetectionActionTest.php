<?php

use App\Actions\Upload\Plugins\DuplicateDetectionAction;
use App\Actions\Upload\UploadContext;
use App\Enums\StorageDisk;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new DuplicateDetectionAction();
    Storage::fake('local');
});

test('duplicate detection action has correct metadata', function () {
    expect($this->action->getName())->toBe('duplicate_detection')
        ->and($this->action->getDescription())->toBe('Detects duplicate files based on content hash to prevent storage waste')
        ->and($this->action->getPriority())->toBe(25);
});

test('duplicate detection action can handle context when duplicates enabled', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true
    );
    
    expect($this->action->canHandle($context))->toBeTrue();
});

test('duplicate detection action cannot handle context when duplicates disabled', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: false
    );
    
    expect($this->action->canHandle($context))->toBeFalse();
});

test('duplicate detection action passes when no duplicate exists', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('No duplicate found')
        ->and($result->shouldContinue())->toBeTrue()
        ->and($result->metadata->get('file_hash'))->not->toBeNull()
        ->and($result->metadata->get('hash_algorithm'))->toBe('sha256')
        ->and($result->metadata->get('duplicate_check_passed'))->toBeTrue();
});

test('duplicate detection action rejects duplicate by default', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    // Create existing image with same content hash
    $fileHash = hash_file('sha256', $file->getPathname());
    $existingImage = Image::factory()->for($user)->create([
        'file_hash' => $fileHash,
        'original_filename' => 'existing.jpg',
        'filename' => 'existing_12345.jpg',
        'url' => 'https://example.com/existing_12345.jpg',
    ]);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('File already exists (duplicate detected)')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors['duplicate_detected'])->toBeTrue()
        ->and($result->errors['existing_file'])->toBeArray()
        ->and($result->errors['existing_file']['id'])->toBe($existingImage->id)
        ->and($result->errors['existing_file']['url'])->toBe($existingImage->url);
});

test('duplicate detection action can skip duplicate and return existing file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    // Create existing image with same content hash
    $fileHash = hash_file('sha256', $file->getPathname());
    $existingImage = Image::factory()->for($user)->create([
        'file_hash' => $fileHash,
        'original_filename' => 'existing.jpg',
        'filename' => 'existing_12345.jpg',
        'url' => 'https://example.com/existing_12345.jpg',
        'path' => 'uploads/existing_12345.jpg',
        'size' => 1024,
        'mime_type' => 'image/jpeg',
    ]);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['action_on_duplicate' => 'skip']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('Duplicate file skipped, returning existing file')
        ->and($result->record)->toBe($existingImage)
        ->and($result->path)->toBe($existingImage->path)
        ->and($result->url)->toBe($existingImage->url)
        ->and($result->filename)->toBe($existingImage->filename)
        ->and($result->size)->toBe($existingImage->size)
        ->and($result->mimeType)->toBe($existingImage->mime_type)
        ->and($result->metadata->get('duplicate_skipped'))->toBeTrue()
        ->and($result->metadata->get('existing_file_id'))->toBe($existingImage->id);
});

test('duplicate detection action can rename duplicate file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    // Create existing image with same content hash
    $fileHash = hash_file('sha256', $file->getPathname());
    $existingImage = Image::factory()->for($user)->create([
        'file_hash' => $fileHash,
    ]);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['action_on_duplicate' => 'rename']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('Duplicate detected, proceeding with renamed file')
        ->and($result->shouldContinue())->toBeTrue()
        ->and($result->metadata->get('duplicate_renamed'))->toBeTrue()
        ->and($result->metadata->get('original_duplicate_id'))->toBe($existingImage->id)
        ->and($result->metadata->get('new_filename'))->toContain('duplicate_');
    
    // Check that context was updated with renamed filename
    $updatedContext = $result->getUpdatedContext();
    expect($updatedContext->getConfiguration('force_filename'))->toContain('duplicate_');
});

test('duplicate detection action uses different hash algorithms', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['hash_algorithm' => 'md5']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->metadata->get('hash_algorithm'))->toBe('md5')
        ->and($result->metadata->get('file_hash'))->toHaveLength(32); // MD5 hash length
});

test('duplicate detection action respects user scope', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    // Create image for user1 with same content hash
    $fileHash = hash_file('sha256', $file->getPathname());
    Image::factory()->for($user1)->create([
        'file_hash' => $fileHash,
    ]);
    
    // Test with user2 - should not find duplicate due to user scope
    $context = new UploadContext(
        file: $file,
        user: $user2,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['scope' => 'user']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('No duplicate found');
});

test('duplicate detection action respects global scope', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    // Create image for user1 with same content hash
    $fileHash = hash_file('sha256', $file->getPathname());
    Image::factory()->for($user1)->create([
        'file_hash' => $fileHash,
    ]);
    
    // Test with user2 - should find duplicate due to global scope
    $context = new UploadContext(
        file: $file,
        user: $user2,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['scope' => 'global']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('File already exists (duplicate detected)');
});

test('duplicate detection action handles hash calculation failure', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['hash_algorithm' => 'invalid_algorithm']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Failed to calculate file hash');
});

test('duplicate detection action provides configuration options', function () {
    $options = $this->action->getConfigurationOptions();
    
    expect($options)->toBeArray()
        ->and($options)->toHaveCount(3)
        ->and($options['hash_algorithm'])->toBeArray()
        ->and($options['hash_algorithm']['type'])->toBe('select')
        ->and($options['hash_algorithm']['options'])->toContain('sha256', 'md5', 'sha1')
        ->and($options['hash_algorithm']['default'])->toBe('sha256')
        ->and($options['scope'])->toBeArray()
        ->and($options['scope']['options'])->toContain('user', 'global')
        ->and($options['action_on_duplicate'])->toBeArray()
        ->and($options['action_on_duplicate']['options'])->toContain('reject', 'skip', 'rename');
});

test('duplicate detection action generates unique renamed filename', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('my-photo.jpg');
    
    // Create existing image to trigger renaming
    $fileHash = hash_file('sha256', $file->getPathname());
    Image::factory()->for($user)->create(['file_hash' => $fileHash]);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'uploads/test',
        checkDuplicates: true,
        configuration: ['action_on_duplicate' => 'rename']
    );
    
    $result = $this->action->execute($context);
    
    $newFilename = $result->metadata->get('new_filename');
    expect($newFilename)->toStartWith('my-photo_duplicate_')
        ->and($newFilename)->toEndWith('.jpg')
        ->and($newFilename)->toMatch('/my-photo_duplicate_\d{14}\.jpg/'); // timestamp format
});