<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CottageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CottageRequest;
use App\Models\Cottage;
use App\Services\CottageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CottageController extends Controller
{
    public function __construct(private readonly CottageService $cottages) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $cottages = Cottage::query()
            ->when($status === 'listed', fn ($q) => $q->where('is_active', true))
            ->when($status === 'delisted', fn ($q) => $q->where('is_active', false))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.cottages.index', [
            'cottages' => $cottages,
            'status' => $status,
            'counts' => [
                'all' => Cottage::count(),
                'listed' => Cottage::where('is_active', true)->count(),
                'delisted' => Cottage::where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.cottages.create', [
            'cottage' => new Cottage(['type' => CottageType::Cottage, 'capacity' => 2, 'is_active' => true]),
            'types' => CottageType::options(),
        ]);
    }

    public function store(CottageRequest $request): RedirectResponse
    {
        $cottage = $this->cottages->create($request->validated(), $request->file('image'));

        return redirect()->route('admin.cottages.index')
            ->with('status', "“{$cottage->name}” was added.");
    }

    public function edit(Cottage $cottage): View
    {
        return view('admin.cottages.edit', [
            'cottage' => $cottage,
            'types' => CottageType::options(),
        ]);
    }

    public function update(CottageRequest $request, Cottage $cottage): RedirectResponse
    {
        $this->cottages->update(
            $cottage,
            $request->validated(),
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return redirect()->route('admin.cottages.index')
            ->with('status', "“{$cottage->name}” was updated.");
    }

    public function toggle(Cottage $cottage): RedirectResponse
    {
        $this->cottages->setListed($cottage, ! $cottage->is_active);

        return back()->with('status', $cottage->is_active
            ? "“{$cottage->name}” is listed again."
            : "“{$cottage->name}” was delisted and is hidden from guests.");
    }

    public function destroy(Cottage $cottage): RedirectResponse
    {
        try {
            $this->cottages->delete($cottage);
        } catch (QueryException) {
            // Foreign-key restriction: the unit has bookings.
            return back()->with('error', "“{$cottage->name}” has bookings and can't be deleted. Delist it instead.");
        }

        return redirect()->route('admin.cottages.index')
            ->with('status', "“{$cottage->name}” was deleted.");
    }
}
