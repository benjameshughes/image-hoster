<?php

namespace App\Policies;

use App\Models\Image;
use App\Models\User;

class ImagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view images
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Image $image): bool
    {
        // Public images can be viewed by anyone
        if ($image->is_public) {
            return true;
        }

        // Private images can only be viewed by their owner
        return $user && $user->id === $image->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can upload images
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Image $image): bool
    {
        // Only the owner can update their images
        return $user->id === $image->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Image $image): bool
    {
        // Only the owner can delete their images
        return $user->id === $image->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Image $image): bool
    {
        // Only the owner can restore their images
        return $user->id === $image->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Image $image): bool
    {
        // Only the owner can permanently delete their images
        return $user->id === $image->user_id;
    }

    /**
     * Determine whether the user can download the image.
     */
    public function download(?User $user, Image $image): bool
    {
        // Public images can be downloaded by anyone
        if ($image->is_public) {
            return true;
        }

        // Private images can only be downloaded by their owner
        return $user && $user->id === $image->user_id;
    }

    /**
     * Determine whether the user can share the image.
     */
    public function share(User $user, Image $image): bool
    {
        // Only the owner can share their images
        return $user->id === $image->user_id;
    }
}
