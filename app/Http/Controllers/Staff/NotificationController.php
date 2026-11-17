<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\ConsoleAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The console bell. Every query goes through the signed-in user's own
 * notifications, so nobody can read or clear someone else's.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $all = $request->query('filter') === 'all';

        $alerts = ($all ? $user->notifications() : $user->unreadNotifications())
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => ConsoleAlert::from($n));

        return view('staff.notifications.index', [
            'alerts' => $alerts,
            'filter' => $all ? 'all' : 'unread',
            'counts' => [
                'unread' => $user->unreadNotifications()->count(),
                'all' => $user->notifications()->count(),
            ],
        ]);
    }

    /** What the bell checks every 30 seconds: kept to two small queries. */
    public function poll(Request $request): JsonResponse
    {
        $newest = $request->user()->unreadNotifications()->first(['id', 'created_at']);

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            // Changes when an alert arrives or the day's chatbot alert is updated in place.
            'newest' => $newest ? $newest->id.'@'.$newest->created_at->getTimestamp() : null,
        ]);
    }

    /** The dropdown: the eight most recent alerts, read or not. */
    public function panel(Request $request): View
    {
        return view('staff.notifications._panel', [
            'alerts' => $request->user()->notifications()->limit(8)->get()
                ->map(fn (DatabaseNotification $n) => ConsoleAlert::from($n)),
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $found */
        $found = $request->user()->notifications()->findOrFail($notification);
        $found->markAsRead();

        $alert = ConsoleAlert::from($found);

        if ($alert->reference !== null && ! Booking::where('reference_code', $alert->reference)->exists()) {
            return redirect()->route('staff.bookings.index')->with('status', 'That booking is no longer available.');
        }

        return redirect()->to($alert->targetUrl);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back(302, [], route('staff.notifications.index'))->with('status', 'All caught up.');
    }
}
