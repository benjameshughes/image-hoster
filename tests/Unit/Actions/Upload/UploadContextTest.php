<?php

use App\Actions\Upload\UploadContext;
use App\Enums\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

test('upload context can be created with required parameters', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    expect($context->file)->toBe($file)
        ->and($context->user)->toBe($user)
        ->and($context->disk)->toBe(StorageDisk::SPACES)
        ->and($context->directory)->toBe('uploads/test')
        ->and($context->isPublic)->toBeTrue()
        ->and($context->randomizeFilename)->toBeTrue()
        ->and($context->extractMetadata)->toBeTrue()
        ->and($context->checkDuplicates)->toBeFalse()
        ->and($context->maxSizeMB)->toBe(50)
        ->and($context->allowedMimeTypes)->toBeEmpty()
        ->and($context->metadata)->toBeInstanceOf(Collection::class)
        ->and($context->configuration)->toBeEmpty()
        ->and($context->processingState)->toBeEmpty();
});

test('upload context can be created with custom parameters', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    $metadata = collect(['test' => 'value']);
    $configuration = ['custom' => 'config'];
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::LOCAL,
        directory: 'custom/path',
        isPublic: false,
        randomizeFilename: false,
        extractMetadata: false,
        checkDuplicates: true,
        maxSizeMB: 25,
        allowedMimeTypes: ['image/jpeg'],
        metadata: $metadata,
        configuration: $configuration,
        sessionId: 'test-session',
        processingState: ['stage' => 'initial']
    );
    
    expect($context->isPublic)->toBeFalse()
        ->and($context->randomizeFilename)->toBeFalse()
        ->and($context->extractMetadata)->toBeFalse()
        ->and($context->checkDuplicates)->toBeTrue()
        ->and($context->maxSizeMB)->toBe(25)
        ->and($context->allowedMimeTypes)->toBe(['image/jpeg'])
        ->and($context->metadata)->toBe($metadata)
        ->and($context->configuration)->toBe($configuration)
        ->and($context->sessionId)->toBe('test-session')
        ->and($context->processingState)->toBe(['stage' => 'initial']);
});

test('upload context can add metadata', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    $updatedContext = $context->withMetadata('width', 800);
    
    expect($context->metadata->get('width'))->toBeNull()
        ->and($updatedContext->metadata->get('width'))->toBe(800)
        ->and($updatedContext)->not->toBe($context); // Immutable
});

test('upload context can update configuration', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        configuration: ['existing' => 'value']
    );
    
    $updatedContext = $context->withConfiguration(['new' => 'config']);
    
    expect($context->configuration)->toBe(['existing' => 'value'])
        ->and($updatedContext->configuration)->toBe(['existing' => 'value', 'new' => 'config'])
        ->and($updatedContext)->not->toBe($context); // Immutable
});

test('upload context can update processing state', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    $updatedContext = $context->withProcessingState('stage', 'validation');
    
    expect($context->processingState)->toBeEmpty()
        ->and($updatedContext->processingState)->toBe(['stage' => 'validation'])
        ->and($updatedContext)->not->toBe($context); // Immutable
});

test('upload context provides file information', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1024);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    expect($context->getOriginalFilename())->toBe('test.jpg')
        ->and($context->getFileSize())->toBe(1024)
        ->and($context->getMimeType())->toBe('image/jpeg')
        ->and($context->getExtension())->toBe('jpg')
        ->and($context->isImage())->toBeTrue();
});

test('upload context can detect non-image files', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    expect($context->getMimeType())->toBe('application/pdf')
        ->and($context->isImage())->toBeFalse();
});

test('upload context provides metadata access', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    $metadata = collect(['width' => 800, 'height' => 600]);
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        metadata: $metadata
    );
    
    expect($context->getMetadata('width'))->toBe(800)
        ->and($context->getMetadata('height'))->toBe(600)
        ->and($context->getMetadata('missing', 'default'))->toBe('default');
});

test('upload context provides configuration access', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        configuration: ['custom_option' => true, 'threshold' => 50]
    );
    
    expect($context->getConfiguration('custom_option'))->toBeTrue()
        ->and($context->getConfiguration('threshold'))->toBe(50)
        ->and($context->getConfiguration('missing', false))->toBeFalse();
});

test('upload context provides processing state access', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        processingState: ['stage' => 'validation', 'progress' => 50]
    );
    
    expect($context->getProcessingState('stage'))->toBe('validation')
        ->and($context->getProcessingState('progress'))->toBe(50)
        ->and($context->getProcessingState('missing', 'none'))->toBe('none');
});