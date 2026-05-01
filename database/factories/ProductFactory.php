<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $materials = [
            'Cemento Portland 50kg', 'Hierro Corrugado 12mm', 'Hierro Corrugado 8mm',
            'Arena Fina m3', 'Grava 3/4 m3', 'Ladrillo Hueco 12x18x33',
            'Bloque de Hormigon 20x20x40', 'Yeso en Polvo 25kg', 'Cal Hidratada 25kg',
            'Pintura Latex 20L', 'Pintura Esmalte 4L', 'Barniz Madera 1L',
            'Cable Electrico 2.5mm', 'Cable Electrico 4mm', 'Tubo PVC 20mm',
            'Tubo PVC 40mm', 'Codo PVC 90 20mm', 'Llave de Paso 1/2',
            'Llave de Paso 3/4', 'Inodoro Standard', 'Lavamanos Blanco',
            'Griferia Monocomando', 'Ducha Electrica', 'Terma Gas 10L',
            'Madera Pino 2x4', 'Madera Pino 1x6', 'Triplay 18mm',
            'Clavos 2" kg', 'Tornillos 1/4" kg', 'Alambre Galvanizado',
        ];

        $categories = [
            'Materiales Basicos', 'Hierro y Acero', 'Mamposteria',
            'Pinturas y Acabados', 'Electricidad', 'Plomeria',
            'Sanitarios', 'Madera y Tableros', 'Ferreteria'
        ];

        $material = $this->faker->randomElement($materials);
        $category = $this->faker->randomElement($categories);
        $cost = $this->faker->randomFloat(2, 5, 500);

        return [
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{4}'),
            'name' => $material,
            'description' => $this->faker->sentence(10),
            'category' => $category,
            'subcategory' => $this->faker->word(),
            'unit' => $this->faker->randomElement(['kg', 'unidad', 'm3', 'm2', 'litro', 'metro']),
            'cost_price' => $cost,
            'sale_price' => round($cost * $this->faker->randomFloat(2, 1.2, 2.0), 2),
            'stock_quantity' => $this->faker->numberBetween(0, 500),
            'min_stock' => $this->faker->numberBetween(5, 50),
            'max_stock' => $this->faker->numberBetween(100, 1000),
            'location' => $this->faker->randomElement(['A1', 'A2', 'B1', 'B2', 'C1', 'C2']),
            'supplier' => $this->faker->company(),
            'status' => $this->faker->randomElement(['active', 'discontinued', 'out_of_stock']),
        ];
    }
}
