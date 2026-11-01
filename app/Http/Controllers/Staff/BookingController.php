<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BookingDecisionRequest;
use App\Models\Booking;
use App\Services\BookingLifecycleService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    /** Queue tabs: key => [label, query filter] */
    private const TABS = ['pending', 'arrivals', 'in_house', 'departures', 'upcoming', 'history', 'all'];

    public function __construct(private readonly BookingLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pending';
        $search = trim((string) $request->query('q'));

        $bookings = $this->tabQuery($tab)
            ->with(['cottage', 'user'])
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $q) use ($search) {
                    $q->where('reference_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->paginate(15)
            ->withQueryString();

        return view('staff.bookings.index', [
            'bookings' => $bookings,
            'tab' => $tab,
            'search' => $search,
            'counts' => collect(self::TABS)->mapWithKeys(fn ($t) => [$t => $this->tabQuery($t)->count()]),
        ]);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('manage', $booking);

        $booking->load(['cottage', 'user', 'statusChanges.user']);

        return view('staff.bookings.show', ['booking' => $booking]);
    }

    public function approve(BookingDecisionRequest $request, Booking $booking): RedirectResponse
    {
        return $this->act($booking, fn () => $this->lifecycle->approve($booking, $request->user(), $request->validated('remarks')), 'confirmed');
    }

    public function reject(BookingDecisionRequest $request, Booking $booking): RedirectResponse
    {
        return $this->act($booking, fn () => $this->lifecycle->reject($booking, $request->user(), $request->validated('remarks')), 'rejected, and its dates were released');
    }

    public function checkIn(BookingDecisionRequest $request, Booking $booking): RedirectResponse
    {
        return $this->act($booking, fn () => $this->lifecycle->checkIn($booking, $request->user(), $request->validated('remarks')), 'checked in');
    }

    public function checkOut(BookingDecisionRequest $request, Booking $booking): RedirectResponse
    {
        return $this->act($booking, fn () => $this->lifecycle->checkOut($booking, $request->user(), $request->validated('remarks')), 'checked out');
    }

    public function cancel(BookingDecisionRequest $request, Booking $booking): RedirectResponse
    {
        return $this->act($booking, fn () => $this->lifecycle->cancel($booking, $request->user(), $request->validated('remarks')), 'cancelled, and its dates were released');
    }

    private function act(Booking $booking, Closure $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidBookingTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Booking {$booking->reference_code} was {$done}.");
    }

    /**
     * @return Builder<Booking>
     */
    private function tabQuery(string $tab): Builder
    {
        $today = CarbonImmutable::today()->toDateString();
        $q = Booking::query();

        return match ($tab) {
            'pending' => $q->where('status', BookingStatus::Pending)->orderBy('created_at'),
            'arrivals' => $q->where('status', BookingStatus::Confirmed)->where('check_in', '<=', $today)->where('check_out', '>', $today)->orderBy('check_in'),
            'in_house' => $q->where('status', BookingStatus::CheckedIn)->orderBy('check_out'),
            'departures' => $q->where('status', BookingStatus::CheckedIn)->where('check_out', '<=', $today)->orderBy('check_out'),
            'upcoming' => $q->where('status', BookingStatus::Confirmed)->where('check_in', '>', $today)->orderBy('check_in'),
            'history' => $q->whereIn('status', [BookingStatus::Rejected, BookingStatus::Cancelled, BookingStatus::CheckedOut])->orderByDesc('updated_at'),
            default => $q->orderByDesc('created_at'),
        };
    }
}
