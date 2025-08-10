<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any payments.
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
     * Determine whether the user can view the payment.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment
     * @return bool
     */
    public function view(User $user, Payment $payment): bool
    {
        return $this->isOwnerOrStaff($user, $payment->order->restaurant);
    }
}
