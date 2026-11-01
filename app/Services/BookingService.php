<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\DatesUnavailableException;
use App\Exceptions\InvalidStayException;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Support\ReferenceCode;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Reservation submission: validates the stay, re-checks availability under a
 * row lock, prices it with bcmath, and stores it as pending with a reference.
 */
class BookingService
{
    private const REFERENCE_ATTEMPTS = 5;

    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array{check_in: string, check_out: string, guests_count: int|string, notes?: ?string, phone?: ?string}  $data
     *
     * @throws InvalidStayException
     * @throws DatesUnavailableException
     */
    public function create(User $guest, Cottage $cottage, array $data): Booking
    {
        if (! $cottage->is_active) {
            throw new InvalidStayException('This unit is not available for booking.');
        }

        [$in, $out] = $this->availability->validateStay($data['check_in'], $data['check_out']);

        $guests = (int) $data['guests_count'];
        if ($guests < 1 || $guests > $cottage->capacity) {
            throw new InvalidStayException("{$cottage->name} fits up to {$cottage->capacity} guests.");
        }

        if (! empty($data['phone']) && $guest->phone !== $data['phone']) {
            $guest->forceFill(['phone' => $data['phone']])->save();
        }

        return $this->availability->reserve($cottage, $in, $out, function (Cottage $locked) use ($guest, $in, $out, $guests, $data) {
            $booking = new Booking([
                'cottage_id' => $locked->id,
                'check_in' => $in,
                'check_out' => $out,
                'guests_count' => $guests,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            ]);
            $booking->user()->associate($guest);
            $booking->status = BookingStatus::Pending;
            // Price is taken from the locked row, so a rate edited mid-request can't slip in.
            $booking->total_price = $this->availability->quote($locked, $in, $out);

            $this->saveWithUniqueReference($booking);

            $booking->statusChanges()->create([
                'user_id' => $guest->id,
                'from_status' => null,
                'to_status' => BookingStatus::Pending,
                'remarks' => 'Reservation submitted online.',
            ]);

            return $booking;
        });
    }

    private function saveWithUniqueReference(Booking $booking): void
    {
        for ($attempt = 1; ; $attempt++) {
            $booking->reference_code = ReferenceCode::generate();

            try {
                // Savepoint so a collision doesn't abort the outer transaction (MySQL/Postgres).
                $booking->getConnection()->transaction(fn () => $booking->save());

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }
}
