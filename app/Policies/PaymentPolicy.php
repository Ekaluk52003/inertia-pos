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
     */
    public function viewAny(User $user, Restaurant $restaurant): bool
    {
        return $this->isOwnerOrStaff($user, $restaurant);
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Payment may not be linked to an order anymore. Prefer using the
        // payment->restaurant relation if available; otherwise fall back to
        // trying payment->order->restaurant when that relation exists.
        if ($payment->restaurant) {
            return $this->isOwnerOrStaff($user, $payment->restaurant);
        }

        if (isset($payment->order) && $payment->order) {
            return $this->isOwnerOrStaff($user, $payment->order->restaurant);
        }

        // Conservative default: deny if we can't determine restaurant.
        return false;
    }
}
