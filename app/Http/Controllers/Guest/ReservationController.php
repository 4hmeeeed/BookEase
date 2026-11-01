<?php

namespace App\Http\Controllers\Guest;

use App\Exceptions\DatesUnavailableException;
use App\Exceptions\InvalidStayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Cottage;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
    ) {}

    /**
     * GET /cottages/{cottage}/book?check_in=…&check_out=…
     */
    public function create(Request $request, Cottage $cottage): View|RedirectResponse
    {
        abort_unless($cottage->is_active, 404);
        $this->authorize('create', Booking::class);

        try {
            [$in, $out] = $this->availability->validateStay($request->query('check_in'), $request->query('check_out'));
        } catch (InvalidStayException $e) {
            return redirect()->route('cottages.show', $cottage)->with('error', $e->getMessage());
        }

        if (! $this->availability->isAvailable($cottage, $in, $out)) {
            return redirect()->route('cottages.show', $cottage)
                ->with('error', 'Those dates were just taken. Please pick different dates.');
        }

        return view('guest.reservations.create', [
            'cottage' => $cottage,
            'checkIn' => $in,
            'checkOut' => $out,
            'nights' => $this->availability->nights($in, $out),
            'total' => $this->availability->quote($cottage, $in, $out),
            'user' => $request->user(),
        ]);
    }

    public function store(StoreBookingRequest $request, Cottage $cottage): RedirectResponse
    {
        try {
            $booking = $this->bookings->create($request->user(), $cottage, $request->validated());
        } catch (DatesUnavailableException|InvalidStayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('guest.bookings.show', $booking)
            ->with('status', "Reservation {$booking->reference_code} received! It's pending until the resort confirms it.");
    }
}
