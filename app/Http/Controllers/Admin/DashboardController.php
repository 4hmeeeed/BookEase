<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ChatInquiry;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        $month = $reports->build(ReportPeriod::preset('this_month'));
        $last30 = ReportPeriod::preset('last_30');

        return view('admin.dashboard', [
            'today' => $reports->today(),
            'month' => $month,
            'trend' => $reports->build($last30)['daily'],
            'staffCount' => User::where('role', UserRole::Staff)->where('is_active', true)->count(),
            'chat' => [
                'week' => ChatInquiry::where('created_at', '>=', now()->subDays(7))->count(),
                'fallback' => ChatInquiry::where('created_at', '>=', now()->subDays(7))->where('was_fallback', true)->count(),
            ],
        ]);
    }
}
