<?php

namespace App\View\Composers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ChatInquiry;
use App\Support\ConsoleAlert;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Navigation for the staff/owner console: the sidebar groups, the phone tab
 * bar, the "More" sheet, and the small counts shown beside them.
 */
class ConsoleComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        $owner = $user->isAdmin();

        $pending = Booking::where('status', BookingStatus::Pending)->count();
        $unread = $user->unreadNotifications()->count();
        $unanswered = $owner
            ? ChatInquiry::where('was_fallback', true)->where('created_at', '>=', now()->subDays(7))->count()
            : 0;

        $overview = $this->link('admin.dashboard', 'Overview', 'admin.dashboard', 'overview');
        $frontDesk = $this->link('staff.dashboard', 'Front desk', 'staff.dashboard', 'desk');
        $bookings = $this->link('staff.bookings.index', 'Bookings', 'staff.bookings.*', 'bookings', $pending);
        $cottages = $this->link('admin.cottages.index', 'Cottages', 'admin.cottages.*', 'cottages');
        $staff = $this->link('admin.staff.index', 'Staff', 'admin.staff.*', 'staff');
        $reports = $this->link('admin.reports.index', 'Reports', 'admin.reports.*', 'reports');
        $chatbot = $this->link('admin.chatbot.index', 'Chatbot log', 'admin.chatbot.*', 'chatbot', $unanswered);
        $alerts = $this->link('staff.notifications.index', 'Alerts', 'staff.notifications.*', 'bell', $unread);

        $view->with('console', [
            'home' => $owner ? 'admin.dashboard' : 'staff.dashboard',
            'roleLabel' => $owner ? 'Owner' : 'Front desk',
            'pending' => $pending,
            'unread' => $unread,
            'newest' => ConsoleAlert::newestMarker($user),
            'groups' => $owner
                ? [
                    ['label' => null, 'links' => [$overview]],
                    ['label' => 'Today', 'links' => [$frontDesk, $bookings]],
                    ['label' => 'Manage', 'links' => [$cottages, $staff]],
                    ['label' => 'Insights', 'links' => [$reports, $chatbot]],
                ]
                : [
                    ['label' => 'Today', 'links' => [$frontDesk, $bookings]],
                ],
            // Phones: three tabs plus "More" (the sheet holds the rest).
            'tabs' => $owner ? [$overview, $bookings, $alerts] : [$frontDesk, $bookings, $alerts],
            'more' => $owner ? [$frontDesk, $cottages, $staff, $reports, $chatbot] : [],
        ]);
    }

    /**
     * @return array{route: string, label: string, pattern: string, icon: string, badge: ?int}
     */
    private function link(string $route, string $label, string $pattern, string $icon, ?int $badge = null): array
    {
        return compact('route', 'label', 'pattern', 'icon', 'badge');
    }
}
