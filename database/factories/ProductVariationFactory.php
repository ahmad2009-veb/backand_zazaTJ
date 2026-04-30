<?php

namespace Database\Factories;

use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariationFactory extends Factory
{
    protected $model = ProductVariation::class;

    public function definition(): array
    {
        $weight = $this->faker->randomElement(['2kg', '5kg', '7kg', '10kg']);
        $timestamp = now()->timestamp . $this->faker->randomNumber(6);
        $index = $this->faker->randomNumber(1);

        return [
            'variation_id' => "{$weight}_{$timestamp}_{$index}",
            'attribute_id' => 10,
            'attribute_value' => str_replace('kg', '000G', $weight),
            'cost_price' => $this->faker->numberBetween(50, 200),
            'sale_price' => $this->faker->numberBetween(100, 300),
            'quantity' => $this->faker->numberBetween(10, 100),
            'barcode' => 'BAR-' . $this->faker->numerify('####'),
        ];
    }
}

