<?php

namespace App\Http\Controllers;

use App\Models\Cottage;
use App\Support\Money;
use Illuminate\View\View;

/**
 * Landing page. Shows live units and rates straight from the cottages table,
 * so the page never advertises a unit or price the resort doesn't have.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $cottages = Cottage::query()->active()->orderBy('price_per_night')->orderBy('name')->get();

        return view('welcome', [
            'cottages' => $cottages->take(5),
            'unitCount' => $cottages->count(),
            'fromRate' => $cottages->isEmpty() ? null : Money::format((string) $cottages->first()->price_per_night),
            'maxGuests' => $cottages->max('capacity'),
        ]);
    }
}
