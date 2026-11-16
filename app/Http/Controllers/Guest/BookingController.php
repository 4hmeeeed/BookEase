<?php

namespace App\Http\Controllers\Guest;

use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingLifecycleService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private readonly BookingLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $bookings = $request->user()->bookings()
            ->with('cottage')
            ->orderByDesc('check_in')
            ->get();

        // A stay is current through its check-out day (check-out is at 12:00 NN).
        $today = CarbonImmutable::today();

        [$current, $past] = $bookings->partition(
            fn (Booking $b) => $b->status->holdsDates() && $b->check_out->gte($today)
        );

        return view('guest.bookings.index', [
            'current' => $current->sortBy('check_in')->values(),
            'past' => $past->values(),
        ]);
    }

    public function show(Booking $booking, PaymentService $payments): View
    {
        $this->authorize('view', $booking);

        $booking->load(['cottage', 'statusChanges', 'payments']);

        return view('guest.bookings.show', [
            'booking' => $booking,
            'paymentOptions' => $payments->onlineOptions($booking),
            'downPercent' => (int) config('bookease.payments.down_payment_percent'),
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);

        try {
            $this->lifecycle->cancel($booking, $request->user(), $data['remarks'] ?? 'Cancelled by guest.');
        } catch (InvalidBookingTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('guest.bookings.show', $booking)
            ->with('status', "Booking {$booking->reference_code} was cancelled and the dates were released.");
    }
}
