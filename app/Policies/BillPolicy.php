<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any bills.
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
     * Determine whether the user can view the bill.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Bill  $bill
     * @return bool
     */
    public function view(User $user, Bill $bill): bool
    {
        return $this->isOwnerOrStaff($user, $bill->restaurant);
    }

    /**
     * Determine whether the user can create bills.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Restaurant  $restaurant
     * @return bool
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }

    /**
     * Determine whether the user can update the bill.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Bill  $bill
     * @return bool
     */
    public function update(User $user, Bill $bill): bool
    {
        return $this->isOwnerOrStaff($user, $bill->restaurant);
    }
}
