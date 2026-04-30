<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $stores = ['Adidas', 'Nike', 'Lining', 'Relax', 'Reebok'];

        foreach ($stores as $store) {
            $email = strtolower($store) . '@example.com';
            $vendor = Vendor::firstOrCreate([
                'email' => $email,
            ],
                [
                    'f_name' => $faker->firstName,
                    'l_name' => $faker->lastName,
                    'phone' => $faker->phoneNumber,
                    'email' => $email,
                    'password' => Hash::make('123456'),
                    'admin_id' => 1,
                ]
            );

            Store::firstOrCreate(['name' => $store], [
                'phone' => $vendor->phone,
                'email' => $email,
                'vendor_id' => $vendor->id,
            ]);
        }
    }
}
