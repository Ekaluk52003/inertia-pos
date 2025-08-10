<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MenuPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any menu items.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function viewAny(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }

    /**
     * Determine whether the user can view the menu item.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Menu  $menu
     * @return bool
     */
    public function view(User $user, Menu $menu): bool
    {
        return $this->isOwnerOrStaff($user, $menu->restaurant);
    }

    /**
     * Determine whether the user can create menu items.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant);
    }

    /**
     * Determine whether the user can update the menu item.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Menu  $menu
     * @return bool
     */
    public function update(User $user, Menu $menu): bool
    {
        return $this->isOwner($user, $menu->restaurant);
    }

    /**
     * Determine whether the user can delete the menu item.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Menu  $menu
     * @return bool
     */
    public function delete(User $user, Menu $menu): bool
    {
        return $this->isOwner($user, $menu->restaurant);
    }

    /**
     * Determine whether the user can toggle the availability of the menu item.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Menu  $menu
     * @return bool
     */
    public function toggleAvailability(User $user, Menu $menu): bool
    {
        return $this->isOwnerOrStaff($user, $menu->restaurant);
    }
}
