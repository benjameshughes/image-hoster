<?php

use App\Actions\Upload\Core\ValidateFileAction;
use App\Actions\Upload\UploadContext;
use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new ValidateFileAction();
});

test('validate file action has correct metadata', function () {
    expect($this->action->getName())->toBe('validate_file')
        ->and($this->action->getDescription())->toBe('Validates uploaded file size, type, and extension')
        ->and($this->action->getPriority())->toBe(10);
});

test('validate file action passes for valid image', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1024); // 1MB
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        maxSizeMB: 5,
        allowedMimeTypes: ['image/jpeg', 'image/png']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('File validation passed')
        ->and($result->shouldContinue())->toBeTrue()
        ->and($result->metadata->get('validated_at'))->not->toBeNull()
        ->and($result->metadata->get('file_size'))->toBe(1024)
        ->and($result->metadata->get('mime_type'))->toBe('image/jpeg')
        ->and($result->metadata->get('extension'))->toBe('jpg');
});

test('validate file action fails for oversized file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('large.jpg')->size(10240); // 10MB
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        maxSizeMB: 5 // 5MB limit
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('File validation failed')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0])->toContain('exceeds maximum allowed size');
});

test('validate file action fails for invalid mime type', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        allowedMimeTypes: ['image/jpeg', 'image/png']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('File validation failed')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0])->toContain('is not allowed');
});

test('validate file action fails for invalid extension', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('malicious.exe', 1024, 'application/octet-stream');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('File validation failed')
        ->and($result->errors)->toHaveCount(2) // MIME type and extension
        ->and(collect($result->errors)->some(fn($error) => str_contains($error, 'extension')))
        ->toBeTrue();
});

test('validate file action can skip size validation', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('large.jpg')->size(10240); // 10MB
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        maxSizeMB: 5,
        configuration: ['validate_size' => false]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
});

test('validate file action can skip mime type validation', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        allowedMimeTypes: ['image/jpeg'],
        configuration: ['validate_mime_type' => false]
    );
    
    $result = $this->action->execute($context);
    
    // Should still fail on extension unless that's also disabled
    expect($result->success)->toBeFalse()
        ->and(collect($result->errors)->every(fn($error) => !str_contains($error, 'type')))
        ->toBeTrue();
});

test('validate file action can skip extension validation', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.xyz', 1024, 'image/jpeg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        configuration: ['validate_extension' => false]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
});

test('validate file action can disable all validations', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('malicious.exe', 10240, 'application/octet-stream');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        maxSizeMB: 5,
        allowedMimeTypes: ['image/jpeg'],
        configuration: [
            'validate_size' => false,
            'validate_mime_type' => false,
            'validate_extension' => false,
        ]
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
});

test('validate file action accumulates multiple validation errors', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('large-file.exe', 10240, 'application/octet-stream');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        maxSizeMB: 5,
        allowedMimeTypes: ['image/jpeg', 'image/png']
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeFalse()
        ->and($result->errors)->toHaveCount(3) // Size, MIME type, and extension
        ->and(collect($result->errors)->some(fn($error) => str_contains($error, 'size')))
        ->toBeTrue()
        ->and(collect($result->errors)->some(fn($error) => str_contains($error, 'type')))
        ->toBeTrue()
        ->and(collect($result->errors)->some(fn($error) => str_contains($error, 'extension')))
        ->toBeTrue();
});

test('validate file action handles empty allowed mime types', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        allowedMimeTypes: [] // Empty means allow all
    );
    
    $result = $this->action->execute($context);
    
    expect($result->success)->toBeTrue();
});

test('validate file action can handle context', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    expect($this->action->canHandle($context))->toBeTrue();
});

test('validate file action respects enabled configuration', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    
    $disabledContext = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        configuration: ['validate_file' => false]
    );
    
    expect($this->action->canHandle($disabledContext))->toBeFalse();
});

test('validate file action provides configuration options', function () {
    $options = $this->action->getConfigurationOptions();
    
    expect($options)->toBeArray()
        ->and($options)->toHaveCount(3)
        ->and($options['validate_size'])->toBeArray()
        ->and($options['validate_size']['type'])->toBe('boolean')
        ->and($options['validate_size']['default'])->toBeTrue()
        ->and($options['validate_mime_type'])->toBeArray()
        ->and($options['validate_extension'])->toBeArray();
});