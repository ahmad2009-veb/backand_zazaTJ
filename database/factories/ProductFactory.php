<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Food;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
//        $category_id = $this->faker->numberBetween(5, 38);

        // $categoryIds = Category::pluck('id')->toArray(); // Get all existing category IDs

        // $categoryId1 = $this->faker->randomElement($categoryIds); // Select a random ID
        // $categoryId2 = $this->faker->randomElement(array_diff($categoryIds, [$categoryId1])); // Select another random ID, ensuring it's different


        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
            'image' => 'product_image.jpg',
            'category_ids' => json_encode([
                ['id' => (string)1, 'position' => 1],
                ['id' => (string)2, 'position' => 2],
            ]),
            'category_id' => 1,
            'variations' => '[{"type":"Red-L","price":120},{"type":"Red-S","price":100},{"type":"White-L","price":120},{"type":"White-S","price":100}]',
            'add_ons' => '[]',
            'attributes' => '["2","1"]',
            'choice_options' => '[{"name":"choice_2","title":"Color","options":["Red","White"]},{"name":"choice_1","title":"Size","options":["L","S"]}]',
            'price' => $this->faker->randomNumber(2),
            'available_time_starts' => '10:00:00',
            'available_time_ends' => '22:00:00',
            'store_id' => $this->faker->randomElement([3, 4, 5]),
            'discount' => $this->faker->numberBetween(0, 100),
            'discount_type' => $this->faker->randomElement(['percent', 'amount']),
           'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),



        ];
    }
}
