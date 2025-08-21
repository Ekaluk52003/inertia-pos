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
     */
    public function viewAny(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }

    /**
     * Determine whether the user can view the menu item.
     */
    public function view(User $user, Menu $menu): bool
    {
        return $this->isOwnerOrStaff($user, $menu->restaurant);
    }

    /**
     * Determine whether the user can create menu items.
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwner($user, $restaurant);
    }

    /**
     * Determine whether the user can update the menu item.
     */
    public function update(User $user, Menu $menu): bool
    {
        return $this->isOwner($user, $menu->restaurant);
    }

    /**
     * Determine whether the user can delete the menu item.
     */
    public function delete(User $user, Menu $menu): bool
    {
        return $this->isOwner($user, $menu->restaurant);
    }

    /**
     * Determine whether the user can toggle the availability of the menu item.
     */
    public function toggleAvailability(User $user, Menu $menu): bool
    {
        return $this->isOwnerOrStaff($user, $menu->restaurant);
    }
}
