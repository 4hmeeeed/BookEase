<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ChatInquiry;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Console\ChatbotUnanswered;
use App\Notifications\Console\ConsoleNotification;
use App\Notifications\Console\GuestCancelledBooking;
use App\Notifications\Console\MoneyRecordedByStaff;
use App\Notifications\Console\NewBookingRequest;
use App\Notifications\Console\OnlinePaymentReceived;
use App\Notifications\Console\PaymentNeedsReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Creates the console alerts behind the staff/owner bell.
 *
 * Callers run this after their own transaction has committed. An alert is a
 * courtesy: when it can't be stored, the failure is logged and the booking,
 * payment or decision that triggered it stands.
 */
class ConsoleNotifier
{
    public function bookingRequested(Booking $booking): void
    {
        $this->guard('booking.requested', fn () => $this->send(
            $this->desk(),
            new NewBookingRequest($booking->loadMissing(['user', 'cottage'])),
        ));
    }

    public function paymentReceived(Payment $payment): void
    {
        $this->guard('payment.received', fn () => $this->send($this->desk(), new OnlinePaymentReceived($payment->loadMissing('booking'))));
    }

    public function paymentNeedsReview(Payment $payment): void
    {
        $this->guard('payment.review', fn () => $this->send($this->desk(), new PaymentNeedsReview($payment->loadMissing('booking'))));
    }

    /** Cash and refunds are entered by the desk itself, so only the owners hear about them. */
    public function moneyRecorded(Payment $payment, User $actor): void
    {
        $this->guard('payment.recorded', fn () => $this->send($this->owners($actor), new MoneyRecordedByStaff($payment->loadMissing('booking'))));
    }

    public function guestCancelled(Booking $booking): void
    {
        $this->guard('booking.cancelled', fn () => $this->send(
            $this->desk(),
            new GuestCancelledBooking($booking->loadMissing(['user', 'cottage'])),
        ));
    }

    /**
     * One unread alert per owner per day carries the day's count: a later miss
     * updates it in place and moves it back to the top of the bell. It keeps
     * its id, so a link to it on a page that is already open still works.
     *
     * The count comes from the day's logged inquiries, not from the alert, so
     * it stays right after the owner reads the alert and when two misses land
     * at once. Call it after the inquiry has been logged.
     */
    public function chatbotMissed(): void
    {
        try {
            $misses = max(1, ChatInquiry::query()
                ->where('was_fallback', true)
                ->where('created_at', '>=', today())
                ->count());

            foreach ($this->owners() as $owner) {
                $today = $owner->unreadNotifications()
                    ->where('type', ChatbotUnanswered::class)
                    ->where('created_at', '>=', today())
                    ->first();

                if ($today === null) {
                    $this->send(new Collection([$owner]), new ChatbotUnanswered($misses));

                    continue;
                }

                // Never lower it: a request that counted earlier may write last.
                $count = max($misses, (int) ($today->data['count'] ?? 0));
                $today->forceFill([
                    'data' => (new ChatbotUnanswered($count))->toArray($owner),
                    'created_at' => now(),
                ])->save();
            }
        } catch (Throwable $e) {
            Log::error('Could not update the chatbot console alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Active front-desk staff and owners, minus whoever did the action.
     *
     * @return Collection<int, User>
     */
    private function desk(?User $except = null): Collection
    {
        return $this->team([UserRole::Staff, UserRole::Admin], $except);
    }

    /**
     * Active owners, minus whoever did the action.
     *
     * @return Collection<int, User>
     */
    private function owners(?User $except = null): Collection
    {
        return $this->team([UserRole::Admin], $except);
    }

    /**
     * @param  list<UserRole>  $roles
     * @return Collection<int, User>
     */
    private function team(array $roles, ?User $except): Collection
    {
        return User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }

    /**
     * Everything an alert needs — finding its recipients, loading what its
     * text mentions, storing it — runs in here, so a database hiccup is logged
     * instead of turning a committed booking or payment into an error page.
     */
    private function guard(string $kind, callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            Log::error('Could not create a console notification', [
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $to
     */
    private function send(Collection $to, ConsoleNotification $notification): void
    {
        if ($to->isNotEmpty()) {
            Notification::send($to, $notification);
        }
    }
}
