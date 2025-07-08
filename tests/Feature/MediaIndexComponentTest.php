<?php

use App\Livewire\Media\Index;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('media index component can be rendered', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertStatus(200);
});

test('media index shows user media only', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    $user1Media = Media::factory()->count(3)->create(['user_id' => $user1->id]);
    $user2Media = Media::factory()->count(2)->create(['user_id' => $user2->id]);

    Livewire::actingAs($user1)
        ->test(Index::class)
        ->assertSet('media', function ($mediaCollection) use ($user1Media) {
            return $mediaCollection->count() === 3 &&
                   $mediaCollection->pluck('id')->sort()->values()->toArray() === 
                   $user1Media->pluck('id')->sort()->values()->toArray();
        });
});

test('media index can search by name', function () {
    $user = User::factory()->create();
    
    $searchableMedia = Media::factory()->create([
        'user_id' => $user->id,
        'name' => 'searchable-image.jpg',
        'original_name' => 'searchable image.jpg',
    ]);
    
    $otherMedia = Media::factory()->create([
        'user_id' => $user->id,
        'name' => 'other-image.jpg',
        'original_name' => 'other image.jpg',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', 'searchable')
        ->assertSet('media', function ($mediaCollection) use ($searchableMedia) {
            return $mediaCollection->count() === 1 &&
                   $mediaCollection->first()->id === $searchableMedia->id;
        });
});

test('media index can search by alt text', function () {
    $user = User::factory()->create();
    
    $searchableMedia = Media::factory()->create([
        'user_id' => $user->id,
        'alt_text' => 'Beautiful sunset landscape',
    ]);
    
    $otherMedia = Media::factory()->create([
        'user_id' => $user->id,
        'alt_text' => 'Urban cityscape',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', 'sunset')
        ->assertSet('media', function ($mediaCollection) use ($searchableMedia) {
            return $mediaCollection->count() === 1 &&
                   $mediaCollection->first()->id === $searchableMedia->id;
        });
});

test('media index can filter by media type', function () {
    $user = User::factory()->create();
    
    $imageMedia = Media::factory()->image()->create(['user_id' => $user->id]);
    $videoMedia = Media::factory()->video()->create(['user_id' => $user->id]);
    $documentMedia = Media::factory()->document()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('filterByType', 'image')
        ->assertSet('media', function ($mediaCollection) use ($imageMedia) {
            return $mediaCollection->count() === 1 &&
                   $mediaCollection->first()->id === $imageMedia->id;
        });
});

test('media index can sort by different fields', function () {
    $user = User::factory()->create();
    
    $oldMedia = Media::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(2),
        'size' => 1024,
    ]);
    
    $newMedia = Media::factory()->create([
        'user_id' => $user->id,
        'created_at' => now(),
        'size' => 2048,
    ]);

    // Test sorting by created_at (default desc)
    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSet('media', function ($mediaCollection) use ($newMedia, $oldMedia) {
            return $mediaCollection->first()->id === $newMedia->id;
        });

    // Test sorting by size
    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('sortBy', 'size')
        ->assertSet('sortBy', 'size')
        ->assertSet('sortDirection', 'asc')
        ->assertSet('media', function ($mediaCollection) use ($oldMedia) {
            return $mediaCollection->first()->id === $oldMedia->id;
        });
});

test('media index can select and delete multiple media', function () {
    $user = User::factory()->create();
    
    $media1 = Media::factory()->create(['user_id' => $user->id]);
    $media2 = Media::factory()->create(['user_id' => $user->id]);
    $media3 = Media::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('selectedMedia', [$media1->id, $media2->id])
        ->call('deleteSelected')
        ->assertDispatched('media-deleted');

    expect(Media::count())->toBe(1)
        ->and(Media::first()->id)->toBe($media3->id);
});

test('media index can select all media', function () {
    $user = User::factory()->create();
    
    $media = Media::factory()->count(3)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('selectAll', true)
        ->assertSet('selectedMedia', function ($selected) use ($media) {
            return count($selected) === 3 &&
                   collect($selected)->sort()->values()->toArray() === 
                   $media->pluck('id')->sort()->values()->toArray();
        });
});

test('media index can view media details', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('view', $media)
        ->assertDispatched('show-media-modal');
});

test('media index can delete single media', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    expect(Media::count())->toBe(1);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('delete', $media)
        ->assertDispatched('media-deleted');

    expect(Media::count())->toBe(0);
});

test('media index shows statistics correctly', function () {
    $user = User::factory()->create();
    
    $imageMedia = Media::factory()->image()->count(2)->create(['user_id' => $user->id]);
    $videoMedia = Media::factory()->video()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSet('stats', function ($stats) {
            return $stats['total'] === 3 &&
                   $stats['by_type']['image'] === 2 &&
                   $stats['by_type']['video'] === 1;
        });
});

test('media index can clear filters', function () {
    $user = User::factory()->create();
    Media::factory()->count(3)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', 'test')
        ->set('filterByType', 'image')
        ->set('selectedMedia', [1, 2])
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('filterByType', '')
        ->assertSet('selectedMedia', [])
        ->assertSet('selectAll', false);
});

test('media index refreshes on upload completion', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->dispatch('upload-completed')
        ->assertDispatched('$refresh');
});

test('media index formats bytes correctly', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Index::class);

    expect($component->instance()->formatBytes(1024))->toBe('1 KB')
        ->and($component->instance()->formatBytes(1048576))->toBe('1 MB')
        ->and($component->instance()->formatBytes(1073741824))->toBe('1 GB')
        ->and($component->instance()->formatBytes(512))->toBe('512 B');
});

test('unauthorized user cannot access media index', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    Media::factory()->create(['user_id' => $user->id]);

    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

    // Try to access without authentication
    Livewire::test(Index::class);
});

test('user can only see their own media in index', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    $user1Media = Media::factory()->create(['user_id' => $user1->id]);
    $user2Media = Media::factory()->create(['user_id' => $user2->id]);

    Livewire::actingAs($user1)
        ->test(Index::class)
        ->assertSet('media', function ($mediaCollection) use ($user1Media, $user2Media) {
            return $mediaCollection->count() === 1 &&
                   $mediaCollection->first()->id === $user1Media->id &&
                   $mediaCollection->pluck('id')->doesntContain($user2Media->id);
        });
});