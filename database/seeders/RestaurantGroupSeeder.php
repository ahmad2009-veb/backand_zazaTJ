<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\RestaurantGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RestaurantGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $restaurants = [
            ['name' => 'Chicken'],
//            ['name' => 'Burger Grill'],
//            ['name' => 'Sait Efendi'],
//            ['name' => 'Lagman House'],
//            ['name' => 'Roll House'],
//            ['name' => 'Шаурмастер'],
//            ['name' => 'NOVO PIZZA'],
        ];
        foreach ($restaurants as $restaurant) {
            $group = RestaurantGroup::query()->firstOrNew([
                'name' => $restaurant['name'],
            ]);
            if (!$group->exists) {
                $group->logo = $this->findLogoImage($restaurant['name'], 'logo');
                $group->cover_photo = $this->findLogoImage($restaurant['name'], 'cover_photo');
                $group->save();
            }

        }

    }

    public function findLogoImage($name, $type)
    {
        $restaurant = Restaurant::query()->where('name', 'LIKE', '%' . $name . '%')
            ->first();
        return $restaurant->{$type} ?? null;
    }
}
