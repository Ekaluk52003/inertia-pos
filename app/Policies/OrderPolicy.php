<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can update the status of order items.
     */
    public function updateItemStatus(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can mark the order as paid.
     */
    public function markAsPaid(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can view the kitchen display for the restaurant.
     */
    public function viewKitchen(User $user, Restaurant $restaurant): bool
    {
        // Allow any owner to view kitchen for any restaurant
        // if ($user->isOwner()) {
        //     return true;
        // }

        // Staff can only view kitchen for their assigned restaurant
        return $this->isStaff($user, $restaurant);
    }
}
