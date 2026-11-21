<?php

namespace Tests\Feature;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use App\Notifications\Console\PaymentProofSubmitted;
use App\Notifications\PaymentProofRejected;
use App\Notifications\PaymentReceived;
use App\Payments\PaymentGateway;
use App\Services\AccountDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GCash/Maya sent to the resort's own wallet (PAYMENT_DRIVER=none): the guest
 * uploads the receipt, staff verify it in the wallet app, only then is it money.
 */
class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private User $staff;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
        config([
            'bookease.payments.driver' => 'none',
            'bookease.payments.online' => false,
            'bookease.payments.wallets.gcash' => ['name' => 'Labnasan Resort', 'number' => '0917 123 4567'],
            'bookease.payments.wallets.maya' => ['name' => null, 'number' => null],
        ]);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->travelTo('2026-11-01 09:00:00');

        $this->guest = User::factory()->create();
        $this->staff = User::factory()->staff()->create();
        $this->booking = Booking::factory()->for(Cottage::factory())->for($this->guest)
            ->stay('2026-12-10', '2026-12-12')
            ->create(['total_price' => '4000.00']);
    }

    private function bookingUrl(string $suffix = ''): string
    {
        return "/my/bookings/{$this->booking->reference_code}{$suffix}";
    }

    private function submit(array $overrides = [], ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->guest)
            ->from($this->bookingUrl())
            ->post($this->bookingUrl('/transfer'), array_merge([
                'method' => 'gcash',
                'kind' => 'down',
                'reference_no' => '1234 567 891011',
                'screenshot' => UploadedFile::fake()->image('receipt.png', 400, 800),
            ], $overrides));
    }

    private function staffUrl(PaymentProof $proof, string $action): string
    {
        return "/staff/bookings/{$this->booking->reference_code}/proofs/{$proof->id}/{$action}";
    }

    public function test_guest_sees_the_resort_wallet_and_amounts(): void
    {
        $this->actingAs($this->guest)->get($this->bookingUrl())
            ->assertOk()
            ->assertSee('Pay with GCash or Maya')
            ->assertSee('0917 123 4567')
            ->assertSee('Labnasan Resort')
            ->assertSee('₱2,000.00')
            ->assertDontSee('Maya number');
    }

    public function test_no_wallet_numbers_means_front_desk_only(): void
    {
        config(['bookease.payments.wallets.gcash' => ['name' => null, 'number' => null]]);

        $this->actingAs($this->guest)->get($this->bookingUrl())
            ->assertOk()
            ->assertDontSee('Pay with GCash or Maya')
            ->assertSee('Pay at the front desk');

        $this->submit()->assertSessionHasErrors('transfer');
        $this->assertSame(0, PaymentProof::count());
    }

    public function test_guest_uploads_a_receipt_and_the_desk_is_alerted(): void
    {
        $this->submit()->assertRedirect($this->bookingUrl())->assertSessionHas('status');

        $proof = PaymentProof::sole();
        $this->assertSame(PaymentProofStatus::Pending, $proof->status);
        $this->assertSame('2000.00', $proof->amount);
        $this->assertSame('1234567891011', $proof->reference_no);
        Storage::disk('local')->assertExists($proof->screenshot_path);

        // Not money yet.
        $this->assertSame(0, Payment::count());
        $this->assertSame('0.00', $this->booking->fresh()->amount_paid);

        Notification::assertSentTo($this->staff, PaymentProofSubmitted::class);
        Notification::assertNotSentTo($this->guest, PaymentProofSubmitted::class);
    }

    public function test_only_one_receipt_can_wait_at_a_time(): void
    {
        $this->submit()->assertSessionHasNoErrors();

        $this->actingAs($this->guest)->get($this->bookingUrl())->assertDontSee('Send receipt to the resort');

        $this->submit(['reference_no' => '999888777666'])->assertSessionHasErrors('transfer');
        $this->assertSame(1, PaymentProof::count());
    }

    public function test_a_reference_number_cannot_be_reused_on_another_booking(): void
    {
        $this->submit()->assertSessionHasNoErrors();

        $other = Booking::factory()->for(Cottage::factory())->for($this->guest)
            ->stay('2026-12-20', '2026-12-21')->create(['total_price' => '1000.00']);

        $this->actingAs($this->guest)
            ->post("/my/bookings/{$other->reference_code}/transfer", [
                'method' => 'gcash', 'kind' => 'full', 'reference_no' => '1234567891011',
                'screenshot' => UploadedFile::fake()->image('r.png'),
            ])->assertSessionHasErrors('transfer');

        $this->assertSame(1, PaymentProof::count());
    }

    public function test_upload_must_be_a_picture_with_a_reference(): void
    {
        $this->submit([
            'reference_no' => '12',
            'screenshot' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors(['reference_no', 'screenshot']);

        $this->assertSame(0, PaymentProof::count());
    }

    public function test_guests_cannot_pay_into_someone_elses_booking(): void
    {
        $this->submit(as: User::factory()->create())->assertForbidden();
        $this->assertSame(0, PaymentProof::count());
    }

    public function test_staff_verify_credits_the_booking_and_emails_a_receipt(): void
    {
        $this->submit();
        $proof = PaymentProof::sole();

        $this->actingAs($this->staff)->post($this->staffUrl($proof, 'verify'), ['verified_amount' => '2,000'])
            ->assertSessionHas('status');

        $payment = Payment::sole();
        $this->assertSame(PaymentMethod::GCash, $payment->method);
        $this->assertSame(PaymentKind::Down, $payment->kind);
        $this->assertSame('2000.00', $payment->amount);
        $this->assertSame($this->staff->id, $payment->recorded_by);
        $this->assertTrue($payment->isPaid());

        $proof->refresh();
        $this->assertSame(PaymentProofStatus::Verified, $proof->status);
        $this->assertSame($payment->id, $proof->payment_id);

        $booking = $this->booking->fresh();
        $this->assertSame('2000.00', $booking->amount_paid);
        $this->assertSame(PaymentStatus::PartiallyPaid, $booking->payment_status);
        Notification::assertSentTo($this->guest, PaymentReceived::class);
    }

    public function test_a_receipt_is_credited_only_once(): void
    {
        $this->submit();
        $proof = PaymentProof::sole();

        $this->actingAs($this->staff)->post($this->staffUrl($proof, 'verify'), ['verified_amount' => '2000']);
        $this->actingAs($this->staff)->post($this->staffUrl($proof, 'verify'), ['verified_amount' => '2000'])
            ->assertSessionHasErrors('proof');

        $this->assertSame(1, Payment::count());
        $this->assertSame('2000.00', $this->booking->fresh()->amount_paid);
    }

    public function test_staff_cannot_credit_more_than_the_balance(): void
    {
        $this->submit();

        $this->actingAs($this->staff)->post($this->staffUrl(PaymentProof::sole(), 'verify'), ['verified_amount' => '5000'])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, Payment::count());
        $this->assertTrue(PaymentProof::sole()->isPending());
    }

    public function test_staff_reject_emails_the_guest_who_can_then_resend(): void
    {
        $this->submit();
        $proof = PaymentProof::sole();

        $this->actingAs($this->staff)->post($this->staffUrl($proof, 'reject'), ['review_note' => 'No transfer with that reference arrived.'])
            ->assertSessionHas('status');

        $this->assertSame(PaymentProofStatus::Rejected, $proof->fresh()->status);
        $this->assertSame(0, Payment::count());
        Notification::assertSentTo($this->guest, PaymentProofRejected::class);

        // The corrected receipt (same reference, now right) is accepted.
        $this->submit()->assertSessionHasNoErrors();
        $this->assertSame(2, PaymentProof::count());
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $this->submit();

        $this->actingAs($this->staff)->post($this->staffUrl(PaymentProof::sole(), 'reject'), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');

        $this->assertTrue(PaymentProof::sole()->isPending());
    }

    public function test_screenshots_are_private(): void
    {
        $this->submit();
        $proof = PaymentProof::sole();

        $this->actingAs($this->staff)->get($this->staffUrl($proof, 'screenshot'))->assertOk();
        $this->actingAs($this->guest)->get($this->bookingUrl("/transfer/{$proof->id}/screenshot"))->assertOk();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get($this->bookingUrl("/transfer/{$proof->id}/screenshot"))->assertNotFound();
        $this->actingAs($this->guest)->get($this->staffUrl($proof, 'screenshot'))->assertForbidden();

        $this->assertStringNotContainsString('public', $proof->screenshot_path);
    }

    public function test_staff_page_shows_the_receipt_to_verify(): void
    {
        $this->submit();

        $this->actingAs($this->staff)->get("/staff/bookings/{$this->booking->reference_code}")
            ->assertOk()
            ->assertSee('GCash / Maya receipts from the guest')
            ->assertSee('1234567891011')
            ->assertSee('Verify and send receipt');
    }

    public function test_staff_can_open_the_receipt_screenshot_in_a_viewer(): void
    {
        $this->submit();
        $proof = PaymentProof::sole();

        $this->actingAs($this->staff)->get("/staff/bookings/{$this->booking->reference_code}")
            ->assertOk()
            ->assertSee("open-modal', 'proof-image-{$proof->id}'", false)
            ->assertSee('View screenshot')
            ->assertSee('Open full size');
    }

    public function test_owner_overview_counts_receipts_to_verify(): void
    {
        $owner = User::factory()->admin()->create();
        $this->submit();

        $this->actingAs($owner)->get('/admin')->assertOk()->assertSee('1 GCash/Maya payment to verify');
    }

    public function test_deleting_an_account_removes_its_screenshots(): void
    {
        $this->submit();
        $path = PaymentProof::sole()->screenshot_path;
        $this->booking->forceFill(['status' => 'cancelled'])->save();

        app(AccountDeletion::class)->delete($this->guest);

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, PaymentProof::count());
    }
}
