<?php

namespace App\Http\Controllers\Guest;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $next = $request->user()->bookings()
            ->with('cottage')
            ->whereIn('status', BookingStatus::blockingValues())
            ->where('check_out', '>', CarbonImmutable::today()->toDateString())
            ->orderBy('check_in')
            ->first();

        return view('guest.dashboard', [
            'next' => $next,
            'bookingCount' => $request->user()->bookings()->count(),
        ]);
    }
}
