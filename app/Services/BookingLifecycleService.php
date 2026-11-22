<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every status change goes through here so the state machine, the date
 * guards, and the audit trail are enforced in one place.
 *
 * Releasing dates needs no extra work: rejected, cancelled, and checked-out
 * bookings simply stop counting in AvailabilityService.
 */
class BookingLifecycleService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ConsoleNotifier $notifier,
    ) {}

    public function approve(Booking $booking, User $actor, ?string $remarks = null): Booking
    {
        $this->transition($booking, BookingStatus::Confirmed, $actor, $remarks, function (Booking $b) {
            // A confirmed stay that has already ended could never be checked in.
            if (CarbonImmutable::today()->gte($b->check_out)) {
                throw InvalidBookingTransitionException::because('This stay\'s dates have already passed. Reject the request instead.');
            }
        });

        return $this->notifyGuest($booking, $remarks);
    }

    public function reject(Booking $booking, User $actor, string $remarks): Booking
    {
        $this->transition($booking, BookingStatus::Rejected, $actor, $remarks);
        $this->payments->closeOpenCheckouts($booking);

        return $this->notifyGuest($booking, $remarks);
    }

    /**
     * Guests may cancel their own pending/confirmed booking before the check-in
     * day. Staff may cancel any pending/confirmed booking.
     */
    public function cancel(Booking $booking, User $actor, ?string $remarks = null): Booking
    {
        $byGuest = $actor->isGuest();

        $this->transition($booking, BookingStatus::Cancelled, $actor, $remarks, function (Booking $b) use ($actor, $byGuest) {
            if ($byGuest && $b->user_id !== $actor->id) {
                throw InvalidBookingTransitionException::because('You can only cancel your own bookings.');
            }

            if ($byGuest && ! $b->isCancellableByGuest()) {
                throw InvalidBookingTransitionException::because('This booking can no longer be cancelled online. Please contact the resort.');
            }
        });

        $this->payments->closeOpenCheckouts($booking);

        if ($byGuest) {
            // The guest already knows; the desk needs to, because the dates are open again.
            $this->notifier->guestCancelled($booking);

            return $booking;
        }

        // Tell the guest when the resort cancelled it.
        return $this->notifyGuest($booking, $remarks);
    }

    public function checkIn(Booking $booking, User $actor, ?string $remarks = null): Booking
    {
        return $this->transition($booking, BookingStatus::CheckedIn, $actor, $remarks, function (Booking $b) {
            $today = CarbonImmutable::today();

            if ($today->lt($b->check_in)) {
                throw InvalidBookingTransitionException::because('This guest can check in from '.$b->check_in->format('M j, Y').'.');
            }

            if ($today->gte($b->check_out)) {
                throw InvalidBookingTransitionException::because('This stay\'s dates have already passed. Cancel the booking instead.');
            }
        });
    }

    public function checkOut(Booking $booking, User $actor, ?string $remarks = null): Booking
    {
        return $this->transition($booking, BookingStatus::CheckedOut, $actor, $remarks);
    }

    /**
     * Email the guest about the resort's decision. Runs after the transition is
     * committed, and a mail outage must never block or undo the decision.
     */
    private function notifyGuest(Booking $booking, ?string $remarks): Booking
    {
        try {
            $booking->loadMissing(['user', 'cottage'])->user->notify(new BookingStatusChanged($booking, filled($remarks) ? trim($remarks) : null));
        } catch (Throwable $e) {
            Log::error('Could not send the booking status email', ['booking' => $booking->reference_code, 'error' => $e->getMessage()]);
        }

        return $booking;
    }

    /**
     * @param  Closure(Booking): void|null  $guard  extra rule, run on the locked row
     */
    private function transition(Booking $booking, BookingStatus $to, User $actor, ?string $remarks, ?Closure $guard = null): Booking
    {
        return DB::transaction(function () use ($booking, $to, $actor, $remarks, $guard) {
            // Re-read under lock so two staff clicking at once can't both act.
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidBookingTransitionException::between($from, $to);
            }

            $guard?->__invoke($locked);

            $locked->status = $to;
            $locked->save();

            $locked->statusChanges()->create([
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'remarks' => filled($remarks) ? trim($remarks) : null,
            ]);

            $booking->setRawAttributes($locked->getAttributes(), true);

            return $booking;
        });
    }
}
