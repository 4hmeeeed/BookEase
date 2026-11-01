<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = now()->addDays(fake()->numberBetween(3, 60))->startOfDay();
        $nights = fake()->numberBetween(1, 4);

        return [
            'user_id' => User::factory(),
            'cottage_id' => Cottage::factory(),
            'reference_code' => 'BE-'.Str::upper(Str::random(8)),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkIn->copy()->addDays($nights)->toDateString(),
            'guests_count' => 2,
            'status' => BookingStatus::Pending,
            'total_price' => Money::mul('1500.00', $nights),
        ];
    }

    /**
     * Convenience: $factory->stay('2026-12-01', '2026-12-03')
     */
    public function stay(string $checkIn, string $checkOut): static
    {
        return $this->state(['check_in' => $checkIn, 'check_out' => $checkOut]);
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
