<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Edition;
use App\Models\User;

class EditionPolicy
{
    /**
     * Only admins can view edition listings.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Only admins can view a specific edition.
     */
    public function view(User $user, Edition $edition): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Only admins can create editions.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Only admins can delete editions, and not while processing.
     */
    public function delete(User $user, Edition $edition): bool
    {
        return $user->role === 'admin' && ! $edition->isProcessing();
    }

    /**
     * Admins cannot update editions (immutable after import).
     */
    public function update(User $user, Edition $edition): bool
    {
        return false;
    }
}
