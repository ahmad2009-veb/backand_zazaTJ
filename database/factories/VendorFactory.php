<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'f_name' => $this->faker->firstName(),
            'l_name' => $this->faker->lastName(),
            'phone' => '+992' . $this->faker->numerify('#########'),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'status' => 1,
        ];
    }
}

