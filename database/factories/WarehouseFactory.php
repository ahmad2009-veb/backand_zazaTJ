<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Warehouse',
            'address' => $this->faker->address(),
            'phone' => '+992' . $this->faker->numerify('#########'),
            'responsible' => $this->faker->name(),
            'status' => true,
            'latitude' => (string) $this->faker->randomFloat(6, -90, 90),
            'longitude' => (string) $this->faker->randomFloat(6, -90, 90), // DB column has limited range
        ];
    }
}

