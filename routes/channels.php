<?php

use App\Models\Import;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Import-specific channels
Broadcast::channel('import.{importId}', function (User $user, int $importId) {
    return $user->id === Import::findOrFail($importId)->user_id;
});

// User imports channel
Broadcast::channel('user.{userId}.imports', function (User $user, int $userId) {
    return $user->id === $userId;
});

// User uploads channel
Broadcast::channel('user.{userId}.uploads', function (User $user, int $userId) {
    return $user->id === $userId;
});

// Upload session channel
Broadcast::channel('upload.{sessionId}', function (User $user, string $sessionId) {
    // Allow access to any authenticated user for their upload sessions
    return (bool) $user;
});
