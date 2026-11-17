<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 10:00:00');
        $this->admin = User::factory()->admin()->create();

        $guest = User::factory()->create(['name' => '=HYPERLINK("http://evil.test","Click")']);
        $this->booking = Booking::factory()->for(Cottage::factory()->create(['name' => 'Seaside Cottage']))->for($guest)
            ->stay('2026-11-14', '2026-11-16')
            ->create(['status' => BookingStatus::CheckedIn, 'total_price' => '6000.00', 'amount_paid' => '2500.00']);

        Payment::factory()->for($this->booking)->create([
            'method' => PaymentMethod::GCash, 'kind' => PaymentKind::Down, 'amount' => '3000.00',
            'status' => PaymentRecordStatus::Paid, 'paid_at' => '2026-11-14 15:00:00', 'reference_no' => 'OR-20261114-AAAAAAAA',
        ]);
        Payment::factory()->for($this->booking)->create([
            'method' => PaymentMethod::Cash, 'kind' => PaymentKind::Refund, 'amount' => '500.00',
            'status' => PaymentRecordStatus::Paid, 'paid_at' => '2026-11-15 09:00:00', 'recorded_by' => $this->admin->id,
            'reference_no' => 'OR-20261115-BBBBBBBB',
        ]);
    }

    public function test_only_the_owner_can_open_reports_and_exports(): void
    {
        $urls = ['/admin/reports', '/admin/reports/bookings.csv', '/admin/reports/payments.csv'];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/staff/login');
        }

        foreach ([User::factory()->create(), User::factory()->staff()->create()] as $user) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_report_page_shows_the_figures(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/reports?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertSee('Nov 1 – Nov 30, 2026')
            ->assertSee('₱2,500.00')            // collected net: 3,000 − 500
            ->assertSee('₱3,500.00')            // outstanding: 6,000 − 2,500
            ->assertSee('6.7%')                 // 2 of 30 unit-nights
            ->assertSee('Seaside Cottage')
            ->assertSee($this->booking->reference_code)
            ->assertSee('How these numbers are counted');
    }

    public function test_presets_and_long_ranges_render(): void
    {
        foreach (['this_month', 'last_month', 'last_30', 'next_30', 'this_year', 'nonsense'] as $preset) {
            $this->actingAs($this->admin)->get("/admin/reports?period={$preset}")->assertOk();
        }
    }

    public function test_bookings_csv(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/bookings.csv?from=2026-11-01&to=2026-11-30');

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('bookease-bookings-2026-11-01-to-2026-11-30.csv', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Reference,Guest,Unit,Check-in', $csv);
        $this->assertStringContainsString($this->booking->reference_code, $csv);
        $this->assertStringContainsString('6000.00,2500.00,3500.00', $csv);
    }

    public function test_csv_neutralises_spreadsheet_formulas(): void
    {
        $csv = $this->actingAs($this->admin)->get('/admin/reports/bookings.csv?from=2026-11-01&to=2026-11-30')->streamedContent();

        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);
    }

    public function test_payments_csv_lists_refunds_as_negative(): void
    {
        $csv = $this->actingAs($this->admin)->get('/admin/reports/payments.csv?from=2026-11-01&to=2026-11-30')->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("
", trim(substr($csv, 3)))));

        $this->assertSame(['Receipt no.', 'Paid at', 'Booking', 'Guest', 'Unit', 'Method', 'Type', 'Amount (PHP)', 'Recorded by', 'Gateway reference'], $rows[0]);
        $this->assertCount(3, $rows);

        [$header, $down, $refund] = $rows;
        $this->assertSame(['OR-20261114-AAAAAAAA', '2026-11-14 15:00', $this->booking->reference_code], array_slice($down, 0, 3));
        $this->assertSame('3000.00', $down[7]);
        $this->assertSame('Payment gateway', $down[8]);
        $this->assertSame('-500.00', $refund[7]);
        $this->assertSame($this->admin->name, $refund[8]);
    }

    public function test_owner_dashboard_shows_live_figures(): void
    {
        Booking::factory()->create(['status' => BookingStatus::Pending]);

        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSee('Needs attention')
            ->assertSee('waiting for approval')
            ->assertSee('₱3,500.00')    // still owed (in-house booking)
            ->assertSee('₱2,500.00')    // collected this month
            ->assertSee(route('admin.staff.index'))
            ->assertSee(route('admin.reports.index'))
            ->assertSee(route('admin.chatbot.index'));
    }
}
