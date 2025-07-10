<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Import;

class ImportPolicy
{
    /**
     * Determine if the user can view any imports.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the import.
     */
    public function view(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }

    /**
     * Determine if the user can create imports.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the import.
     */
    public function update(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }

    /**
     * Determine if the user can delete the import.
     */
    public function delete(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }

    /**
     * Determine if the user can restore the import.
     */
    public function restore(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }

    /**
     * Determine if the user can permanently delete the import.
     */
    public function forceDelete(User $user, Import $import): bool
    {
        return $user->id === $import->user_id;
    }
}
