<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user is the owner of the restaurant.
     */
    protected function isOwner(User $user, Restaurant $restaurant): bool
    {
        // Allow explicit owner role, or match by restaurant.owner_id for tests/fixtures that set owner_id
        return $user->isOwner() || ($restaurant && $restaurant->owner_id === $user->id);
    }

    /**
     * Determine if the user is a staff member of the restaurant.
     */
    protected function isStaff(User $user, Restaurant $restaurant): bool
    {
        return $user->isStaff() && $user->restaurant_id === $restaurant->id;
    }

    /**
     * Determine if the user is either the owner or a staff member of the restaurant.
     */
    protected function isOwnerOrStaff(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant) || $this->isStaff($user, $restaurant);
    }
}
