<?php

namespace Database\Factories;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'reference_no' => ReferenceCode::generate('OR-'.now()->format('Ymd'), 8),
            'method' => PaymentMethod::GCash,
            'kind' => PaymentKind::Down,
            'amount' => '1000.00',
            'status' => PaymentRecordStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentRecordStatus::Paid, 'paid_at' => now()]);
    }
}
