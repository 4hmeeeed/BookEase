<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CarbonImmutable $check_in   first night (inclusive)
 * @property CarbonImmutable $check_out  departure day (exclusive)
 * @property string $total_price          2-decimal string (bcmath)
 * @property string $amount_paid          2-decimal string (bcmath)
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * status, payment_status, amount_paid, and reference_code are set by
     * services only; they're never taken from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cottage_id',
        'check_in',
        'check_out',
        'guests_count',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'amount_paid' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'guests_count' => 'integer',
            'total_price' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    /*
     | Dates are always stored as plain 'Y-m-d' strings (not 'Y-m-d H:i:s') so
     | the overlap comparison behaves identically on MySQL and SQLite.
     */
    protected function checkIn(): Attribute
    {
        return $this->dateOnly();
    }

    protected function checkOut(): Attribute
    {
        return $this->dateOnly();
    }

    private function dateOnly(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cottage(): BelongsTo
    {
        return $this->belongsTo(Cottage::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    /** total_price − amount_paid, never below zero. */
    public function balance(): string
    {
        return Money::max(Money::sub($this->total_price, $this->amount_paid), '0.00');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(BookingStatusChange::class)->latest('created_at')->latest('id');
    }

    /** Route-model binding for guest/staff URLs uses the public reference code. */
    public function getRouteKeyName(): string
    {
        return 'reference_code';
    }

    /**
     * A guest may cancel their own booking while it is pending or confirmed
     * and the check-in day hasn't arrived yet.
     */
    public function isCancellableByGuest(): bool
    {
        return in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)
            && CarbonImmutable::today()->lt($this->check_in);
    }

    /** Staff may check a confirmed guest in from the check-in day until the check-out day. */
    public function canCheckInToday(): bool
    {
        $today = CarbonImmutable::today();

        return $this->status === BookingStatus::Confirmed
            && $today->gte($this->check_in)
            && $today->lt($this->check_out);
    }

    public function scopeArrivingOn(Builder $query, string $date): void
    {
        $query->where('check_in', $date);
    }

    public function scopeDepartingOn(Builder $query, string $date): void
    {
        $query->where('check_out', $date);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('check_out', '>', CarbonImmutable::today()->toDateString());
    }

    /** Bookings that currently hold their dates. */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blockingValues());
    }

    /**
     * Half-open overlap with [$checkIn, $checkOut):
     * existing starts before requested end AND existing ends after requested start.
     */
    public function scopeOverlapping(Builder $query, string $checkIn, string $checkOut): void
    {
        $query->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
