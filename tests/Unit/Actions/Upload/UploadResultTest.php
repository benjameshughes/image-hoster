<?php

use App\Actions\Upload\UploadContext;
use App\Actions\Upload\UploadResult;
use App\Enums\StorageDisk;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

test('upload result can be created as success', function () {
    $user = User::factory()->create();
    $image = Image::factory()->for($user)->create();
    $metadata = collect(['width' => 800, 'height' => 600]);
    
    $result = UploadResult::success(
        message: 'Upload successful',
        record: $image,
        path: 'uploads/test.jpg',
        url: 'https://example.com/test.jpg',
        filename: 'test.jpg',
        size: 1024,
        mimeType: 'image/jpeg',
        metadata: $metadata
    );
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('Upload successful')
        ->and($result->record)->toBe($image)
        ->and($result->path)->toBe('uploads/test.jpg')
        ->and($result->url)->toBe('https://example.com/test.jpg')
        ->and($result->filename)->toBe('test.jpg')
        ->and($result->size)->toBe(1024)
        ->and($result->mimeType)->toBe('image/jpeg')
        ->and($result->metadata)->toBe($metadata)
        ->and($result->errors)->toBeEmpty()
        ->and($result->context)->toBeNull();
});

test('upload result can be created as failure', function () {
    $errors = ['File too large', 'Invalid type'];
    
    $result = UploadResult::failure(
        message: 'Upload failed',
        errors: $errors
    );
    
    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('Upload failed')
        ->and($result->record)->toBeNull()
        ->and($result->path)->toBeNull()
        ->and($result->url)->toBeNull()
        ->and($result->filename)->toBeNull()
        ->and($result->size)->toBeNull()
        ->and($result->mimeType)->toBeNull()
        ->and($result->metadata)->toBeInstanceOf(Collection::class)
        ->and($result->metadata)->toBeEmpty()
        ->and($result->errors)->toBe($errors)
        ->and($result->context)->toBeNull();
});

test('upload result can be created for continuation', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    $metadata = collect(['stage' => 'processing']);
    
    $result = UploadResult::continue(
        context: $context,
        message: 'Continue processing',
        metadata: $metadata
    );
    
    expect($result->success)->toBeTrue()
        ->and($result->message)->toBe('Continue processing')
        ->and($result->record)->toBeNull()
        ->and($result->path)->toBeNull()
        ->and($result->url)->toBeNull()
        ->and($result->filename)->toBeNull()
        ->and($result->size)->toBeNull()
        ->and($result->mimeType)->toBeNull()
        ->and($result->metadata)->toBe($metadata)
        ->and($result->errors)->toBeEmpty()
        ->and($result->context)->toBe($context);
});

test('upload result can determine if processing should continue', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test'
    );
    
    // Success with context should continue
    $continueResult = UploadResult::continue($context);
    expect($continueResult->shouldContinue())->toBeTrue();
    
    // Success without context should not continue
    $finalResult = UploadResult::success(message: 'Final result');
    expect($finalResult->shouldContinue())->toBeFalse();
    
    // Failure should not continue
    $failureResult = UploadResult::failure('Failed');
    expect($failureResult->shouldContinue())->toBeFalse();
});

test('upload result can get updated context with merged metadata', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    $originalMetadata = collect(['original' => 'value']);
    $context = new UploadContext(
        file: $file,
        user: $user,
        disk: StorageDisk::SPACES,
        directory: 'uploads/test',
        metadata: $originalMetadata
    );
    
    $additionalMetadata = collect(['width' => 800, 'height' => 600]);
    $result = UploadResult::continue(
        context: $context,
        metadata: $additionalMetadata
    );
    
    $updatedContext = $result->getUpdatedContext();
    
    expect($updatedContext)->not->toBeNull()
        ->and($updatedContext->metadata->get('original'))->toBe('value')
        ->and($updatedContext->metadata->get('width'))->toBe(800)
        ->and($updatedContext->metadata->get('height'))->toBe(600);
});

test('upload result returns null updated context when no context present', function () {
    $result = UploadResult::success(message: 'Success without context');
    
    expect($result->getUpdatedContext())->toBeNull();
});

test('upload result can be converted to array', function () {
    $user = User::factory()->create();
    $image = Image::factory()->for($user)->create();
    $metadata = collect(['width' => 800, 'height' => 600]);
    $errors = ['Test error'];
    
    $result = UploadResult::success(
        message: 'Upload successful',
        record: $image,
        path: 'uploads/test.jpg',
        url: 'https://example.com/test.jpg',
        filename: 'test.jpg',
        size: 1024,
        mimeType: 'image/jpeg',
        metadata: $metadata
    );
    
    $array = $result->toArray();
    
    expect($array)->toBeArray()
        ->and($array['success'])->toBeTrue()
        ->and($array['message'])->toBe('Upload successful')
        ->and($array['record'])->toBeArray()
        ->and($array['path'])->toBe('uploads/test.jpg')
        ->and($array['url'])->toBe('https://example.com/test.jpg')
        ->and($array['filename'])->toBe('test.jpg')
        ->and($array['size'])->toBe(1024)
        ->and($array['mime_type'])->toBe('image/jpeg')
        ->and($array['metadata'])->toBe(['width' => 800, 'height' => 600])
        ->and($array['errors'])->toBeEmpty();
});

test('upload result array conversion handles null values', function () {
    $result = UploadResult::failure('Test failure', ['Error message']);
    
    $array = $result->toArray();
    
    expect($array)->toBeArray()
        ->and($array['success'])->toBeFalse()
        ->and($array['message'])->toBe('Test failure')
        ->and($array['record'])->toBeNull()
        ->and($array['path'])->toBeNull()
        ->and($array['url'])->toBeNull()
        ->and($array['filename'])->toBeNull()
        ->and($array['size'])->toBeNull()
        ->and($array['mime_type'])->toBeNull()
        ->and($array['metadata'])->toBeEmpty()
        ->and($array['errors'])->toBe(['Error message']);
});