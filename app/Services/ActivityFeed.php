<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\BookingStatusChange;
use App\Models\Payment;
use App\Support\ActivityEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The owner's "Recent activity": booking decisions and settled payments,
 * newest first, read from the audit trail and the payments table.
 */
class ActivityFeed
{
    /**
     * @return list<ActivityEntry>
     */
    public function latest(int $limit = 10): array
    {
        $changes = BookingStatusChange::with(['user', 'booking.user', 'booking.cottage'])
            ->latest('created_at')->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (BookingStatusChange $change) => $this->fromChange($change));

        $payments = Payment::with(['booking', 'recordedBy'])
            ->where('status', PaymentRecordStatus::Paid)
            ->whereNotNull('paid_at')
            ->latest('paid_at')->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment) => $this->fromPayment($payment));

        return $changes->concat($payments)
            ->sortByDesc(fn (ActivityEntry $entry) => $entry->at->getTimestamp())
            ->take($limit)
            ->values()
            ->all();
    }

    private function fromChange(BookingStatusChange $change): ActivityEntry
    {
        $booking = $change->booking;
        $ref = $booking->reference_code;
        $actor = $change->user?->name ?? 'System';

        $text = match ($change->to_status) {
            BookingStatus::Pending => "{$booking->user->name} requested {$booking->cottage->name} · "
                .$booking->check_in->format('M j').'–'.$booking->check_out->format('M j'),
            BookingStatus::Confirmed => "{$actor} approved {$ref}",
            BookingStatus::Rejected => "{$actor} rejected {$ref}",
            BookingStatus::Cancelled => "{$actor} cancelled {$ref}",
            BookingStatus::CheckedIn => "{$actor} checked in {$ref}",
            BookingStatus::CheckedOut => "{$actor} checked out {$ref}",
        };

        return new ActivityEntry(
            at: CarbonImmutable::instance($change->created_at),
            text: $text,
            reference: $ref,
            tone: $change->to_status === BookingStatus::Pending ? 'attention' : 'info',
        );
    }

    private function fromPayment(Payment $payment): ActivityEntry
    {
        $ref = $payment->booking->reference_code;
        $amount = Money::format($payment->amount);
        $by = $payment->recordedBy?->name ?? 'the front desk';

        $text = match (true) {
            $payment->isRefund() => "Refund {$amount} by {$by} · {$ref}",
            $payment->method === PaymentMethod::Cash => "Cash {$amount} recorded by {$by} · {$ref}",
            default => "{$payment->method->label()} {$amount} received · {$ref}",
        };

        return new ActivityEntry(
            at: CarbonImmutable::instance($payment->paid_at),
            text: $text,
            reference: $ref,
            tone: 'money',
        );
    }
}
