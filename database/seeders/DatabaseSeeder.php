<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Scenario seeders — each is self-contained (creates its own units/items/products).
     * Set DB_SEED_SCENARIO in .env (or prefix the artisan command) to run one instead
     * of the full dataset.
     *
     * Available scenarios:
     *   carryover  — 1 product, ingredients split across multiple batches; tests the
     *                carry-over depletion mechanic and carryoverPlan UI preview.
     *   shortage   — 1 product, one ingredient is deliberately short; tests the
     *                ValidationException path in ProductionController.
     *
     * Examples:
     *   DB_SEED_SCENARIO=carryover php artisan migrate:fresh --seed
     *   php artisan db:seed --class="Database\Seeders\Scenarios\CarryoverScenarioSeeder"
     */
    private const SCENARIOS = [
        'carryover' => Scenarios\CarryoverScenarioSeeder::class,
        'shortage'  => Scenarios\ShortageScenarioSeeder::class,
    ];

    public function run(): void
    {
        $scenario = env('DB_SEED_SCENARIO');

        if ($scenario) {
            if (!isset(self::SCENARIOS[$scenario])) {
                $this->command->error(
                    "Unknown scenario '{$scenario}'. Available: " . implode(', ', array_keys(self::SCENARIOS))
                );
                return;
            }

            $this->call(self::SCENARIOS[$scenario]);
            return;
        }

        // Default: full development dataset
        $this->call([
            UnitSeeder::class,
            ItemSeeder::class,
            RestockBatchSeeder::class,
            ProductSeeder::class,
            ProductIngredientSeeder::class,
        ]);
    }
}
