<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view media
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Media $media): bool
    {
        // Public media can be viewed by anyone
        if ($media->is_public) {
            return true;
        }

        // Private media can only be viewed by their owner
        return $user && $user->id === $media->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can upload media
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Media $media): bool
    {
        // Only the owner can update their media
        return $user->id === $media->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Media $media): bool
    {
        // Only the owner can delete their media
        return $user->id === $media->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Media $media): bool
    {
        // Only the owner can restore their media
        return $user->id === $media->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        // Only the owner can permanently delete their media
        return $user->id === $media->user_id;
    }

    /**
     * Determine whether the user can download the media.
     */
    public function download(?User $user, Media $media): bool
    {
        // Public media can be downloaded by anyone
        if ($media->is_public) {
            return true;
        }

        // Private media can only be downloaded by their owner
        return $user && $user->id === $media->user_id;
    }

    /**
     * Determine whether the user can share the media.
     */
    public function share(User $user, Media $media): bool
    {
        // Only the owner can share their media
        return $user->id === $media->user_id;
    }
}