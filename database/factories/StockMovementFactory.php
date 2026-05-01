<?php

namespace Database\Factories;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $types = ['in', 'out', 'adjustment'];
        $type = $this->faker->randomElement($types);
        $quantity = $this->faker->numberBetween(1, 100);
        $stockBefore = $this->faker->numberBetween(50, 500);
        $stockAfter = $type === 'in' ? $stockBefore + $quantity : max(0, $stockBefore - $quantity);

        return [
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference' => $this->faker->optional()->regexify('REF-[A-Z0-9]{8}'),
            'notes' => $this->faker->optional()->sentence(8),
        ];
    }
}
