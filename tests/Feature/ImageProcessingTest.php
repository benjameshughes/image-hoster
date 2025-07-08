<?php

use App\Models\Media;
use App\Models\User;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
});

test('image processing service can extract metadata from images', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $metadata = $service->extractMetadata($media);

    expect($metadata)->toHaveKeys(['width', 'height', 'mime_type'])
        ->and($metadata['width'])->toBe(800)
        ->and($metadata['height'])->toBe(600)
        ->and($metadata['mime_type'])->toBe('image/jpeg');
});

test('image processing service can generate thumbnails', function () {
    $file = UploadedFile::fake()->image('test.jpg', 1200, 800);
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 1200,
        'height' => 800,
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $thumbnailPath = $service->generateThumbnail($media);

    expect($thumbnailPath)->toBeString()
        ->and(Storage::disk($media->disk->value)->exists($thumbnailPath))->toBeTrue();

    // Update media with thumbnail path for cleanup test
    $media->update(['thumbnail_path' => $thumbnailPath]);
    
    // Verify thumbnail dimensions are smaller
    $thumbnailContent = Storage::disk($media->disk->value)->get($thumbnailPath);
    $tempPath = tempnam(sys_get_temp_dir(), 'thumb_test');
    file_put_contents($tempPath, $thumbnailContent);
    
    $imageSize = getimagesize($tempPath);
    expect($imageSize[0])->toBeLessThanOrEqual(300) // width
        ->and($imageSize[1])->toBeLessThanOrEqual(300); // height
    
    unlink($tempPath);
});

test('image processing service can compress images', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(2048); // 2MB
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
        'size' => 2097152, // 2MB in bytes
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $result = $service->compressImage($media, 75);

    expect($result)->toHaveKeys(['compressed_path', 'original_size', 'compressed_size', 'compression_ratio'])
        ->and($result['compressed_size'])->toBeLessThan($result['original_size'])
        ->and($result['compression_ratio'])->toBeGreaterThan(0)
        ->and(Storage::disk($media->disk->value)->exists($result['compressed_path']))->toBeTrue();
});

test('image processing service can create compression levels', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $levels = $service->createCompressionLevels($media);

    expect($levels)->toBeArray()
        ->and($levels)->toHaveCount(5) // Different quality levels
        ->and($levels[0])->toHaveKeys(['quality', 'path', 'size', 'size_formatted', 'compression_ratio', 'url']);

    // Verify files were created
    foreach ($levels as $level) {
        expect(Storage::disk($media->disk->value)->exists($level['path']))->toBeTrue();
    }
});

test('image processing service can apply compression with specific quality', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
        'size' => 1048576, // 1MB
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $result = $service->applyCompression($media, 80);

    expect($result)->toHaveKeys(['compression_ratio', 'original_size', 'new_size'])
        ->and($result['compression_ratio'])->toBeGreaterThan(0);

    // Verify media was updated
    $media->refresh();
    expect($media->compressed_size)->toBeLessThan($media->size);
});

test('image processing service can reprocess images', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
        'width' => null, // No metadata initially
        'height' => null,
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $result = $service->reprocessImage($media);

    expect($result)->toHaveKeys(['metadata', 'thumbnail'])
        ->and($result['metadata'])->toHaveKeys(['width', 'height'])
        ->and($result['metadata']['width'])->toBe(800)
        ->and($result['metadata']['height'])->toBe(600);

    // Verify media was updated
    $media->refresh();
    expect($media->width)->toBe(800)
        ->and($media->height)->toBe(600);
});

test('image processing service handles non-image files gracefully', function () {
    $user = User::factory()->create();
    $media = Media::factory()->document()->create([
        'user_id' => $user->id,
        'path' => 'uploads/document.pdf',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'application/pdf',
    ]);

    // Store a fake PDF file
    Storage::disk($media->disk->value)->put($media->path, 'fake pdf content');

    $service = new ImageProcessingService();
    
    // Should handle gracefully without throwing exceptions
    $metadata = $service->extractMetadata($media);
    expect($metadata)->toBeArray();

    // Should not be able to generate thumbnail for non-images
    $this->expectException(\Exception::class);
    $service->generateThumbnail($media);
});

test('image processing service provides compression presets', function () {
    $service = new ImageProcessingService();
    $presets = $service->getCompressionPresets();

    expect($presets)->toBeArray()
        ->and($presets)->toHaveCount(5)
        ->and($presets[0])->toHaveKeys(['quality', 'label', 'description']);
});

test('image processing service can optimize image orientation', function () {
    $file = UploadedFile::fake()->image('test.jpg', 600, 800); // Portrait
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $result = $service->optimizeOrientation($media);

    expect($result)->toHaveKeys(['rotated', 'original_dimensions', 'new_dimensions']);
});

test('image processing service cleans up temporary files', function () {
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    $levels = $service->createCompressionLevels($media);

    // Verify files exist
    foreach ($levels as $level) {
        expect(Storage::disk($media->disk->value)->exists($level['path']))->toBeTrue();
    }

    // Clean up temporary files
    $service->cleanupTemporaryFiles($levels, $media->disk->value);

    // Verify files are removed
    foreach ($levels as $level) {
        expect(Storage::disk($media->disk->value)->exists($level['path']))->toBeFalse();
    }
});

test('image processing service validates image format support', function () {
    $service = new ImageProcessingService();
    
    expect($service->isImageFormatSupported('image/jpeg'))->toBeTrue()
        ->and($service->isImageFormatSupported('image/png'))->toBeTrue()
        ->and($service->isImageFormatSupported('image/gif'))->toBeTrue()
        ->and($service->isImageFormatSupported('image/webp'))->toBeTrue()
        ->and($service->isImageFormatSupported('application/pdf'))->toBeFalse()
        ->and($service->isImageFormatSupported('video/mp4'))->toBeFalse();
});

test('image processing service handles large images efficiently', function () {
    $file = UploadedFile::fake()->image('large.jpg', 4000, 3000)->size(10240); // Large image
    $user = User::factory()->create();
    
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/large.jpg',
        'disk' => \App\Enums\StorageDisk::LOCAL,
        'mime_type' => 'image/jpeg',
        'size' => 10485760, // 10MB
    ]);

    // Store the file
    Storage::disk($media->disk->value)->put($media->path, $file->getContent());

    $service = new ImageProcessingService();
    
    // Should handle large images without memory issues
    $metadata = $service->extractMetadata($media);
    expect($metadata['width'])->toBe(4000)
        ->and($metadata['height'])->toBe(3000);

    // Compression should work on large images
    $result = $service->compressImage($media, 70);
    expect($result['compressed_size'])->toBeLessThan($result['original_size']);
});