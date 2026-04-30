<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $storeIds = Store::pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();
        return [
            'user_id'                    => $this->faker->randomElement($userIds),
            'zone_id'                    => $this->faker->numberBetween(1, 6),
            'order_amount'               => $this->faker->randomNumber(3),
            'order_status'               => $this->faker->randomElement([
                'pending',
                'delivered',
                'failed',
                'confirmed',
                'processing',
                'refunded',
                'canceled'

            ]),
            'store_id'              => $this->faker->randomElement($storeIds),
            'store_discount_amount' => $this->faker->randomNumber(3),
        ];
    }
}
