<?php

namespace App\Http\Controllers;

use App\Enums\CottageType;
use App\Exceptions\InvalidStayException;
use App\Models\Cottage;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public cottage browsing: by stay dates, party size, and type.
 */
class CottageController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function index(Request $request): View
    {
        // Public GET filters: silently ignore junk instead of redirecting.
        $guests = filter_var($request->query('guests'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]) ?: null;
        $type = CottageType::tryFrom((string) $request->query('type', ''));

        // Dates are optional; when given they must form a valid stay.
        $stay = null;
        $stayError = null;

        if ($request->filled('check_in') || $request->filled('check_out')) {
            try {
                $stay = $this->availability->validateStay($request->query('check_in'), $request->query('check_out'));
            } catch (InvalidStayException $e) {
                $stayError = $e->getMessage();
            }
        }

        $query = $stay
            ? $this->availability->availableCottages($stay[0], $stay[1], $guests)
            : Cottage::query()->active()->forGuests($guests);

        $cottages = $query
            ->ofType($type)
            ->orderBy('price_per_night')
            ->orderBy('name')
            ->get();

        // Server-side (bcmath) totals for the chosen stay.
        $quotes = $stay
            ? $cottages->mapWithKeys(fn (Cottage $c) => [$c->id => $this->availability->quote($c, $stay[0], $stay[1])])
            : collect();

        return view('cottages.index', [
            'cottages' => $cottages,
            'guests' => $guests,
            'type' => $type,
            'types' => CottageType::options(),
            'checkIn' => $stay[0] ?? null,
            'checkOut' => $stay[1] ?? null,
            'rawCheckIn' => $request->query('check_in'),
            'rawCheckOut' => $request->query('check_out'),
            'nights' => $stay ? $this->availability->nights($stay[0], $stay[1]) : null,
            'quotes' => $quotes,
            'stayError' => $stayError,
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function show(Request $request, Cottage $cottage): View
    {
        abort_unless($cottage->is_active, 404);

        return view('cottages.show', [
            'cottage' => $cottage,
            'calendar' => [
                'availabilityUrl' => route('cottages.availability', $cottage),
                'quoteUrl' => route('cottages.quote', $cottage),
                'bookUrl' => route('guest.reservations.create', $cottage),
                'canBook' => ! $request->user() || $request->user()->isGuest(),
                'today' => CarbonImmutable::today()->toDateString(),
                'maxNights' => (int) config('bookease.max_nights'),
                'windowDays' => (int) config('bookease.booking_window_days'),
                'minAdvanceDays' => (int) config('bookease.min_advance_days'),
                'checkIn' => $this->dateParam($request->query('check_in')),
                'checkOut' => $this->dateParam($request->query('check_out')),
            ],
            'others' => Cottage::active()
                ->whereKeyNot($cottage->getKey())
                ->orderBy('price_per_night')
                ->limit(3)
                ->get(),
        ]);
    }

    private function dateParam(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
