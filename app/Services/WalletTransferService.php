<?php

namespace App\Services;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProofStatus;
use App\Exceptions\PaymentNotAllowedException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use App\Notifications\Console\PaymentProofSubmitted;
use App\Notifications\PaymentProofRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * GCash/Maya without a payment gateway: the guest sends money to the resort's
 * own wallet, then uploads the receipt and its reference number. Nothing is
 * credited until a staff member has checked the transfer in the wallet app.
 *
 * Safeguards: one open proof per booking, a reference number is credited at
 * most once (also enforced by the unique payments.provider_payment_id), the
 * proof row is locked while it is decided, and the amount can never exceed
 * the balance.
 */
class WalletTransferService
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    /**
     * The resort's wallets that guests can send to, from .env.
     *
     * @return array<string, array{label: string, name: string, number: string, qr: ?string}>
     */
    public function wallets(): array
    {
        $wallets = [];

        foreach (PaymentMethod::online() as $method) {
            $cfg = config('bookease.payments.wallets.'.$method->value, []);
            $number = trim((string) ($cfg['number'] ?? ''));

            if ($number === '') {
                continue;
            }

            $wallets[$method->value] = [
                'label' => $method->label(),
                'name' => trim((string) ($cfg['name'] ?? '')),
                'number' => $number,
                'qr' => $this->qrUrl($method),
            ];
        }

        return $wallets;
    }

    /** On only when online checkout is off and at least one wallet number is set. */
    public function enabled(): bool
    {
        return ! config('bookease.payments.online') && $this->wallets() !== [];
    }

    public function openProof(Booking $booking): ?PaymentProof
    {
        return $booking->paymentProofs()->getQuery()->where('status', PaymentProofStatus::Pending)->first();
    }

    /**
     * What the guest may send now: kind value => amount. Empty while a proof
     * is still being checked, so a guest can't pay the same balance twice.
     *
     * @return array<string, string>
     */
    public function options(Booking $booking): array
    {
        if (! $this->enabled() || $this->openProof($booking)) {
            return [];
        }

        return $this->payments->amountOptions($booking);
    }

    /**
     * @throws PaymentNotAllowedException
     */
    public function submit(Booking $booking, User $guest, PaymentMethod $method, PaymentKind $kind, string $referenceNo, UploadedFile $screenshot): PaymentProof
    {
        if (! $this->enabled() || ! array_key_exists($method->value, $this->wallets())) {
            throw new PaymentNotAllowedException('That wallet isn’t accepted right now. Please pay at the front desk.');
        }

        if ($booking->user_id !== $guest->id) {
            throw new PaymentNotAllowedException('You can only pay for your own bookings.');
        }

        $referenceNo = self::normalizeReference($referenceNo);
        $path = $screenshot->store('payment-proofs/'.now()->format('Y/m'), PaymentProof::DISK);

        if ($path === false) {
            throw new PaymentNotAllowedException('We couldn’t save your screenshot. Please try again.');
        }

        try {
            $proof = DB::transaction(function () use ($booking, $guest, $method, $kind, $referenceNo, $path) {
                $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

                if ($this->openProof($locked)) {
                    throw new PaymentNotAllowedException('The resort is still checking your last payment. You’ll get an email once it’s done.');
                }

                $amount = $this->payments->amountOptions($locked)[$kind->value] ?? null;

                if ($amount === null) {
                    throw new PaymentNotAllowedException('That payment option is not available for this booking.');
                }

                if ($this->referenceInUse($method, $referenceNo)) {
                    throw new PaymentNotAllowedException('This reference number was already submitted. Check the number on your receipt.');
                }

                $proof = new PaymentProof;
                $proof->forceFill([
                    'booking_id' => $locked->id,
                    'user_id' => $guest->id,
                    'method' => $method,
                    'kind' => $kind,
                    'amount' => $amount,
                    'reference_no' => $referenceNo,
                    'screenshot_path' => $path,
                    'status' => PaymentProofStatus::Pending,
                ])->save();

                return $proof;
            });
        } catch (Throwable $e) {
            Storage::disk(PaymentProof::DISK)->delete($path);
            throw $e;
        }

        $this->alertDesk($proof->setRelation('booking', $booking));

        return $proof;
    }

    /**
     * Staff checked the wallet app and found the transfer: credit it.
     *
     * @throws PaymentNotAllowedException
     */
    public function verify(PaymentProof $proof, User $staff, string $amount): Payment
    {
        $payment = DB::transaction(function () use ($proof, $staff, $amount) {
            $locked = PaymentProof::query()->whereKey($proof->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new PaymentNotAllowedException('This payment was already '.($locked->status === PaymentProofStatus::Verified ? 'verified' : 'rejected').'.');
            }

            if ($this->referenceInUse($locked->method, $locked->reference_no, except: $locked)) {
                throw new PaymentNotAllowedException('Another booking already used this reference number. Reject this one.');
            }

            $payment = $this->payments->creditWalletTransfer(
                $locked->booking,
                $staff,
                $amount,
                $locked->method,
                'wallet:'.$locked->method->value.':'.$locked->reference_no,
            );

            $locked->forceFill([
                'status' => PaymentProofStatus::Verified,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
                'payment_id' => $payment->id,
            ])->save();

            return $payment;
        });

        $this->payments->announceDeskPayment($payment->setRelation('booking', $proof->booking), $staff);
        $this->clearDeskAlerts($proof);

        return $payment;
    }

    /**
     * Staff could not find the transfer: tell the guest why.
     *
     * @throws PaymentNotAllowedException
     */
    public function reject(PaymentProof $proof, User $staff, string $note): PaymentProof
    {
        $rejected = DB::transaction(function () use ($proof, $staff, $note) {
            $locked = PaymentProof::query()->whereKey($proof->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new PaymentNotAllowedException('This payment was already '.($locked->status === PaymentProofStatus::Verified ? 'verified' : 'rejected').'.');
            }

            $locked->forceFill([
                'status' => PaymentProofStatus::Rejected,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $locked;
        });

        $rejected->loadMissing('booking.user', 'booking.cottage');

        try {
            $rejected->booking->user->notify(new PaymentProofRejected($rejected));
        } catch (Throwable $e) {
            Log::error('Could not email the guest about a rejected payment', ['proof' => $rejected->id, 'error' => $e->getMessage()]);
        }

        $this->clearDeskAlerts($proof);

        return $rejected;
    }

    public static function normalizeReference(string $reference): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $reference));
    }

    /** Pending or verified proofs with this reference, on any booking. */
    private function referenceInUse(PaymentMethod $method, string $referenceNo, ?PaymentProof $except = null): bool
    {
        return PaymentProof::query()
            ->where('method', $method)
            ->where('reference_no', $referenceNo)
            ->whereIn('status', [PaymentProofStatus::Pending, PaymentProofStatus::Verified])
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->exists();
    }

    private function alertDesk(PaymentProof $proof): void
    {
        try {
            $desk = User::query()
                ->whereIn('role', [\App\Enums\UserRole::Staff, \App\Enums\UserRole::Admin])
                ->where('is_active', true)
                ->get();

            Notification::send($desk, new PaymentProofSubmitted($proof));
        } catch (Throwable $e) {
            Log::error('Could not create the payment-to-verify alert', ['proof' => $proof->id, 'error' => $e->getMessage()]);
        }
    }

    /** Once someone has decided, the "to verify" alert is done for everyone. */
    private function clearDeskAlerts(PaymentProof $proof): void
    {
        try {
            DB::table('notifications')
                ->where('type', PaymentProofSubmitted::class)
                ->whereNull('read_at')
                ->where('data->reference', $proof->booking->reference_code)
                ->update(['read_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Could not clear payment-to-verify alerts', ['proof' => $proof->id, 'error' => $e->getMessage()]);
        }
    }

    /** Optional QR image the owner uploads to public/images/wallets/{gcash|maya}-qr.(png|jpg|webp). */
    private function qrUrl(PaymentMethod $method): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $file = "images/wallets/{$method->value}-qr.{$ext}";

            if (is_file(public_path($file))) {
                return asset($file);
            }
        }

        return null;
    }
}
