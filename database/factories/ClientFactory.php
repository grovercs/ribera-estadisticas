<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company' => $this->faker->company(),
            'tax_id' => $this->faker->unique()->numerify('B-########'),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'type' => $this->faker->randomElement(['individual', 'business']),
            'credit_limit' => $this->faker->randomFloat(2, 1000, 50000),
            'total_spent' => $this->faker->randomFloat(2, 0, 100000),
            'order_count' => $this->faker->numberBetween(0, 200),
            'last_order_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
