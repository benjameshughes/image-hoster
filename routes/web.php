<?php

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::resource('images', ImageController::class)
    ->middleware(['auth', 'verified']);

// Public route to view an image
Route::get('/image/{filename}', [ImageController::class, 'show'])
    ->where('filename', '.*')
    ->name('image.show');

Route::get('/image/{filename}/download', [ImageController::class, 'download'])
    ->where('filename', '.*')
    ->name('image.download')
    ->middleware(['auth']);

require __DIR__.'/auth.php';
