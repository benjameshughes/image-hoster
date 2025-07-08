<?php

use App\Livewire\Actions\Uploader;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
});

test('uploader component can be rendered', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->assertStatus(200);
});

test('uploader component can upload single file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(Media::count())->toBe(1);
    
    $media = Media::first();
    expect($media->user_id)->toBe($user->id)
        ->and($media->original_name)->toBe('test.jpg')
        ->and(Storage::disk('spaces')->exists($media->path))->toBeTrue();
});

test('uploader component can upload multiple files', function () {
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

    expect(Media::count())->toBe(3);
    
    $mediaNames = Media::pluck('original_name')->toArray();
    expect($mediaNames)->toContain('test1.jpg', 'test2.png', 'test3.gif');
});

test('uploader component validates file types', function () {
    $user = User::factory()->create();
    $invalidFile = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$invalidFile])
        ->call('save')
        ->assertHasErrors(['files.0']);

    expect(Media::count())->toBe(0);
});

test('uploader component validates file size', function () {
    $user = User::factory()->create();
    $largeFile = UploadedFile::fake()->image('large.jpg')->size(102400); // 100MB

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('maxFileSizeMB', 50) // Set 50MB limit
        ->set('files', [$largeFile])
        ->call('save')
        ->assertHasErrors(['files.0']);

    expect(Media::count())->toBe(0);
});

test('uploader component can check for duplicates', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    // First upload
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('checkDuplicates', true)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(Media::count())->toBe(1);

    // Second upload of same content should be prevented
    $duplicateFile = UploadedFile::fake()->image('test.jpg');
    
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('checkDuplicates', true)
        ->set('files', [$duplicateFile])
        ->call('save')
        ->assertHasErrors();

    expect(Media::count())->toBe(1);
});

test('uploader component can skip duplicate checking', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    // First upload
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('checkDuplicates', false)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    // Second upload should succeed
    $duplicateFile = UploadedFile::fake()->image('test.jpg');
    
    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('checkDuplicates', false)
        ->set('files', [$duplicateFile])
        ->call('save')
        ->assertHasNoErrors();

    expect(Media::count())->toBe(2);
});

test('uploader component can extract metadata', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 1200, 800);

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('extractMetadata', true)
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $media = Media::first();
    expect($media->width)->toBe(1200)
        ->and($media->height)->toBe(800);
});

test('uploader component can use different storage disks', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('disk', 'local')
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $media = Media::first();
    expect($media->disk->value)->toBe('local')
        ->and(Storage::disk('local')->exists($media->path))->toBeTrue();
});

test('uploader component tracks upload progress', function () {
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
    
    // The media should still exist in database
    expect(Media::count())->toBe(2);
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

test('uploader component handles upload errors gracefully', function () {
    $user = User::factory()->create();
    $invalidFile = UploadedFile::fake()->create('invalid.txt', 1024, 'text/plain');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$invalidFile])
        ->call('save')
        ->assertHasErrors();

    expect(Media::count())->toBe(0);
});

test('uploader component dispatches upload complete event', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', [$file])
        ->call('save')
        ->assertDispatched('upload-completed');
});

test('uploader component validates maximum number of files', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 25))->map(fn($i) => 
        UploadedFile::fake()->image("test{$i}.jpg")
    )->toArray();

    Livewire::actingAs($user)
        ->test(Uploader::class)
        ->set('files', $files)
        ->call('save')
        ->assertHasErrors(['files']);

    expect(Media::count())->toBe(0);
});

test('uploader component formats file sizes correctly', function () {
    $user = User::factory()->create();
    
    $component = Livewire::actingAs($user)->test(Uploader::class);

    expect($component->instance()->formatFileSize(1024))->toBe('1.00 KB')
        ->and($component->instance()->formatFileSize(1048576))->toBe('1.00 MB')
        ->and($component->instance()->formatFileSize(1073741824))->toBe('1.00 GB')
        ->and($component->instance()->formatFileSize(512))->toBe('512 B');
});