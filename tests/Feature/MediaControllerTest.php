<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('spaces');
});

test('media index page can be accessed by authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/media');

    $response->assertStatus(200);
});

test('media index page redirects unauthenticated users', function () {
    $response = $this->get('/media');

    $response->assertRedirect('/login');
});

test('media show page displays media for owner', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/media/{$media->id}");

    $response->assertStatus(200)
        ->assertViewIs('media.show')
        ->assertViewHas('media', $media);
});

test('media show page denies access to non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->get("/media/{$media->id}");

    $response->assertStatus(403);
});

test('media view page displays media for owner', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/media/{$media->id}/view");

    $response->assertStatus(200)
        ->assertViewIs('media.view')
        ->assertViewHas('media', $media);
});

test('media view page denies access to non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->get("/media/{$media->id}/view");

    $response->assertStatus(403);
});

test('media download works for owner', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
        'original_name' => 'test image.jpg',
    ]);

    // Create the file on storage
    Storage::disk($media->disk->value)->put($media->path, 'fake file content');

    $response = $this->actingAs($user)->get("/media/{$media->id}/download");

    $response->assertStatus(200)
        ->assertHeader('content-disposition', 'attachment; filename="test image.jpg"');
});

test('media download denies access to non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->get("/media/{$media->id}/download");

    $response->assertStatus(403);
});

test('media download returns 404 when file not found', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/nonexistent.jpg',
    ]);

    $response = $this->actingAs($user)->get("/media/{$media->id}/download");

    $response->assertStatus(404);
});

test('media edit page displays for owner', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/media/{$media->id}/edit");

    $response->assertStatus(200)
        ->assertViewIs('media.edit')
        ->assertViewHas('media', $media);
});

test('media edit page denies access to non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->get("/media/{$media->id}/edit");

    $response->assertStatus(403);
});

test('media can be deleted by owner', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
    ]);

    // Create the file on storage
    Storage::disk($media->disk->value)->put($media->path, 'fake file content');

    expect(Media::count())->toBe(1)
        ->and(Storage::disk($media->disk->value)->exists($media->path))->toBeTrue();

    $response = $this->actingAs($user)->delete("/media/{$media->id}");

    $response->assertRedirect('/media')
        ->assertSessionHas('success', 'Media deleted successfully.');

    expect(Media::count())->toBe(0)
        ->and(Storage::disk($media->disk->value)->exists($media->path))->toBeFalse();
});

test('media deletion is denied to non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->delete("/media/{$media->id}");

    $response->assertStatus(403);
    expect(Media::count())->toBe(1);
});

test('media create page can be accessed by authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/media/create');

    $response->assertStatus(200)
        ->assertViewIs('media.create');
});

test('media create page redirects unauthenticated users', function () {
    $response = $this->get('/media/create');

    $response->assertRedirect('/login');
});

test('legacy images routes redirect to media routes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/images');

    $response->assertRedirect('/media');
});

test('media routes require authentication', function () {
    $media = Media::factory()->create();

    $routes = [
        "/media/{$media->id}",
        "/media/{$media->id}/view", 
        "/media/{$media->id}/download",
        "/media/{$media->id}/edit",
        "/media/create",
    ];

    foreach ($routes as $route) {
        $response = $this->get($route);
        $response->assertRedirect('/login');
    }
});

test('media routes work with proper authentication and authorization', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create([
        'user_id' => $user->id,
        'path' => 'uploads/test.jpg',
    ]);

    // Create the file for download test
    Storage::disk($media->disk->value)->put($media->path, 'fake content');

    $routes = [
        ['GET', "/media/{$media->id}", 200],
        ['GET', "/media/{$media->id}/view", 200],
        ['GET', "/media/{$media->id}/download", 200],
        ['GET', "/media/{$media->id}/edit", 200],
        ['GET', "/media/create", 200],
    ];

    foreach ($routes as [$method, $route, $expectedStatus]) {
        $response = $this->actingAs($user)->call($method, $route);
        expect($response->getStatusCode())->toBe($expectedStatus, "Route {$method} {$route} failed");
    }
});

test('media model binding works correctly', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/media/{$media->id}");

    $response->assertStatus(200);
    
    // Test with non-existent ID
    $response = $this->actingAs($user)->get("/media/99999");
    $response->assertStatus(404);
});

test('media policies are properly enforced across controller methods', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $media = Media::factory()->create(['user_id' => $owner->id]);

    $protectedRoutes = [
        ['GET', "/media/{$media->id}"],
        ['GET', "/media/{$media->id}/view"],
        ['GET', "/media/{$media->id}/download"],
        ['GET', "/media/{$media->id}/edit"],
        ['DELETE', "/media/{$media->id}"],
    ];

    foreach ($protectedRoutes as [$method, $route]) {
        $response = $this->actingAs($otherUser)->call($method, $route);
        expect($response->getStatusCode())->toBe(403, "Route {$method} {$route} should deny access to non-owner");
    }
});