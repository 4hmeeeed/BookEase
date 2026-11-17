<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Guests learn by email when the resort decides on their reservation.
 */
class BookingStatusEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $guest;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 09:00:00');
        $this->staff = User::factory()->staff()->create();
        $this->guest = User::factory()->create();
        $this->booking = Booking::factory()
            ->for(Cottage::factory()->create(['name' => 'Seaside Cottage']))
            ->for($this->guest)
            ->stay('2026-12-10', '2026-12-13')
            ->create();
    }

    private function decide(string $action, ?string $remarks = null): TestResponse
    {
        return $this->actingAs($this->staff)
            ->post("/staff/bookings/{$this->booking->reference_code}/{$action}", ['remarks' => $remarks]);
    }

    /** All the text of the email the guest received for this status. */
    private function emailText(BookingStatus $status): string
    {
        $sent = Notification::sent($this->guest, BookingStatusChanged::class)
            ->first(fn (BookingStatusChanged $n) => $n->status === $status);
        $this->assertNotNull($sent, "No {$status->value} email was sent.");

        $mail = $sent->toMail($this->guest);

        return implode("\n", [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);
    }

    public function test_guest_is_emailed_when_their_reservation_is_confirmed(): void
    {
        Notification::fake();

        $this->decide('approve')->assertSessionHas('status');

        $text = $this->emailText(BookingStatus::Confirmed);
        $this->assertStringContainsString($this->booking->reference_code, $text);
        $this->assertStringContainsString('Seaside Cottage', $text);
        $this->assertStringContainsString('Dec 10, 2026', $text);
    }

    public function test_a_rejection_email_tells_the_guest_why(): void
    {
        Notification::fake();

        $this->decide('reject', 'The cottage is closed for repairs that week.');

        $this->assertStringContainsString('The cottage is closed for repairs that week.', $this->emailText(BookingStatus::Rejected));
    }

    public function test_guest_is_emailed_when_the_resort_cancels_their_booking(): void
    {
        Notification::fake();
        $this->booking->forceFill(['status' => BookingStatus::Confirmed])->save();

        $this->decide('cancel', 'Typhoon signal no. 2 over Samar.');

        $this->assertStringContainsString('Typhoon signal no. 2 over Samar.', $this->emailText(BookingStatus::Cancelled));
    }

    public function test_cancelling_your_own_booking_sends_no_status_email(): void
    {
        Notification::fake();

        $this->actingAs($this->guest)->post("/my/bookings/{$this->booking->reference_code}/cancel");

        $this->assertSame(BookingStatus::Cancelled, $this->booking->fresh()->status);
        Notification::assertNotSentTo($this->guest, BookingStatusChanged::class);
    }

    public function test_a_mail_outage_does_not_turn_a_decision_into_an_error(): void
    {
        Mail::extend('down', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "smtp.example".');
            }

            public function __toString(): string
            {
                return 'down://';
            }
        });
        config(['mail.mailers.down' => ['transport' => 'down'], 'mail.default' => 'down']);

        $this->decide('approve')->assertRedirect()->assertSessionHas('status');

        $this->assertSame(BookingStatus::Confirmed, $this->booking->fresh()->status);
    }
}
