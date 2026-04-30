<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = ['Кроссовки', 'Туфли', 'Боссоножки', 'Ботинки'];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category],
                [
                    'parent_id' => 0,
                    'position' => 0,
                    'status' => 1,
                    'priority' => 0,
                    'is_popular' => 0,
                ]);
        }
    }
}
