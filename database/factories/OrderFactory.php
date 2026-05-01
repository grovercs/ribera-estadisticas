<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 50, 5000);
        $tax = round($subtotal * 0.18, 2);
        $discount = $this->faker->randomFloat(2, 0, $subtotal * 0.1);
        $total = round($subtotal + $tax - $discount, 2);

        return [
            'order_number' => $this->faker->unique()->regexify('ORD-[0-9]{6}'),
            'client_id' => Client::factory(),
            'user_id' => null,
            'order_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'delivery_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'partial', 'overdue']),
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'transfer', 'credit']),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'notes' => $this->faker->optional()->sentence(15),
            'shipping_address' => $this->faker->address(),
            'tracking_number' => $this->faker->optional()->regexify('TRK-[A-Z0-9]{10}'),
        ];
    }
}
