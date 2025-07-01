<?php

use App\Http\Controllers\ImageController;
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
    Route::resource('images', ImageController::class)->names('images');
    Route::get('images/{image}/view', [ImageController::class, 'view'])->name('images.view');
    Route::get('images/{image}/download', [ImageController::class, 'download'])->name('images.download');
});

// Public image sharing routes (no authentication required)
Route::prefix('share')->name('images.public.')->group(function () {
    Route::get('{uniqueId}', [PublicImageController::class, 'show'])->name('show');
    Route::get('{uniqueId}/serve/{type?}', [PublicImageController::class, 'serve'])->name('serve');
    Route::get('{uniqueId}/embed', [PublicImageController::class, 'embed'])->name('embed');
    Route::get('{uniqueId}/metadata', [PublicImageController::class, 'metadata'])->name('metadata');
});

// Legacy route for backwards compatibility
Route::get('i/{uniqueId}', [PublicImageController::class, 'show'])->name('images.public');

require __DIR__.'/auth.php';
