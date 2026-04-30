<?php

namespace Database\Seeders;

use App\Models\DeliveryType;
use Illuminate\Database\Seeder;

class DeliveryTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DeliveryType::query()->firstOrCreate(
            ['name' => 'standard'],
            ['value' => 15]
        );

        DeliveryType::query()->firstOrCreate(
            ['name' => 'express'],
            ['value' => 30]
        );
    }
}
