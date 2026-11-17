<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ChatInquiry;
use App\Models\User;
use App\Notifications\Console\PaymentNeedsReview;
use App\Support\AttentionItem;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * What needs a person right now, for the top of the owner overview. Read-only:
 * each row is a count with a link to where it gets handled.
 */
class AttentionService
{
    /** How many days ahead "arriving soon" looks (today included). */
    private const SOON_DAYS = 3;

    /**
     * @return list<AttentionItem>
     */
    public function items(User $viewer): array
    {
        return array_values(array_filter([
            $this->pendingRequests(),
            $this->balancesDueSoon(),
            $this->noShows(),
            $this->paymentsToReview($viewer),
            $this->chatbotGaps(),
        ]));
    }

    private function pendingRequests(): ?AttentionItem
    {
        $pending = Booking::where('status', BookingStatus::Pending);
        $count = (clone $pending)->count();

        if ($count === 0) {
            return null;
        }

        $hours = (int) CarbonImmutable::parse($pending->min('created_at'))->diffInHours(now(), true);
        $age = $hours < 48 ? "{$hours}h" : intdiv($hours, 24).' days';

        return new AttentionItem(
            key: 'pending',
            tone: 'attention',
            text: $count.' '.Str::plural('request', $count)." waiting for approval (oldest {$age})",
            url: route('staff.bookings.index', ['tab' => 'pending']),
        );
    }

    private function balancesDueSoon(): ?AttentionItem
    {
        $today = CarbonImmutable::today();

        $owing = Booking::with('user')
            ->where('status', BookingStatus::Confirmed)
            ->whereBetween('check_in', [$today->toDateString(), $today->addDays(self::SOON_DAYS)->toDateString()])
            ->orderBy('check_in')
            ->get()
            ->filter(fn (Booking $booking) => Money::isPositive($booking->balance()))
            ->values();

        if ($owing->isEmpty()) {
            return null;
        }

        $count = $owing->count();
        $total = $owing->reduce(fn (string $sum, Booking $booking) => Money::add($sum, $booking->balance()), '0.00');

        return new AttentionItem(
            key: 'due_soon',
            tone: 'money',
            text: $count.' '.Str::plural('guest', $count).' arriving in the next '.self::SOON_DAYS.' days still '
                .($count === 1 ? 'owes' : 'owe').' '.Money::format($total),
            url: route('staff.bookings.index', ['tab' => 'upcoming']),
            bookings: $owing->take(3)->all(),
        );
    }

    private function noShows(): ?AttentionItem
    {
        $ended = Booking::where('status', BookingStatus::Confirmed)
            ->where('check_out', '<=', CarbonImmutable::today()->toDateString());
        $count = (clone $ended)->count();

        if ($count === 0) {
            return null;
        }

        return new AttentionItem(
            key: 'no_show',
            tone: 'warning',
            text: $count.' confirmed '.Str::plural('stay', $count).' ended without a check-in',
            url: route('staff.bookings.index', ['tab' => 'arrivals']),
            bookings: $ended->with('user')->orderBy('check_out')->limit(3)->get()->all(),
        );
    }

    /** Counted from the viewer's unread alerts: opening the alert acknowledges it. */
    private function paymentsToReview(User $viewer): ?AttentionItem
    {
        $count = $viewer->unreadNotifications()->where('type', PaymentNeedsReview::class)->count();

        if ($count === 0) {
            return null;
        }

        return new AttentionItem(
            key: 'payment_review',
            tone: 'warning',
            text: $count.' online '.Str::plural('payment', $count).' arrived with the wrong amount',
            url: route('staff.notifications.index'),
        );
    }

    private function chatbotGaps(): ?AttentionItem
    {
        $count = ChatInquiry::where('was_fallback', true)->where('created_at', '>=', now()->subDays(7))->count();

        if ($count === 0) {
            return null;
        }

        return new AttentionItem(
            key: 'chatbot',
            tone: 'info',
            text: $count.' '.Str::plural('question', $count)." in the last 7 days the assistant couldn't answer",
            url: route('admin.chatbot.index', ['filter' => 'fallback']),
        );
    }
}
