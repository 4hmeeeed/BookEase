<?php

namespace App\Http\Controllers;

use App\Enums\CottageType;
use App\Models\Cottage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public cottage browsing. Date-based availability filtering is added in
 * step 3 (AvailabilityService); for now guests filter by party size and type.
 */
class CottageController extends Controller
{
    public function index(Request $request): View
    {
        // Public GET filters: silently ignore junk instead of redirecting.
        $guests = filter_var($request->query('guests'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]) ?: null;
        $type = CottageType::tryFrom((string) $request->query('type', ''));

        $cottages = Cottage::query()
            ->active()
            ->forGuests($guests)
            ->ofType($type)
            ->orderBy('price_per_night')
            ->orderBy('name')
            ->get();

        return view('cottages.index', [
            'cottages' => $cottages,
            'guests' => $guests,
            'type' => $type,
            'types' => CottageType::options(),
        ]);
    }

    public function show(Cottage $cottage): View
    {
        abort_unless($cottage->is_active, 404);

        return view('cottages.show', [
            'cottage' => $cottage,
            'others' => Cottage::active()
                ->whereKeyNot($cottage->getKey())
                ->orderBy('price_per_night')
                ->limit(3)
                ->get(),
        ]);
    }
}
