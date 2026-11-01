<?php

namespace Database\Factories;

use App\Enums\CottageType;
use App\Models\Cottage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cottage>
 */
class CottageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)).' Cottage',
            'type' => fake()->randomElement(CottageType::cases()),
            'capacity' => fake()->numberBetween(2, 12),
            'price_per_night' => fake()->numberBetween(8, 60).'00.00',
            'description' => fake()->sentence(14),
            'is_active' => true,
        ];
    }

    public function delisted(): static
    {
        return $this->state(['is_active' => false]);
    }
}
