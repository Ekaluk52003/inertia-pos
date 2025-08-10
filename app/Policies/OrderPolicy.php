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
     * Determine whether the user can view the order.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order
     * @return bool
     */
    public function view(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can update the order.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order
     * @return bool
     */
    public function update(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can update the status of order items.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order
     * @return bool
     */
    public function updateItemStatus(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can mark the order as paid.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order
     * @return bool
     */
    public function markAsPaid(User $user, Order $order): bool
    {
        return $this->isOwnerOrStaff($user, $order->restaurant);
    }

    /**
     * Determine whether the user can view the kitchen display for the restaurant.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function viewKitchen(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }
}
