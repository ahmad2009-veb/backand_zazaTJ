<?php

namespace Database\Seeders;

use App\Models\LoyaltyPoint;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoyaltyPointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        LoyaltyPoint::query()->create([
            'points' => 100,
            'value' => 1,
            'expires_at' => now()->addMonths(3),
        ]);
    }
}
