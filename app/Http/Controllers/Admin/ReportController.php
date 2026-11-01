<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\ReportService;
use App\Support\Csv;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $report = $this->reports->build(ReportPeriod::fromRequest($request));

        return view('admin.reports.index', $report + ['series' => $this->reports->series($report['daily'])]);
    }

    /** Bookings that check in during the period. */
    public function bookingsCsv(Request $request): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);

        $rows = Booking::query()
            ->with(['user:id,name', 'cottage:id,name'])
            ->where('check_in', '>=', $period->from->toDateString())
            ->where('check_in', '<', $period->end()->toDateString())
            ->orderBy('check_in')
            ->lazy()
            ->map(fn (Booking $b) => [
                $b->reference_code,
                $b->user?->name,
                $b->cottage?->name,
                $b->check_in->toDateString(),
                $b->check_out->toDateString(),
                $b->nights(),
                $b->guests_count,
                $b->status->label(),
                Money::normalize($b->total_price),
                Money::normalize($b->amount_paid),
                $b->balance(),
                $b->payment_status->label(),
                $b->created_at?->format('Y-m-d H:i'),
            ]);

        return Csv::download(
            "bookease-bookings-{$period->from->toDateString()}-to-{$period->to->toDateString()}.csv",
            ['Reference', 'Guest', 'Unit', 'Check-in', 'Check-out', 'Nights', 'Guests', 'Status', 'Total (PHP)', 'Paid (PHP)', 'Balance (PHP)', 'Payment status', 'Requested at'],
            $rows,
        );
    }

    /** Settled payments and refunds dated in the period (refunds are negative). */
    public function paymentsCsv(Request $request): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);

        $rows = Payment::query()
            ->with(['booking:id,reference_code,user_id,cottage_id', 'booking.user:id,name', 'booking.cottage:id,name', 'recordedBy:id,name'])
            ->where('status', PaymentRecordStatus::Paid)
            ->where('paid_at', '>=', $period->from)
            ->where('paid_at', '<', $period->end())
            ->orderBy('paid_at')
            ->lazy()
            ->map(fn (Payment $p) => [
                $p->reference_no,
                $p->paid_at->format('Y-m-d H:i'),
                $p->booking?->reference_code,
                $p->booking?->user?->name,
                $p->booking?->cottage?->name,
                $p->method->label(),
                $p->kind->label(),
                $p->isRefund() ? Money::sub('0.00', $p->amount) : Money::normalize($p->amount),
                $p->recordedBy?->name ?? 'Payment gateway',
                $p->provider_payment_id,
            ]);

        return Csv::download(
            "bookease-payments-{$period->from->toDateString()}-to-{$period->to->toDateString()}.csv",
            ['Receipt no.', 'Paid at', 'Booking', 'Guest', 'Unit', 'Method', 'Type', 'Amount (PHP)', 'Recorded by', 'Gateway reference'],
            $rows,
        );
    }
}
