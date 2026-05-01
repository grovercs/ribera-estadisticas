<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\Alert;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario admin
        User::factory()->create([
            'name' => 'Admin Ribera',
            'email' => 'admin@ribera.com',
            'password' => bcrypt('password'),
        ]);

        // Clientes
        $clients = Client::factory(20)->create();

        // Productos
        $products = Product::factory(30)->create();

        // Órdenes con items
        $orders = Order::factory(50)->create();

        foreach ($orders as $order) {
            $itemsCount = rand(1, 5);
            for ($i = 0; $i < $itemsCount; $i++) {
                $product = $products->random();
                $quantity = rand(1, 20);
                $unitPrice = $product->sale_price;
                $total = round($quantity * $unitPrice, 2);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'total' => $total,
                ]);
            }

            // Actualizar totales de la orden
            $items = $order->items;
            $subtotal = $items->sum('total');
            $tax = round($subtotal * 0.18, 2);
            $total = round($subtotal + $tax, 2);

            $order->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);
        }

        // Movimientos de stock
        foreach ($products->take(15) as $product) {
            StockMovement::factory(rand(1, 5))->create([
                'product_id' => $product->id,
            ]);
        }

        // Alertas de ejemplo
        $lowStockProducts = $products->where('stock_quantity', '<=', 20)->take(5);
        foreach ($lowStockProducts as $product) {
            Alert::create([
                'type' => 'low_stock',
                'product_id' => $product->id,
                'title' => 'Stock bajo: ' . $product->name,
                'description' => 'El producto tiene solo ' . $product->stock_quantity . ' unidades (mínimo: ' . $product->min_stock . ')',
                'severity' => $product->stock_quantity <= 10 ? 'critical' : 'warning',
                'status' => 'active',
            ]);
        }

        $this->command->info('Datos de prueba creados exitosamente!');
    }
}
