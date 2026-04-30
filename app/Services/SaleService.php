<?php

namespace App\Services;

use App\Enums\SaleStatusEnum;
use App\Events\ProductSold;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleProduct;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Throw_;

class SaleService
{

    public function   StoreSale(array $data)
    {
        try {
            $products = collect($data['products']);

            $saleProducts = collect([]);

            $products->each(function ($el) use (&$saleProducts) {
                $product = Product::findOrFail($el['product_id']);

                $saleProducts[] = [
                    'product_id' => $product->id,
                    'quantity' => $el['quantity'],
                    'price' => $el['price'],
                    'purchase_price' => $product->purchase_price,
                    'variation' => $el['variation'],
                    'discount' => $el['discount'],
                    'discount_type' => $el['discount_type']
                ];
            });

            $sale = new Sale();
            $sale->name = $data['name'];
            $sale->status = $data['status'];
            $sale->warehouse_id = $data['warehouse_id'];
            $sale->user_id = $data['user_id'];
            $sale->total_price = $this->getProductsTotalPrice($saleProducts);
            $sale->products_count = $saleProducts->sum('quantity');
            $sale->delivery_man_id = $data['delivery_man_id'] ?? null;
            $sale->delivery_charge = $data['delivery_charge'] ?? null;
            $sale->order_id = $data['order_id'];
            $sale->save();


            $sale->saleProducts()->createMany($saleProducts->toArray());


            return $sale;
        } catch (\Throwable $th) {
            throw new Exception('error storing sale: ' . $th->getMessage());
        }


        // $sale = Sale::query()->create($data);
    }


    public function updateSale(array $data, Sale $sale)
    {
        try {
            $saleProducts = collect([]);
            $sale->saleProducts()->delete();
            $products = collect($data['products']);

            $products->each(function ($el) use (&$saleProducts) {
                $product = Product::findOrFail($el['product_id']);
                $saleProducts[] = [
                    'product_id' => $product->id,
                    'quantity' => $el['quantity'],
                    'price' =>  $this->getProductPrice($product, $el['variation'], $el['price']),
                    'purchase_price' => $product->purchase_price,
                    'variation' => $el['variation'],
                    'discount' => $el['discount'],
                    'discount_type' => $el['discount_type']
                ];
            });


            $sale->name = $data['name'];
            $sale->status = $data['status'];
            $sale->warehouse_id = $data['warehouse_id'];
            $sale->total_price = $this->getProductsTotalPrice($saleProducts);
            $sale->products_count = $saleProducts->sum('quantity');
            $sale->delivery_man_id = $data['delivery_man_id'] ?? null;
            $sale->delivery_charge = $data['delivery_charge'] ?? null;
            $sale->save();

            $sale->saleProducts()->createMany($saleProducts->toArray());

            return $sale;
        } catch (\Throwable $th) {
            throw new Exception('error storing sale: ' . $th->getMessage());
        }
    }

    //get Product price according to variation
    public function getProductPrice(Product $product, string $variation = null,): int
    {


        if (!$variation) return $product->price;
        $price = collect(json_decode($product->variations, true))->map(function ($el) use ($variation) {
            if ($el['type'] == $variation) {
                return $variation['price'];
            }
        })->filter()->first();

        return $price;
    }



    public function getProductsTotalPrice($products)
    {
        return  $products->reduce(function ($acc, $el) {
            return $acc += $el['price'] * $el['quantity'];
        }, 0);
    }



    public function UpdateProductsProfitability(Sale $sale)
    {
        Log::debug($sale->saleProducts);
        foreach ($sale->saleProducts as $saleProduct) {
            $product = Product::find($saleProduct->product_id);
        if ($product) {
            // Устанавливаем количество проданных единиц
            $product->sales_count += $saleProduct->quantity;

            // Вычисляем текущую прибыль с единицы
            $currentProfitPerUnit = $saleProduct->price - $saleProduct->purchase_price;

            // Пересчитываем среднюю прибыль с единицы
            $product->profit_per_unit = $currentProfitPerUnit;

            // Общая прибыль: добавляем прибыль от текущей продажи
            $product->total_profit += $currentProfitPerUnit * $saleProduct->quantity;

            // Общая выручка: добавляем выручку от текущей продажи
            $product->total_revenue = ($product->total_revenue ?? 0) + ($saleProduct->price * $saleProduct->quantity);

            // Рентабельность: общая прибыль делится на общую выручку и умножается на 100
            $product->profitability = $product->total_revenue > 0
                ? ($product->total_profit / $product->total_revenue) * 100
                : 0;

            $product->save();
        }

        }

    }
}
