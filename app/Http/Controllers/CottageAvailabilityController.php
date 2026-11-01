<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidStayException;
use App\Models\Cottage;
use App\Services\AvailabilityService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints behind the availability calendar. They expose which nights
 * are taken (never who booked them) and a server-computed price.
 */
class CottageAvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * GET /cottages/{cottage}/availability?month=2026-12
     */
    public function month(Request $request, Cottage $cottage): JsonResponse
    {
        abort_unless($cottage->is_active, 404);

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->query('month'))
            : CarbonImmutable::today()->startOfMonth();

        $from = $month->startOfMonth();
        $to = $from->addMonth();

        return response()->json([
            'month' => $from->format('Y-m'),
            'booked' => $this->availability->bookedNights($cottage, $from, $to),
        ]);
    }

    /**
     * GET /cottages/{cottage}/quote?check_in=2026-12-10&check_out=2026-12-13
     */
    public function quote(Request $request, Cottage $cottage): JsonResponse
    {
        abort_unless($cottage->is_active, 404);

        try {
            [$in, $out] = $this->availability->validateStay(
                $request->query('check_in'),
                $request->query('check_out'),
            );
        } catch (InvalidStayException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $available = $this->availability->isAvailable($cottage, $in, $out);
        $total = $this->availability->quote($cottage, $in, $out);

        return response()->json([
            'ok' => true,
            'available' => $available,
            'check_in' => $in->toDateString(),
            'check_out' => $out->toDateString(),
            'nights' => $this->availability->nights($in, $out),
            'rate' => Money::format($cottage->price_per_night),
            'total' => $total,
            'total_formatted' => Money::format($total),
            'message' => $available ? null : 'Some of those nights are already taken.',
        ]);
    }
}
