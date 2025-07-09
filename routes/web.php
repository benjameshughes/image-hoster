<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PublicImageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    // Media Library (with upload rate limiting)
    Route::get('media', App\Livewire\Media\Index::class)
        ->middleware('throttle:livewire-actions')
        ->name('media.index');
    Route::resource('media', MediaController::class)->except(['index']);
    Route::get('media/{media}/view', [MediaController::class, 'view'])->name('media.view');
    Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    
    // Legacy Images routes (redirect to media)
    Route::redirect('images', 'media');
    Route::resource('images', ImageController::class)->names('images');
    Route::get('images/{image}/view', [ImageController::class, 'view'])->name('images.view');
    Route::get('images/{image}/download', [ImageController::class, 'download'])->name('images.download');
    
    // WordPress Import Routes (with import-specific rate limiting)
    Route::get('import', App\Livewire\Import\NewDashboard::class)
        ->middleware('throttle:livewire-actions')
        ->name('import.dashboard');
    Route::get('import/{import}/progress', App\Livewire\Import\NewProgress::class)
        ->middleware('throttle:livewire-actions')
        ->name('import.progress');
    Route::get('import/duplicates', App\Livewire\Import\DuplicateReview::class)
        ->middleware('throttle:expensive-operations')
        ->name('import.duplicates');
});

// Public media sharing routes (no authentication required)
Route::prefix('share')->name('media.public.')->group(function () {
    Route::get('{uniqueId}', [PublicImageController::class, 'show'])->name('show');
    Route::get('{uniqueId}/serve/{type?}', [PublicImageController::class, 'serve'])->name('serve');
    Route::get('{uniqueId}/embed', [PublicImageController::class, 'embed'])->name('embed');
    Route::get('{uniqueId}/metadata', [PublicImageController::class, 'metadata'])->name('metadata');
});

// Public media sharing route (main route)
Route::get('m/{uniqueId}', [PublicImageController::class, 'show'])->name('media.public');

// Legacy public image sharing routes (no authentication required)
Route::prefix('share')->name('images.public.')->group(function () {
    Route::get('{uniqueId}', [PublicImageController::class, 'show'])->name('show');
    Route::get('{uniqueId}/serve/{type?}', [PublicImageController::class, 'serve'])->name('serve');
    Route::get('{uniqueId}/embed', [PublicImageController::class, 'embed'])->name('embed');
    Route::get('{uniqueId}/metadata', [PublicImageController::class, 'metadata'])->name('metadata');
});

// Legacy route for backwards compatibility
Route::get('i/{uniqueId}', [PublicImageController::class, 'show'])->name('images.public');

require __DIR__.'/auth.php';
