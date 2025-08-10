<?php

namespace App\Policies;

use App\Models\QrCode;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QrCodePolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any QR codes.
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
     * Determine whether the user can view the QR code.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\QrCode  $qrCode
     * @return bool
     */
    public function view(User $user, QrCode $qrCode): bool
    {
        return $this->isOwnerOrStaff($user, $qrCode->restaurant);
    }

    /**
     * Determine whether the user can create QR codes.
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
     * Determine whether the user can update the QR code.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\QrCode  $qrCode
     * @return bool
     */
    public function update(User $user, QrCode $qrCode): bool
    {
        return $this->isOwner($user, $qrCode->restaurant);
    }

    /**
     * Determine whether the user can delete the QR code.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\QrCode  $qrCode
     * @return bool
     */
    public function delete(User $user, QrCode $qrCode): bool
    {
        return $this->isOwner($user, $qrCode->restaurant);
    }

    /**
     * Determine whether the user can toggle the active status of the QR code.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\QrCode  $qrCode
     * @return bool
     */
    public function toggleActive(User $user, QrCode $qrCode): bool
    {
        return $this->isOwner($user, $qrCode->restaurant);
    }

    /**
     * Determine whether the user can regenerate the QR code.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\QrCode  $qrCode
     * @return bool
     */
    public function regenerate(User $user, QrCode $qrCode): bool
    {
        return $this->isOwner($user, $qrCode->restaurant);
    }
}
