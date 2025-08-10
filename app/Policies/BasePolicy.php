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
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    protected function isOwner(User $user, Restaurant $restaurant): bool
    {
        return $user->isOwner() && $restaurant->owner_id === $user->id;
    }

    /**
     * Determine if the user is a staff member of the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    protected function isStaff(User $user, Restaurant $restaurant): bool
    {
        return $user->isStaff() && $user->restaurant_id === $restaurant->id;
    }

    /**
     * Determine if the user is either the owner or a staff member of the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    protected function isOwnerOrStaff(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant) || $this->isStaff($user, $restaurant);
    }
}
