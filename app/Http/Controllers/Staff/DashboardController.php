<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = CarbonImmutable::today()->toDateString();

        $arrivals = Booking::with(['cottage', 'user'])
            ->where('status', BookingStatus::Confirmed)
            ->where('check_in', '<=', $today)
            ->where('check_out', '>', $today)
            ->orderBy('check_in')
            ->get();

        $departures = Booking::with(['cottage', 'user'])
            ->where('status', BookingStatus::CheckedIn)
            ->where('check_out', '<=', $today)
            ->orderBy('check_out')
            ->get();

        $pending = Booking::with(['cottage', 'user'])
            ->where('status', BookingStatus::Pending)
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        return view('staff.dashboard', [
            'arrivals' => $arrivals,
            'departures' => $departures,
            'pending' => $pending,
            'stats' => [
                'pending' => Booking::where('status', BookingStatus::Pending)->count(),
                'arrivals' => $arrivals->count(),
                'in_house' => Booking::where('status', BookingStatus::CheckedIn)->count(),
                'departures' => $departures->count(),
            ],
        ]);
    }
}
