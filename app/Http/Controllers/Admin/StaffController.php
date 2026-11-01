<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staffService) {}

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : 'all';
        $search = trim((string) $request->query('q', ''));
        $base = User::where('role', UserRole::Staff);

        $staff = (clone $base)
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount(['recordedPayments', 'bookingDecisions'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.staff.index', [
            'staff' => $staff,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'all' => (clone $base)->count(),
                'active' => (clone $base)->where('is_active', true)->count(),
                'inactive' => (clone $base)->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.create', ['member' => new User]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $member = $this->staffService->create($request->validated());

        return redirect()->route('admin.staff.index')
            ->with('status', "{$member->name} can now sign in as front-desk staff.");
    }

    public function edit(User $staff): View
    {
        $this->ensureStaff($staff);

        return view('admin.staff.edit', ['member' => $staff]);
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $this->staffService->update($staff, $request->validated());

        return redirect()->route('admin.staff.index')
            ->with('status', filled($request->validated('password'))
                ? "Saved. {$staff->name}’s password was changed and they were signed out everywhere."
                : "Saved {$staff->name}’s details.");
    }

    public function toggle(User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $this->staffService->setActive($staff, ! $staff->is_active);

        return back()->with('status', $staff->is_active
            ? "{$staff->name} can sign in again."
            : "{$staff->name} was deactivated and signed out.");
    }

    /** Guests and the owner are never edited through this screen. */
    private function ensureStaff(User $user): void
    {
        abort_unless($user->isStaff(), 404);
    }
}
