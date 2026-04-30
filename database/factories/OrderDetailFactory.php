<?php

namespace Database\Factories;

use App\CentralLogics\Helpers;
use App\Models\Food;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderDetailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrderDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $productIds = Product::pluck('id')->toArray();
        $product = Product::find($this->faker->randomElement($productIds));

        if ($product) {
            $product = Helpers::product_data_formatting($product);
            $orderIds = Order::pluck('id')->toArray();

            return [
                'product_id' => $product['id'],
                'order_id' => $this->faker->randomElement($orderIds),
                'item_campaign_id' => null,
                'price' => $this->faker->randomNumber(2),
                'product_details' => json_encode($product),
                'quantity' => $this->faker->numberBetween(1, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
}

