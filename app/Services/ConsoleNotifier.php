<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Console\ConsoleNotification;
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
        $this->send($this->desk(), new NewBookingRequest($booking));
    }

    public function paymentReceived(Payment $payment): void
    {
        $this->send($this->desk(), new OnlinePaymentReceived($payment));
    }

    public function paymentNeedsReview(Payment $payment): void
    {
        $this->send($this->desk(), new PaymentNeedsReview($payment));
    }

    /** Cash and refunds are entered by the desk itself, so only the owners hear about them. */
    public function moneyRecorded(Payment $payment, User $actor): void
    {
        $this->send($this->owners($actor), new MoneyRecordedByStaff($payment));
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
     * @param  Collection<int, User>  $to
     */
    private function send(Collection $to, ConsoleNotification $notification): void
    {
        if ($to->isEmpty()) {
            return;
        }

        try {
            Notification::send($to, $notification);
        } catch (Throwable $e) {
            Log::error('Could not create a console notification', [
                'kind' => $notification->kind(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
