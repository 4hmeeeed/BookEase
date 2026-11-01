<?php

namespace App\Models;

use App\Enums\CottageType;
use App\Support\Money;
use Database\Factories\CottageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A bookable unit: cottage, room, villa, or cabana.
 *
 * @property string $price_per_night 2-decimal numeric string (use App\Support\Money)
 */
class Cottage extends Model
{
    /** @use HasFactory<CottageFactory> */
    use HasFactory;

    /**
     * `image_path` is set only by CottageService after a validated upload.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'capacity',
        'price_per_night',
        'description',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CottageType::class,
            'capacity' => 'integer',
            'price_per_night' => 'decimal:2', // stays a string → safe for bcmath
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Only listed (not delisted) units. */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Units that can hold at least this many guests. */
    public function scopeForGuests(Builder $query, ?int $guests): void
    {
        $query->when($guests, fn (Builder $q) => $q->where('capacity', '>=', $guests));
    }

    public function scopeOfType(Builder $query, ?CottageType $type): void
    {
        $query->when($type, fn (Builder $q) => $q->where('type', $type->value));
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null);
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn () => Money::format($this->price_per_night));
    }
}
