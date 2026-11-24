<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\DatesUnavailableException;
use App\Exceptions\InvalidStayException;
use App\Models\Booking;
use App\Models\Cottage;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The single source of truth for "is this unit free for these dates?"
 *
 * A booking holds the half-open interval [check_in, check_out). Two stays
 * overlap only when   existing.check_in < requested.check_out
 *                 AND existing.check_out > requested.check_in
 * so a stay ending on the day another begins is NOT an overlap
 * (12:00 NN check-out → 2:00 PM check-in changeover).
 *
 * Only pending, confirmed, and checked-in bookings hold dates.
 */
class AvailabilityService
{
    /**
     * Is the unit free for every night in [$checkIn, $checkOut)?
     *
     * @param  int|null  $ignoreBookingId  exclude this booking (when editing it)
     */
    public function isAvailable(Cottage $cottage, string|DateTimeInterface $checkIn, string|DateTimeInterface $checkOut, ?int $ignoreBookingId = null): bool
    {
        [$in, $out] = [$this->toDate($checkIn), $this->toDate($checkOut)];

        if ($out <= $in) {
            return false; // a stay needs at least one night
        }

        return ! $this->conflicts($cottage, $in, $out, $ignoreBookingId)->exists();
    }

    /**
     * Bookings that hold any night of [$checkIn, $checkOut) for this unit.
     *
     * @return Builder<Booking>
     */
    public function conflicts(Cottage $cottage, string|DateTimeInterface $checkIn, string|DateTimeInterface $checkOut, ?int $ignoreBookingId = null): Builder
    {
        return Booking::query()
            ->where('cottage_id', $cottage->getKey())
            ->blocking()
            ->overlapping($this->toDate($checkIn), $this->toDate($checkOut))
            ->when($ignoreBookingId, fn (Builder $q) => $q->whereKeyNot($ignoreBookingId));
    }

    /**
     * Listed units that fit the party and are free for the whole stay.
     *
     * @return Builder<Cottage>
     */
    public function availableCottages(string|DateTimeInterface $checkIn, string|DateTimeInterface $checkOut, ?int $guests = null): Builder
    {
        [$in, $out] = [$this->toDate($checkIn), $this->toDate($checkOut)];

        return Cottage::query()
            ->active()
            ->forGuests($guests)
            ->whereDoesntHave('bookings', fn (Builder $q) => $q->blocking()->overlapping($in, $out));
    }

    /**
     * Nights (as 'Y-m-d') in [$from, $to) that are already held for this unit.
     * Used by the availability calendar. Reveals dates only, never guest data.
     *
     * @return list<string>
     */
    public function bookedNights(Cottage $cottage, string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        [$from, $to] = [$this->toDate($from), $this->toDate($to)];

        $nights = [];

        $this->conflicts($cottage, $from, $to)
            ->get(['check_in', 'check_out'])
            ->each(function (Booking $booking) use ($from, $to, &$nights) {
                $night = max($booking->check_in->toDateString(), $from);
                $end = min($booking->check_out->toDateString(), $to);

                while ($night < $end) {
                    $nights[$night] = true;
                    $night = CarbonImmutable::parse($night)->addDay()->toDateString();
                }
            });

        $nights = array_keys($nights);
        sort($nights);

        return $nights;
    }

    /**
     * Run $callback only if the dates are still free, holding a row lock on the
     * cottage so two simultaneous requests can't both pass the check.
     * Every code path that creates or moves a booking must go through here.
     *
     * @template T
     *
     * @param  Closure(Cottage): T  $callback
     * @return T
     *
     * @throws DatesUnavailableException
     * @throws Throwable
     */
    public function reserve(Cottage $cottage, string|DateTimeInterface $checkIn, string|DateTimeInterface $checkOut, Closure $callback, ?int $ignoreBookingId = null): mixed
    {
        return DB::transaction(function () use ($cottage, $checkIn, $checkOut, $callback, $ignoreBookingId) {
            // Serialises all bookings for this unit until the transaction ends.
            $locked = Cottage::query()->whereKey($cottage->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->isAvailable($locked, $checkIn, $checkOut, $ignoreBookingId)) {
                throw DatesUnavailableException::make();
            }

            return $callback($locked);
        });
    }

    /**
     * Check the dates themselves (not occupancy) against the resort's rules.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [checkIn, checkOut]
     *
     * @throws InvalidStayException
     */
    public function validateStay(string|DateTimeInterface|null $checkIn, string|DateTimeInterface|null $checkOut): array
    {
        if (blank($checkIn) || blank($checkOut)) {
            throw new InvalidStayException('Please choose both a check-in and a check-out date.');
        }

        try {
            $in = $checkIn instanceof DateTimeInterface
                ? CarbonImmutable::instance($checkIn)->startOfDay()
                : CarbonImmutable::createFromFormat('!Y-m-d', $checkIn);
            $out = $checkOut instanceof DateTimeInterface
                ? CarbonImmutable::instance($checkOut)->startOfDay()
                : CarbonImmutable::createFromFormat('!Y-m-d', $checkOut);
        } catch (Throwable) {
            $in = $out = false;
        }

        // Reject overflowed dates like 2026-02-31 (PHP would roll them into March).
        $roundTrips = fn ($date, $raw) => $raw instanceof DateTimeInterface || $date->format('Y-m-d') === $raw;

        if (! $in || ! $out || ! $roundTrips($in, $checkIn) || ! $roundTrips($out, $checkOut)) {
            throw new InvalidStayException('Please choose valid check-in and check-out dates.');
        }

        $today = CarbonImmutable::today();

        if ($in->lt($today)) {
            throw new InvalidStayException('Check-in can’t be in the past.');
        }

        $minAdvance = (int) config('bookease.min_advance_days');
        if ($minAdvance > 0 && $in->lt($today->addDays($minAdvance))) {
            throw new InvalidStayException("Reservations must be made at least {$minAdvance} days before arrival.");
        }

        if ($out->lte($in)) {
            throw new InvalidStayException('Check-out must be at least one day after check-in.');
        }

        $maxNights = (int) config('bookease.max_nights');
        if ($in->diffInDays($out) > $maxNights) {
            throw new InvalidStayException("Online bookings are limited to {$maxNights} nights. Please contact the resort for longer stays.");
        }

        $window = (int) config('bookease.booking_window_days');
        if ($in->gt($today->addDays($window))) {
            throw new InvalidStayException("Bookings open up to {$window} days ahead.");
        }

        return [$in, $out];
    }

    public function nights(string|DateTimeInterface $checkIn, string|DateTimeInterface $checkOut): int
    {
        $in = CarbonImmutable::parse($this->toDate($checkIn));
        $out = CarbonImmutable::parse($this->toDate($checkOut));

        return max(0, (int) $in->diffInDays($out, false));
    }

    /**
     * Total for the stay: nightly rate × nights, in bcmath (Excerpt 2).
     */
    public function quote(Cottage $cottage, string|DateTimeInterface $checkIn, string|DateTimeInterface $checkOut): string
    {
        return Money::mul((string) $cottage->price_per_night, $this->nights($checkIn, $checkOut));
    }

    /**
     * Payment status of a booking from its total and the amount settled (Excerpt 2).
     */
    public function paymentStatus(string $total, string $paid): PaymentStatus
    {
        if (Money::compare($paid, '0.00') <= 0) {
            return PaymentStatus::Unpaid;
        }

        return Money::compare($paid, $total) < 0 ? PaymentStatus::PartiallyPaid : PaymentStatus::Paid;
    }

    private function toDate(string|DateTimeInterface $date): string
    {
        return CarbonImmutable::parse($date)->toDateString();
    }
}
