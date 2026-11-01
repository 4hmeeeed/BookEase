<?php

namespace Database\Seeders;

use App\Enums\CottageType;
use App\Models\Cottage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * ⚠ PLACEHOLDER DATA FOR DEVELOPMENT ONLY.
 *
 * These names, capacities, and rates are NOT Labnasan Resort's real units or
 * prices. Replace them with the owner's actual list (or enter them through
 * Admin → Cottages) before any demo or evaluation, because the chatbot will
 * quote whatever is in this table.
 */
class CottageSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            $this->command?->warn('CottageSeeder skipped in production.');

            return;
        }

        $units = [
            ['[Sample] Seaside Cottage A', CottageType::Cottage, 4, '1500.00', 'Open-air beachfront cottage with a table and benches, steps from the shore.'],
            ['[Sample] Seaside Cottage B', CottageType::Cottage, 6, '2000.00', 'Larger beachfront cottage for families, shaded by coconut palms.'],
            ['[Sample] Garden Room', CottageType::Room, 2, '2500.00', 'Air-conditioned room with a private bathroom, facing the garden.'],
            ['[Sample] Family Villa', CottageType::Villa, 10, '6500.00', 'Two-bedroom villa with a kitchen and veranda, good for big groups.'],
            ['[Sample] Sunset Cabana', CottageType::Cabana, 3, '1200.00', 'Small cabana on the sand, best for watching the sunset.'],
        ];

        foreach ($units as [$name, $type, $capacity, $rate, $description]) {
            Cottage::updateOrCreate(['name' => $name], [
                'type' => $type,
                'capacity' => $capacity,
                'price_per_night' => $rate,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }
}
