<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityFeed;
use App\Services\AttentionService;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, AttentionService $attention, ActivityFeed $activity): View
    {
        $month = $reports->build(ReportPeriod::preset('this_month'));
        $last30 = ReportPeriod::preset('last_30');

        return view('admin.dashboard', [
            'attention' => $attention->items($request->user()),
            'today' => $reports->today(),
            'month' => $month,
            'trend' => $reports->build($last30)['daily'],
            'activity' => $activity->latest(10),
        ]);
    }
}
