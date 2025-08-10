<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RestaurantPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any restaurants.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can view the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function view(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }

    /**
     * Determine whether the user can create restaurants.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant);
    }

    /**
     * Determine whether the user can delete the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant);
    }

    /**
     * Determine whether the user can manage staff for the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function viewStaff(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant);
    }

    /**
     * Determine whether the user can create staff for the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function createStaff(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant);
    }

    /**
     * Determine whether the user can delete staff for the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @param  \App\Models\User  $staff
     * @return bool
     */
    public function deleteStaff(User $user, Restaurant $restaurant, User $staff): bool
    {
        return $this->isOwner($user, $restaurant) && $staff->isStaff() && $staff->restaurant_id === $restaurant->id;
    }
}
