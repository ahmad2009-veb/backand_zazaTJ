<?php

namespace Database\Factories;

use App\Models\Food;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $restaurantIds = Restaurant::pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();
        $orderIds = Order::pluck('id')->toArray();

        return [
            'food_id' => Food::get()->random()->id,
            'user_id' => function () use ($userIds) {
                return $userIds[array_rand($userIds)];
            },
            'restaurant_id' => function () use ($restaurantIds) {
                return $restaurantIds[array_rand($restaurantIds)];
            },
            'order_id' => function () use ($orderIds) {
                return $orderIds[array_rand($orderIds)];
            },
            'item_campaign_id' => null,
            'comment' => null,
            'rating' => $this->faker->numberBetween(1, 5),

        ];

//        return [
//            'food_id'          => $this->faker->numberBetween(1, 5000),
//            'user_id'          => $this->faker->numberBetween(1, 153701),
//            'order_id'         => $this->faker->numberBetween(1, 297950),
//            'item_campaign_id' => null,
//            'comment'          => $this->faker->name(),
//            'rating'           => $this->faker->numberBetween(1, 5),
//            'created_at'       => now(),
//            'updated_at'       => now(),
//        ];
    }
}
