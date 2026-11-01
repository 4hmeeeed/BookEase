<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /** Guests see only their own bookings; staff and the owner see all. */
    public function view(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id || $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $user->isGuest() && $user->is_active;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($this->manage($user)) {
            return true;
        }

        return $booking->user_id === $user->id && $booking->isCancellableByGuest();
    }

    /** Approve, reject, check in, check out. */
    public function manage(User $user, ?Booking $booking = null): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }
}
