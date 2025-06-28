<?php

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::resource('images', ImageController::class)->names('images');

require __DIR__.'/auth.php';
