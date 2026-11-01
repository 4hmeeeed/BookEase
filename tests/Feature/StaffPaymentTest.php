<?php

namespace Tests\Feature;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-11-01 09:00:00');
        $this->staff = User::factory()->staff()->create();
        $this->booking = Booking::factory()
            ->for(Cottage::factory()->create())
            ->stay('2026-12-10', '2026-12-13')
            ->create(['total_price' => '7500.00']);
    }

    private function url(string $action): string
    {
        return "/staff/bookings/{$this->booking->reference_code}/{$action}";
    }

    public function test_staff_records_cash_down_payment_and_guest_gets_receipt(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '₱3,000'])->assertSessionHas('status');

        $payment = Payment::sole();
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertSame(PaymentKind::Down, $payment->kind);
        $this->assertSame('3000.00', $payment->amount);
        $this->assertSame($this->staff->id, $payment->recorded_by);
        $this->assertTrue($payment->isPaid());

        $this->booking->refresh();
        $this->assertSame('3000.00', $this->booking->amount_paid);
        $this->assertSame(PaymentStatus::PartiallyPaid, $this->booking->payment_status);
        Notification::assertSentTo($this->booking->user, PaymentReceived::class);
    }

    public function test_full_cash_payment_is_labelled_full_and_settles_booking(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '7500']);

        $this->assertSame(PaymentKind::Full, Payment::sole()->kind);
        $this->assertSame(PaymentStatus::Paid, $this->booking->fresh()->payment_status);
    }

    public function test_second_cash_payment_is_a_balance(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '3000']);
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '4500']);

        $this->assertSame(PaymentKind::Balance, Payment::latest('id')->first()->kind);
        $this->assertSame('7500.00', $this->booking->fresh()->amount_paid);
    }

    public function test_cash_cannot_exceed_balance_or_be_zero(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '7500.01'])->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => 'abc'])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guests_cannot_record_cash_or_refunds(): void
    {
        $guest = $this->booking->user;

        $this->actingAs($guest)->post($this->url('cash'), ['amount' => '7500'])->assertForbidden();
        $this->actingAs($guest)->post($this->url('refund'), ['refund_amount' => '1', 'refund_method' => 'cash'])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_partial_and_full_refunds(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '7500']);

        $this->actingAs($this->staff)->post($this->url('refund'), ['refund_amount' => '2000', 'refund_method' => 'gcash'])->assertSessionHas('status');
        $this->booking->refresh();
        $this->assertSame('5500.00', $this->booking->amount_paid);
        $this->assertSame(PaymentStatus::PartiallyPaid, $this->booking->payment_status);

        $this->actingAs($this->staff)->post($this->url('refund'), ['refund_amount' => '5500', 'refund_method' => 'cash']);
        $this->booking->refresh();
        $this->assertSame('0.00', $this->booking->amount_paid);
        $this->assertSame(PaymentStatus::Refunded, $this->booking->payment_status);

        $refund = Payment::where('kind', PaymentKind::Refund)->latest('id')->first();
        $this->assertSame($this->staff->id, $refund->recorded_by);
    }

    public function test_refund_cannot_exceed_amount_paid(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '1000']);

        $this->actingAs($this->staff)
            ->post($this->url('refund'), ['refund_amount' => '1000.01', 'refund_method' => 'gcash'])
            ->assertSessionHasErrors('refund_amount');

        $this->actingAs($this->staff)
            ->post($this->url('refund'), ['refund_amount' => '100', 'refund_method' => 'card'])
            ->assertSessionHasErrors('refund_method');

        $this->assertSame('1000.00', $this->booking->fresh()->amount_paid);
    }

    public function test_staff_booking_page_shows_payments_and_actions(): void
    {
        $this->actingAs($this->staff)->post($this->url('cash'), ['amount' => '3000']);

        $this->actingAs($this->staff)->get("/staff/bookings/{$this->booking->reference_code}")
            ->assertOk()
            ->assertSee('Record cash payment')
            ->assertSee('Record refund')
            ->assertSee('₱4,500.00')         // balance
            ->assertSee($this->staff->name);  // recorded by
    }
}
