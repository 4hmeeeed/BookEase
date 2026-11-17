<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentNotAllowedException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use App\Support\Money;
use App\Support\ReferenceCode;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Money in and out of a booking.
 *
 * Rules (CLAUDE.md):
 *  – online payments are GCash or Maya through the gateway only
 *  – an online payment is marked paid ONLY from a verified, signed webhook
 *  – duplicate webhooks never credit twice
 *  – all arithmetic is bcmath on 2-decimal strings
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AvailabilityService $availability,
        private readonly ConsoleNotifier $notifier,
    ) {}

    // ------------------------------------------------------------------
    // What can be paid
    // ------------------------------------------------------------------

    public function isPayable(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)
            && Money::isPositive($booking->balance());
    }

    public function downPaymentAmount(Booking $booking): string
    {
        return Money::percentOf($booking->total_price, (int) config('bookease.payments.down_payment_percent'));
    }

    /**
     * Online options for the guest right now: kind value => amount.
     * Nothing paid yet → down payment or full; something paid → balance.
     *
     * @return array<string, string>
     */
    public function onlineOptions(Booking $booking): array
    {
        if (! config('bookease.payments.online') || ! $this->isPayable($booking)) {
            return [];
        }

        if (! Money::isPositive($booking->amount_paid)) {
            $down = $this->downPaymentAmount($booking);
            $options = [];

            if (Money::isPositive($down) && Money::compare($down, $booking->total_price) < 0) {
                $options[PaymentKind::Down->value] = $down;
            }

            $options[PaymentKind::Full->value] = Money::normalize($booking->total_price);

            return $options;
        }

        return [PaymentKind::Balance->value => $booking->balance()];
    }

    // ------------------------------------------------------------------
    // Online checkout
    // ------------------------------------------------------------------

    /**
     * Create a pending payment and a hosted checkout. Does NOT mark anything paid.
     *
     * @param  Closure(Payment): array{0: string, 1: string}  $returnUrls  [successUrl, cancelUrl]
     * @return array{0: Payment, 1: CheckoutSession}
     *
     * @throws PaymentNotAllowedException
     */
    public function startCheckout(Booking $booking, User $guest, PaymentKind $kind, PaymentMethod $method, Closure $returnUrls): array
    {
        if (! config('bookease.payments.online')) {
            throw new PaymentNotAllowedException('Online payment isn’t available. Please pay at the resort’s front desk.');
        }

        if ($booking->user_id !== $guest->id) {
            throw new PaymentNotAllowedException('You can only pay for your own bookings.');
        }

        if (! $method->isOnline()) {
            throw new PaymentNotAllowedException('Please choose GCash or Maya.');
        }

        [$payment, $superseded] = DB::transaction(function () use ($booking, $kind, $method) {
            $locked = $this->lock($booking);
            $amount = $this->onlineOptions($locked)[$kind->value] ?? null;

            if ($amount === null) {
                throw new PaymentNotAllowedException('That payment option is not available for this booking.');
            }

            // Only one open checkout per booking: older unfinished ones expire.
            $superseded = $this->expireOpenCheckouts($locked);

            return [$this->newPayment($locked, $method, $kind, $amount, PaymentRecordStatus::Pending), $superseded];
        });

        // Network calls happen outside the DB lock.
        $this->closeAtGateway($superseded);

        [$successUrl, $cancelUrl] = $returnUrls($payment);

        try {
            $session = $this->gateway->createCheckout($payment->setRelation('booking', $booking->loadMissing('cottage')), $successUrl, $cancelUrl);
        } catch (Throwable $e) {
            $payment->forceFill(['status' => PaymentRecordStatus::Failed])->save();
            throw $e;
        }

        $payment->forceFill(['provider_payment_id' => $session->id])->save();

        return [$payment, $session];
    }

    // ------------------------------------------------------------------
    // Webhook (the ONLY way an online payment becomes paid)
    // ------------------------------------------------------------------

    /**
     * @return string outcome for logging: paid | duplicate | failed | ignored | unknown | mismatch
     */
    public function handleWebhook(WebhookEvent $event): string
    {
        if ($event->outcome === WebhookEvent::IGNORED || ! $event->checkoutId) {
            return 'ignored';
        }

        $result = DB::transaction(function () use ($event) {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('provider_payment_id', $event->checkoutId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return ['unknown', null];
            }

            if ($payment->isPaid()) {
                return ['duplicate', $payment]; // retries/replays never credit twice
            }

            if ($event->outcome === WebhookEvent::FAILED) {
                $payment->forceFill(['status' => PaymentRecordStatus::Failed, 'provider_payload' => $event->rawBody])->save();

                return ['failed', $payment];
            }

            if ($event->amountCentavos !== Money::toCentavos($payment->amount) || strtoupper((string) $event->currency) !== 'PHP') {
                // Never credit an amount we didn't ask for; leave for staff review.
                $payment->forceFill(['provider_payload' => $event->rawBody])->save();
                Log::warning('Payment webhook amount mismatch', [
                    'payment' => $payment->reference_no,
                    'expected_centavos' => Money::toCentavos($payment->amount),
                    'received_centavos' => $event->amountCentavos,
                    'currency' => $event->currency,
                ]);

                return ['mismatch', $payment];
            }

            // Paid is authoritative even if the guest abandoned/restarted (money moved).
            $payment->forceFill([
                'status' => PaymentRecordStatus::Paid,
                'paid_at' => now(),
                'method' => $event->method ?? $payment->method,
                'provider_payload' => $event->rawBody,
            ])->save();

            $this->recalculate($this->lock($payment->booking));

            return ['paid', $payment];
        });

        [$outcome, $payment] = $result;

        if ($outcome === 'paid') {
            $this->notifyGuest($payment);
            $this->notifier->paymentReceived($payment);
        } elseif ($outcome === 'mismatch') {
            // Not credited, so a person has to look at it.
            $this->notifier->paymentNeedsReview($payment);
        }

        return $outcome;
    }

    // ------------------------------------------------------------------
    // Front desk
    // ------------------------------------------------------------------

    /**
     * Cash / walk-in payment recorded by staff. Takes effect immediately.
     *
     * @throws PaymentNotAllowedException
     */
    public function recordCash(Booking $booking, User $staff, string $amount): Payment
    {
        $amount = Money::normalize($amount);

        $payment = DB::transaction(function () use ($booking, $staff, $amount) {
            $locked = $this->lock($booking);

            if (! $this->isPayable($locked)) {
                throw new PaymentNotAllowedException('This booking has nothing left to pay.');
            }

            if (! Money::isPositive($amount) || Money::compare($amount, $locked->balance()) > 0) {
                throw new PaymentNotAllowedException('Enter an amount between ₱0.01 and the balance of '.Money::format($locked->balance()).'.');
            }

            $nothingPaidYet = ! Money::isPositive($locked->amount_paid);
            $kind = match (true) {
                $nothingPaidYet && Money::compare($amount, $locked->total_price) === 0 => PaymentKind::Full,
                $nothingPaidYet => PaymentKind::Down,
                default => PaymentKind::Balance,
            };

            $payment = $this->newPayment($locked, PaymentMethod::Cash, $kind, $amount, PaymentRecordStatus::Paid, $staff);
            $this->recalculate($locked);

            return $payment;
        });

        // Open online checkouts were priced against the old balance.
        $this->closeOpenCheckouts($booking);

        $this->notifyGuest($payment);
        $this->notifier->moneyRecorded($payment, $staff);

        return $payment;
    }

    /**
     * Record a refund the resort has sent back to the guest (e.g. via GCash or cash).
     *
     * @throws PaymentNotAllowedException
     */
    public function recordRefund(Booking $booking, User $staff, string $amount, PaymentMethod $method): Payment
    {
        $amount = Money::normalize($amount);

        $payment = DB::transaction(function () use ($booking, $staff, $amount, $method) {
            $locked = $this->lock($booking);

            if (! Money::isPositive($amount) || Money::compare($amount, $locked->amount_paid) > 0) {
                throw new PaymentNotAllowedException('A refund can be at most the amount paid, '.Money::format($locked->amount_paid).'.');
            }

            $payment = $this->newPayment($locked, $method, PaymentKind::Refund, $amount, PaymentRecordStatus::Paid, $staff);
            $this->recalculate($locked);

            return $payment;
        });

        $this->notifier->moneyRecorded($payment, $staff);

        return $payment;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * amount_paid = Σ settled payments − Σ settled refunds (bcmath, in PHP —
     * SQL SUM on SQLite would return a float).
     */
    public function recalculate(Booking $booking): Booking
    {
        $received = '0.00';
        $refunded = '0.00';

        foreach ($booking->payments()->getQuery()->where('status', PaymentRecordStatus::Paid)->get(['kind', 'amount']) as $p) {
            if ($p->isRefund()) {
                $refunded = Money::add($refunded, $p->amount);
            } else {
                $received = Money::add($received, $p->amount);
            }
        }

        $net = Money::max(Money::sub($received, $refunded), '0.00');

        $booking->amount_paid = $net;
        $booking->payment_status = Money::isPositive($refunded) && ! Money::isPositive($net)
            ? PaymentStatus::Refunded
            : $this->availability->paymentStatus($booking->total_price, $net);
        $booking->save();

        return $booking;
    }

    /**
     * A rejected or cancelled booking takes no more online payments: its
     * unfinished checkouts expire here and are closed at the gateway.
     */
    public function closeOpenCheckouts(Booking $booking): void
    {
        $this->closeAtGateway(DB::transaction(fn () => $this->expireOpenCheckouts($booking)));
    }

    /**
     * Mark the booking's unfinished online checkouts expired.
     *
     * @return list<string> their gateway checkout ids, to close at the gateway
     */
    private function expireOpenCheckouts(Booking $booking): array
    {
        $open = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentRecordStatus::Pending)
            ->whereIn('method', [PaymentMethod::GCash, PaymentMethod::Maya]);

        $checkoutIds = (clone $open)->whereNotNull('provider_payment_id')->pluck('provider_payment_id')->all();
        $open->update(['status' => PaymentRecordStatus::Expired, 'updated_at' => now()]);

        return $checkoutIds;
    }

    /**
     * Hosted checkouts stay payable until closed, so superseded ones are closed
     * at the gateway; otherwise paying both pages would charge the guest twice.
     * A refusal (e.g. a payment already under way) is logged, never fatal: a
     * payment that still lands is credited by its signed webhook as usual.
     *
     * @param  list<string>  $checkoutIds
     */
    private function closeAtGateway(array $checkoutIds): void
    {
        foreach ($checkoutIds as $checkoutId) {
            try {
                $this->gateway->expireCheckout($checkoutId);
            } catch (Throwable $e) {
                Log::warning('Could not close a superseded checkout at the payment gateway', [
                    'checkout' => $checkoutId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function lock(Booking $booking): Booking
    {
        return Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
    }

    private function newPayment(Booking $booking, PaymentMethod $method, PaymentKind $kind, string $amount, PaymentRecordStatus $status, ?User $recordedBy = null): Payment
    {
        $payment = new Payment;
        $payment->forceFill([
            'booking_id' => $booking->id,
            'reference_no' => $this->uniqueReference(),
            'method' => $method,
            'kind' => $kind,
            'amount' => $amount,
            'status' => $status,
            'paid_at' => $status === PaymentRecordStatus::Paid ? now() : null,
            'recorded_by' => $recordedBy?->id,
        ])->save();

        return $payment;
    }

    private function uniqueReference(): string
    {
        do {
            $ref = ReferenceCode::generate('OR-'.now()->format('Ymd'), 8);
        } while (Payment::where('reference_no', $ref)->exists());

        return $ref;
    }

    private function notifyGuest(Payment $payment): void
    {
        $payment->loadMissing('booking.user', 'booking.cottage');

        try {
            $payment->booking->user->notify(new PaymentReceived($payment));
        } catch (Throwable $e) {
            // A mail outage must never undo a confirmed payment.
            Log::error('Could not send payment receipt email', ['payment' => $payment->reference_no, 'error' => $e->getMessage()]);
        }
    }
}
