<?php

use App\Enums\AllowedImageType;
use App\Enums\StorageDisk;
use App\Models\Media;
use App\Models\User;
use App\Services\UploaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
    Storage::fake('testing');
});

test('uploader service can upload a single image', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1024);

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->upload($file);

    expect($result)->toBeInstanceOf(Media::class)
        ->and($result->user_id)->toBe($user->id)
        ->and($result->original_name)->toBe('test.jpg')
        ->and($result->mime_type)->toBe('image/jpeg')
        ->and($result->size)->toBeGreaterThan(0)
        ->and($result->disk)->toBe(StorageDisk::LOCAL)
        ->and(Storage::disk('local')->exists($result->path))->toBeTrue();
});

test('uploader service can upload multiple files', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg', 800, 600),
        UploadedFile::fake()->image('test2.png', 1200, 800),
        UploadedFile::fake()->image('test3.gif', 400, 300),
    ];

    $uploader = new UploaderService();
    $results = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->uploadMultiple($files);

    expect($results)->toHaveCount(3)
        ->and($results[0])->toBeInstanceOf(Media::class)
        ->and($results[1])->toBeInstanceOf(Media::class)
        ->and($results[2])->toBeInstanceOf(Media::class);

    foreach ($results as $media) {
        expect($media->user_id)->toBe($user->id)
            ->and(Storage::disk('local')->exists($media->path))->toBeTrue();
    }
});

test('uploader service validates file types', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $uploader = new UploaderService();
    
    $this->expectException(\App\Exceptions\InvalidFileTypeException::class);
    
    $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->allowedTypes([AllowedImageType::JPEG, AllowedImageType::PNG])
        ->upload($file);
});

test('uploader service validates file size', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('large.jpg')->size(10240); // 10MB

    $uploader = new UploaderService();
    
    $this->expectException(\App\Exceptions\FileSizeLimitException::class);
    
    $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->maxSize(5120) // 5MB limit
        ->upload($file);
});

test('uploader service can check for duplicates', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    // First upload
    $uploader = new UploaderService();
    $first = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->checkDuplicates()
        ->upload($file);

    // Second upload of same file
    $file2 = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    $this->expectException(\App\Exceptions\DuplicateFileException::class);
    
    $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->checkDuplicates()
        ->upload($file2);
});

test('uploader service can skip duplicate checking', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    // First upload
    $uploader = new UploaderService();
    $first = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->upload($file);

    // Second upload of same file without duplicate checking
    $file2 = UploadedFile::fake()->image('test.jpg', 800, 600);
    
    $second = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->upload($file2);

    expect($first->id)->not->toBe($second->id)
        ->and(Media::count())->toBe(2);
});

test('uploader service can extract metadata from images', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 1200, 800);

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->extractMetadata()
        ->upload($file);

    expect($result->width)->toBe(1200)
        ->and($result->height)->toBe(800);
});

test('uploader service sanitizes filenames', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('Test File With Spaces & Special!@#$.jpg');

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->upload($file);

    expect($result->name)->not->toContain(' ')
        ->and($result->name)->not->toContain('!')
        ->and($result->name)->not->toContain('@')
        ->and($result->name)->not->toContain('#')
        ->and($result->name)->not->toContain('$')
        ->and($result->original_name)->toBe('Test File With Spaces & Special!@#$.jpg');
});

test('uploader service generates unique paths', function () {
    $user = User::factory()->create();
    $file1 = UploadedFile::fake()->image('test.jpg');
    $file2 = UploadedFile::fake()->image('test.jpg');

    $uploader = new UploaderService();
    
    $result1 = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->upload($file1);
        
    $result2 = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->upload($file2);

    expect($result1->path)->not->toBe($result2->path)
        ->and($result1->name)->not->toBe($result2->name);
});

test('uploader service can upload to different disks', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::SPACES)
        ->for($user)
        ->upload($file);

    expect($result->disk)->toBe(StorageDisk::SPACES)
        ->and(Storage::disk('spaces')->exists($result->path))->toBeTrue();
});

test('uploader service can set custom path prefix', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->path('custom/uploads')
        ->upload($file);

    expect($result->path)->toStartWith('custom/uploads/');
});

test('uploader service cleans up on failure', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('invalid.exe', 1024, 'application/exe');

    $uploader = new UploaderService();
    
    try {
        $uploader
            ->disk(StorageDisk::LOCAL)
            ->for($user)
            ->allowedTypes([AllowedImageType::JPEG])
            ->upload($file);
    } catch (\Exception $e) {
        // Expected to fail
    }

    // Check that no orphaned files exist
    $files = Storage::disk('local')->allFiles();
    expect($files)->toBeEmpty();
});

test('uploader service tracks progress for multiple files', function () {
    $user = User::factory()->create();
    $files = [
        UploadedFile::fake()->image('test1.jpg'),
        UploadedFile::fake()->image('test2.jpg'),
        UploadedFile::fake()->image('test3.jpg'),
    ];

    $progressUpdates = [];
    
    $uploader = new UploaderService();
    $results = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->onProgress(function ($current, $total, $filename) use (&$progressUpdates) {
            $progressUpdates[] = [$current, $total, $filename];
        })
        ->uploadMultiple($files);

    expect($progressUpdates)->toHaveCount(3)
        ->and($progressUpdates[0])->toBe([1, 3, 'test1.jpg'])
        ->and($progressUpdates[1])->toBe([2, 3, 'test2.jpg'])
        ->and($progressUpdates[2])->toBe([3, 3, 'test3.jpg']);
});

test('uploader service can set media as shareable', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->shareable()
        ->upload($file);

    expect($result->is_shareable)->toBeTrue();
});

test('uploader service can set media as private', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    $uploader = new UploaderService();
    $result = $uploader
        ->disk(StorageDisk::LOCAL)
        ->for($user)
        ->private()
        ->upload($file);

    expect($result->is_shareable)->toBeFalse();
});