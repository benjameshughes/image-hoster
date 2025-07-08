<?php

use App\Enums\MediaType;
use App\Enums\StorageDisk;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('testing');
});

test('media can be created with required fields', function () {
    $user = User::factory()->create();
    
    $media = Media::create([
        'user_id' => $user->id,
        'name' => 'test-image.jpg',
        'original_name' => 'test image.jpg',
        'path' => 'uploads/test-image.jpg',
        'directory' => 'uploads',
        'disk' => StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'media_type' => MediaType::IMAGE,
        'is_public' => false,
        'is_shareable' => true,
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->user_id)->toBe($user->id)
        ->and($media->name)->toBe('test-image.jpg')
        ->and($media->original_name)->toBe('test image.jpg')
        ->and($media->path)->toBe('uploads/test-image.jpg')
        ->and($media->disk)->toBe(StorageDisk::LOCAL)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBe(1024)
        ->and($media->media_type)->toBe(MediaType::IMAGE);
});

test('media belongs to user', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    expect($media->user)->toBeInstanceOf(User::class)
        ->and($media->user->id)->toBe($user->id);
});

test('media has dimensions when width and height are set', function () {
    $media = Media::factory()->create([
        'width' => 800,
        'height' => 600,
    ]);

    expect($media->hasDimensions())->toBeTrue()
        ->and($media->width)->toBe(800)
        ->and($media->height)->toBe(600);
});

test('media has no dimensions when width or height is null', function () {
    $media = Media::factory()->create([
        'width' => null,
        'height' => null,
    ]);

    expect($media->hasDimensions())->toBeFalse();
});

test('media formats file size correctly', function () {
    $media = Media::factory()->create(['size' => 1024]);
    expect($media->formattedSize)->toBe('1 KB');

    $media = Media::factory()->create(['size' => 1048576]);
    expect($media->formattedSize)->toBe('1 MB');

    $media = Media::factory()->create(['size' => 1073741824]);
    expect($media->formattedSize)->toBe('1 GB');

    $media = Media::factory()->create(['size' => 512]);
    expect($media->formattedSize)->toBe('512 B');
});

test('media generates correct url', function () {
    Storage::fake('spaces');
    
    $media = Media::factory()->create([
        'path' => 'uploads/test.jpg',
        'disk' => StorageDisk::SPACES,
    ]);

    Storage::disk('spaces')->put('uploads/test.jpg', 'fake content');

    expect($media->url)->toContain('uploads/test.jpg');
});

test('media generates correct download url', function () {
    $media = Media::factory()->create();

    expect($media->downloadUrl)->toBe(route('media.download', $media));
});

test('media generates shareable url when shareable', function () {
    $media = Media::factory()->create(['is_shareable' => true]);

    expect($media->isShareable)->toBeTrue()
        ->and($media->shareableUrl)->toContain('/m/');
});

test('media is not shareable when is_shareable is false', function () {
    $media = Media::factory()->create(['is_shareable' => false]);

    expect($media->isShareable)->toBeFalse();
});

test('media can check if file exists on storage', function () {
    Storage::fake('testing');
    
    $media = Media::factory()->create([
        'path' => 'uploads/test.jpg',
        'disk' => StorageDisk::LOCAL,
    ]);

    // File doesn't exist initially
    expect($media->exists())->toBeFalse();

    // Create the file
    Storage::disk('local')->put('uploads/test.jpg', 'fake content');
    
    // Now it should exist
    expect($media->exists())->toBeTrue();
});

test('media deletes file when model is deleted', function () {
    Storage::fake('testing');
    
    $media = Media::factory()->create([
        'path' => 'uploads/test.jpg',
        'disk' => StorageDisk::LOCAL,
    ]);

    // Create the file
    Storage::disk('local')->put('uploads/test.jpg', 'fake content');
    expect(Storage::disk('local')->exists('uploads/test.jpg'))->toBeTrue();

    // Delete the media model
    $media->delete();

    // File should be deleted
    expect(Storage::disk('local')->exists('uploads/test.jpg'))->toBeFalse();
});

test('media can have tags', function () {
    $media = Media::factory()->create([
        'tags' => ['nature', 'landscape', 'photography'],
    ]);

    expect($media->tags)->toBe(['nature', 'landscape', 'photography'])
        ->and($media->tags)->toHaveCount(3);
});

test('media can have alt text and description', function () {
    $media = Media::factory()->create([
        'alt_text' => 'Beautiful landscape photo',
        'description' => 'A stunning view of mountains at sunset',
    ]);

    expect($media->alt_text)->toBe('Beautiful landscape photo')
        ->and($media->description)->toBe('A stunning view of mountains at sunset');
});

test('media tracks creation source', function () {
    $media = Media::factory()->create(['source' => 'wordpress']);

    expect($media->source)->toBe('wordpress');
});

test('media can have unique identifier for sharing', function () {
    $media = Media::factory()->create();

    expect($media->unique_id)->not->toBeNull()
        ->and($media->unique_id)->toHaveLength(32);
});